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
     * Different spelling but phonetically similar first/last names (SOUNDEX).
     */
    private function findSimilarSpellingDuplicates(): \Illuminate\Support\Collection
    {
        $keys = Client::query()
            ->selectRaw(self::SOUNDEX_KEY . ' as keyval')
            ->whereNotNull('first_name')
            ->whereNotNull('last_name')
            ->groupBy('keyval')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('keyval')
            ->map(fn ($v) => (string) $v)
            ->toArray();

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
            ->whereIn(DB::raw(self::SOUNDEX_KEY), $keys)
            ->get();

        return $clients->groupBy(function ($client) {
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
        })->map(fn ($items) => $this->groupPayload($items))->values();
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