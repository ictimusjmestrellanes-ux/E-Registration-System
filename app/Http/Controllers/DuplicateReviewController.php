<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Support\Facades\DB;

class DuplicateReviewController extends Controller
{
    private const NAME_KEY = "CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)))";
    private const EXACT_KEY = "CONCAT_WS('|', LOWER(TRIM(first_name)), LOWER(TRIM(COALESCE(middle_name,''))), LOWER(TRIM(last_name)), COALESCE(birth_date,''))";
    private const SOUNDEX_KEY = "CONCAT(COALESCE(SOUNDEX(LOWER(TRIM(first_name))),''), '|', COALESCE(SOUNDEX(LOWER(TRIM(last_name))),''))";

    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index()
    {
        if (!feature_allowed('Duplicate Clients Review')) {
            abort(404);
        }

        $exactGroups = $this->findExactDuplicates();
        $likelyGroups = $this->findLikelyDuplicates();
        $similarGroups = $this->findSimilarSpellingDuplicates();

        return view('pages.duplicates.index', compact('exactGroups', 'likelyGroups', 'similarGroups'));
    }

    /**
     * Same normalized full name AND exact same birth date.
     */
    private function findExactDuplicates(): \Illuminate\Support\Collection
    {
        $keys = Client::query()
            ->selectRaw(self::EXACT_KEY . ' as keyval')
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        return $this->groupClientsByKey($keys, self::EXACT_KEY);
    }

    /**
     * Same normalized full name, but birth dates are missing (null) or only
     * match on the year.
     */
    private function findLikelyDuplicates(): \Illuminate\Support\Collection
    {
        $nameKeys = Client::query()
            ->selectRaw(self::NAME_KEY . ' as keyval')
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        if (empty($nameKeys)) {
            return collect();
        }

        $clients = Client::query()
            ->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status',
                'email', 'contact', 'contact_2', 'address',
                'province', 'city', 'barangay',
                'photo_path', 'created_at',
            ])
            ->whereIn(DB::raw(self::NAME_KEY), $nameKeys)
            ->get();

        return $clients->groupBy(function ($client) {
            return implode('|', [
                strtolower(trim($client->first_name)),
                strtolower(trim($client->middle_name ?? '')),
                strtolower(trim($client->last_name)),
            ]);
        })->filter(function ($items) {
            if ($items->count() < 2) {
                return false;
            }

            $birthDates = $items->pluck('birth_date')->filter()->map(fn ($d) => $d->format('Y-m-d'));

            // Fully identical birth dates belong to the exact tab.
            if ($birthDates->count() === $items->count() && $birthDates->unique()->count() === 1) {
                return false;
            }

            // Some dates missing -> likely duplicate.
            if ($birthDates->count() < $items->count()) {
                return true;
            }

            // Only year-level match -> likely duplicate.
            $years = $birthDates->map(fn ($d) => substr($d, 0, 4))->unique();

            return $years->count() === 1;
        })->map(fn ($items) => $this->groupPayload($items))->values();
    }

    /**
     * Different spelling but phonetically similar first/last names (SOUNDEX),
     * plus near-spelling/typo matches (e.g. Iscober/Escobar) detected in PHP
     * because MySQL has no built-in LEVENSHTEIN.
     */
    private function findSimilarSpellingDuplicates(): \Illuminate\Support\Collection
    {
        $columns = [
            'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
            'age', 'birth_date', 'gender', 'civil_status',
            'email', 'contact', 'contact_2', 'address',
            'province', 'city', 'barangay',
            'photo_path', 'created_at',
        ];

        // ---------- Pass 1: SOUNDEX-based phonetic matches ----------
        $keys = Client::query()
            ->selectRaw(self::SOUNDEX_KEY . ' as keyval')
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

        $soundexGroups = collect();
        if (!empty($keys)) {
            $clients = Client::query()
                ->select($columns)
                ->whereIn(DB::raw(self::SOUNDEX_KEY), $keys)
                ->get();

            $soundexGroups = $clients->groupBy(function ($client) {
                return implode('|', [
                    (string) soundex(strtolower(trim($client->first_name))),
                    (string) soundex(strtolower(trim($client->last_name))),
                ]);
            })->filter(function ($items) {
                if ($items->count() < 2) {
                    return false;
                }

                // Skip groups where the normalized names are identical
                // (those belong to the exact/likely tabs).
                $distinctNames = $items->map(function ($client) {
                    return strtolower(trim($client->first_name)) . ' ' . strtolower(trim($client->last_name));
                })->unique();

                return $distinctNames->count() > 1;
            });
        }

        // ---------- Pass 2: near-spelling (typo) matches ----------
        $allClients = Client::query()->select($columns)->get();

        $clean = fn (?string $s) => strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/i', ' ', (string) $s))));

        // Extract the real surname and given name. Some imported records store
        // "SURNAME, Given Name" inside first_name/middle_name, so handle both
        // formats instead of trusting the column layout.
        $extract = function ($client) use ($clean) {
            $first = trim((string) $client->first_name);
            $middle = trim((string) $client->middle_name);
            $last = trim((string) $client->last_name);

            if (str_contains($first, ',')) {
                // Scrambled import format: "SURNAME," in first_name.
                $sur = trim(str_replace(',', ' ', explode(',', $first)[0]));
                $given = str_contains($middle, ',')
                    ? trim(explode(',', $middle, 2)[1])
                    : $middle;

                // When the import left middle_name empty, the given name usually
                // ended up in last_name ("CALDO," + "" + "PATRICK").
                if ($given === '' && preg_match('/^[a-z]{2,}$/i', str_replace(['.', ','], '', $last))
                    && !in_array(strtolower(preg_replace('/[^a-z]/i', '', $last)), ['jr', 'sr', 'ii', 'iii', 'iv'], true)) {
                    $given = $last;
                }

                return ['sur' => $clean($sur), 'given' => $clean($given)];
            }

            $lettersOnlyLast = preg_replace('/[^a-z]/i', '', strtolower($last));
            if ($lettersOnlyLast !== '' && strlen($lettersOnlyLast) >= 2 && !in_array(strtolower($last), ['jr', 'sr', 'ii', 'iii', 'iv', 'v'], true)) {
                return ['sur' => $clean($last), 'given' => $clean($first)];
            }

            // last_name is a bare initial or suffix; the surname may sit in middle_name ("CRUZ, JUAN").
            if (str_contains($middle, ',')) {
                [$mSur, $mGiven] = array_pad(explode(',', $middle, 2), 2, '');

                return ['sur' => $clean($mSur), 'given' => $clean($mGiven !== '' ? $mGiven : $first)];
            }

            return ['sur' => '', 'given' => $clean($first . ' ' . $last)];
        };

        $levOk = function (?string $a, ?string $b, int $max): bool {
            $a = trim((string) $a);
            $b = trim((string) $b);
            if ($a === '' || $b === '') {
                return false;
            }
            if ($a === $b) {
                return true;
            }
            // Short strings (initials etc.) must match exactly to avoid false positives.
            if (strlen($a) < 4 || strlen($b) < 4) {
                return false;
            }
            return levenshtein($a, $b) <= $max;
        };

        $indexed = $allClients->map(fn ($c) => [
            'client' => $c,
        ] + $extract($c))->filter(fn ($x) => $x['sur'] !== '' || $x['given'] !== '');

        $n = $indexed->count();

        // Union-find over indices so overlapping pairs merge into one group.
        $parent = range(0, max($n - 1, 0));
        $find = function ($i) use (&$find, &$parent) {
            while ($parent[$i] !== $i) {
                $parent[$i] = $parent[$parent[$i]];
                $i = $parent[$i];
            }
            return $i;
        };
        $union = function ($a, $b) use ($find, &$parent) {
            $ra = $find($a);
            $rb = $find($b);
            if ($ra !== $rb) {
                $parent[$rb] = $ra;
            }
        };

        // Track pairs already grouped by the soundex pass so we do not duplicate them.
        $seenPairs = [];
        foreach ($soundexGroups as $items) {
            $ids = $items->pluck('id')->values();
            for ($i = 0; $i < $ids->count(); $i++) {
                for ($j = $i + 1; $j < $ids->count(); $j++) {
                    $seenPairs[min($ids[$i], $ids[$j]) . '-' . max($ids[$i], $ids[$j])] = true;
                }
            }
        }

        $byId = $indexed->values();

        for ($i = 0; $i < $n; $i++) {
            for ($j = $i + 1; $j < $n; $j++) {
                $a = $byId[$i];
                $b = $byId[$j];

                $pairKey = min($a['client']->id, $b['client']->id) . '-' . max($a['client']->id, $b['client']->id);
                if (isset($seenPairs[$pairKey])) {
                    continue;
                }
                $seenPairs[$pairKey] = true;

                // Identical normalized names belong to the exact/likely tabs.
                if ($a['sur'] === $b['sur'] && $a['given'] === $b['given']) {
                    continue;
                }

                // Surnames must match exactly, be a single typo apart, or be a
                // 2-letter typo that stays phonetically identical (Iscober/Escobar).
                $sa = $a['sur'];
                $sb = $b['sur'];
                if ($sa === '' || $sb === '') {
                    continue;
                }
                if ($sa !== $sb) {
                    if (strlen($sa) < 4 || strlen($sb) < 4) {
                        continue;
                    }
                    $d = levenshtein($sa, $sb);
                    // A 2-letter difference is only accepted when the consonant
                    // skeleton is identical (Iscober/Escobar -> scbr), which
                    // rejects unrelated names like Lapid/Sapida.
                    $skelA = str_replace(['a', 'e', 'i', 'o', 'u'], '', $sa);
                    $skelB = str_replace(['a', 'e', 'i', 'o', 'u'], '', $sb);
                    if (!($d <= 1 || ($d === 2 && $skelA !== '' && $skelA === $skelB))) {
                        continue;
                    }
                }

                $givenOk = $a['given'] !== '' && $b['given'] !== '' && (
                    $a['given'] === $b['given']
                    || $levOk($a['given'], $b['given'], 1)
                    || (min(strlen($a['given']), strlen($b['given'])) >= 3
                        && (str_starts_with($a['given'], $b['given']) || str_starts_with($b['given'], $a['given']))));

                if ($givenOk) {
                    $union($i, $j);
                }
            }
        }

        $typoGroups = collect();
        if ($n > 0) {
            $buckets = [];
            foreach ($byId as $idx => $item) {
                $root = $find($idx);
                $buckets[$root][] = $item['client'];
            }

            foreach ($buckets as $members) {
                if (count($members) < 2) {
                    continue;
                }
                $typoGroups->push(collect($members));
            }
        }

        // Use a plain (non-Eloquent) collection: Eloquent's merge() expects
        // models, but these hold groups of models.
        return collect()
            ->merge($soundexGroups)
            ->merge($typoGroups)
            ->map(fn ($items) => $this->groupPayload($items))
            ->values();
    }

    private function groupClientsByKey(array $keys, string $keyExpr): \Illuminate\Support\Collection
    {
        if (empty($keys)) {
            return collect();
        }

        $clients = Client::query()
            ->select([
                'id', 'client_id', 'first_name', 'middle_name', 'last_name', 'suffix',
                'age', 'birth_date', 'gender', 'civil_status',
                'email', 'contact', 'contact_2', 'address',
                'province', 'city', 'barangay',
                'photo_path', 'created_at',
            ])
            ->whereIn(DB::raw($keyExpr), $keys)
            ->get();

        return $clients->groupBy(function ($client) use ($keyExpr) {
            return $keyExpr === self::EXACT_KEY
                ? implode('|', [
                    strtolower(trim($client->first_name)),
                    strtolower(trim($client->middle_name ?? '')),
                    strtolower(trim($client->last_name)),
                    $client->birth_date?->format('Y-m-d'),
                ])
                : implode('|', [
                    strtolower(trim($client->first_name)),
                    strtolower(trim($client->middle_name ?? '')),
                    strtolower(trim($client->last_name)),
                ]);
        })->map(fn ($items) => $this->groupPayload($items))->values();
    }

    private function groupPayload($items): array
    {
        return [
            'total' => $items->count(),
            'clients' => $items,
            'created_at' => $items->min('created_at'),
        ];
    }
}