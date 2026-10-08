<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Resumable fuzzy matching: no full scan runs inside the page GET request. */
class DuplicateClientScan
{
    private const GENERATION = 'duplicate_clients_scan_generation_v2';

    public static function invalidate(): void
    {
        // Existing requests may finish, but cannot publish into a new generation.
        Cache::forget(self::GENERATION);
    }

    public function generation(): string
    {
        Cache::add(self::GENERATION, (string) Str::uuid(), 3600);

        return Cache::get(self::GENERATION);
    }

    private function key(string $generation, string $suffix): string
    {
        return 'duplicate_clients_scan_v2_'.$generation.'_'.$suffix;
    }

    public function results(): ?array
    {
        return Cache::get($this->key($this->generation(), 'results'));
    }

    public function advance(int $pairLimit = 50000): array
    {
        $generation = $this->generation();
        $resultKey = $this->key($generation, 'results');
        if (Cache::has($resultKey)) {
            return ['done' => true, 'message' => 'Review ready.'];
        }
        $lock = Cache::lock($this->key($generation, 'lock'), 30);
        if (!$lock->get()) {
            return ['done' => false, 'message' => 'Another reviewer is updating the scan.'];
        }
        try {
            $stateKey = $this->key($generation, 'state');
            $packed = Cache::get($stateKey);
            $state = $packed ? unserialize(gzdecode(base64_decode($packed, true)), ['allowed_classes' => false]) : [
                'phase' => 'read', 'cursor' => 0, 'max_id' => (int) DB::table('clients')->max('id'),
                'read' => 0, 'nodes' => [], 'node_keys' => [], 'phonetic' => [], 'parent' => [],
            ];
            if ($state['phase'] === 'read') {
                $this->read($state);
            } elseif ($state['phase'] === 'index') {
                $this->index($state);
            } else {
                $this->compare($state, max(1, $pairLimit));
            }
            // Client edits/imports during a batch invalidate its snapshot.
            if (Cache::get(self::GENERATION) !== $generation) {
                return ['done' => false, 'message' => 'Client data changed; restarting with fresh records.'];
            }
            if ($state['phase'] === 'done') {
                Cache::put($resultKey, $this->groups($state), 1800);
                Cache::forget($stateKey);

                return ['done' => true, 'message' => 'Review ready.'];
            }
            // Base64 is essential for database cache stores using UTF-8 TEXT.
            Cache::put($stateKey, base64_encode(gzencode(serialize($state), 1)), 3600);
            $message = match ($state['phase']) {
                'read' => 'Reading clients: '.number_format($state['read']).' records processed.',
                'index' => 'Building name index: '.number_format($state['indexed']).' distinct names processed.',
                default => 'Comparing spellings: '.number_format($state['compared']).' of '.number_format($state['pairs']).' candidates processed.',
            };

            return ['done' => false, 'phase' => $state['phase'], 'processed' => $state['read'], 'message' => $message];
        } finally {
            $lock->release();
        }
    }

    private function read(array &$state): void
    {
        $rows = DB::table('clients')->select(['id', 'first_name', 'middle_name', 'last_name'])
            ->where('id', '>', $state['cursor'])->where('id', '<=', $state['max_id'])
            ->orderBy('id')->limit(2000)->get();
        foreach ($rows as $row) {
            $state['cursor'] = (int) $row->id;
            $state['read']++;
            [$surname, $given] = $this->extract($row);
            if ($surname === '' && $given === '') {
                continue;
            }
            $rawName = strtolower(trim((string) $row->first_name)).'|'.strtolower(trim((string) $row->last_name));
            $key = json_encode([$surname, $given, $rawName]);
            $index = $state['node_keys'][$key] ?? null;
            if ($index === null) {
                $index = count($state['nodes']);
                $state['node_keys'][$key] = $index;
                $state['nodes'][] = ['sur' => $surname, 'given' => $given, 'ids' => []];
                $state['parent'][] = $index;
                if ($row->first_name !== null && $row->last_name !== null) {
                    $phonetic = soundex(strtolower(trim($row->first_name))).'|'.soundex(strtolower(trim($row->last_name)));
                    $state['phonetic'][$phonetic][$rawName][] = $index;
                }
            }
            // Collapse repeated identical names before comparing, avoiding n² work.
            $state['nodes'][$index]['ids'][] = (int) $row->id;
        }
        if ($rows->count() < 2000 || $state['cursor'] >= $state['max_id']) {
            foreach ($state['phonetic'] as $names) {
                if (count($names) < 2) {
                    continue;
                }
                $anchor = null;
                foreach ($names as $indices) {
                    foreach ($indices as $index) {
                        $anchor ??= $index;
                        $this->union($state, $anchor, $index);
                    }
                }
            }
            unset($state['phonetic'], $state['node_keys']);
            $state['phase'] = 'index';
            $state['indexed'] = 0;
            $state['blocks'] = [];
        }
    }

    private function index(array &$state): void
    {
        $end = min(count($state['nodes']), $state['indexed'] + 1000);
        while ($state['indexed'] < $end) {
            $index = $state['indexed']++;
            $surname = $state['nodes'][$index]['sur'];
            if ($surname === '') {
                continue;
            }
            $keys = ['d:'.$surname];
            for ($i = 0, $length = strlen($surname); $i < $length; $i++) {
                $keys[] = 'd:'.substr($surname, 0, $i).substr($surname, $i + 1);
            }
            if (($skeleton = $this->skeleton($surname)) !== '') {
                $keys[] = 'k:'.$skeleton;
            }
            foreach (array_unique($keys) as $key) {
                $state['blocks'][$key][] = $index;
            }
        }
        if ($end === count($state['nodes'])) {
            $blocks = [];
            $seen = [];
            $state['pairs'] = 0;
            foreach ($state['blocks'] as $indices) {
                if (count($indices) < 2) {
                    continue;
                }
                $root = $this->root($state, $indices[0]);
                $connected = true;
                foreach ($indices as $index) {
                    if ($this->root($state, $index) !== $root) {
                        $connected = false;
                        break;
                    }
                }
                if ($connected) {
                    continue; // phonetic pass already merged every member
                }
                $key = sha1(implode(',', $indices));
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $blocks[] = $indices;
                $state['pairs'] += count($indices) * (count($indices) - 1) / 2;
            }
            $state['blocks'] = $blocks;
            $state['phase'] = 'compare';
            $state['block'] = 0;
            $state['left'] = 0;
            $state['right'] = 1;
            $state['compared'] = 0;
        }
    }

    private function compare(array &$state, int $limit): void
    {
        $deadline = microtime(true) + 1.0;
        $processed = 0;
        while ($state['block'] < count($state['blocks'])) {
            $indices = $state['blocks'][$state['block']];
            $size = count($indices);
            if ($state['left'] >= $size - 1) {
                $state['block']++;
                $state['left'] = 0;
                $state['right'] = 1;
                continue;
            }
            $left = $indices[$state['left']];
            $right = $indices[$state['right']++];
            if ($state['right'] >= $size) {
                $state['left']++;
                $state['right'] = $state['left'] + 1;
            }
            $state['compared']++;
            if ($this->root($state, $left) !== $this->root($state, $right)
                && $this->matches($state['nodes'][$left], $state['nodes'][$right])) {
                $this->union($state, $left, $right);
            }
            if (++$processed >= $limit || microtime(true) >= $deadline) {
                return;
            }
        }
        $state['phase'] = 'done';
    }

    private function groups(array &$state): array
    {
        $groups = [];
        $nodes = [];
        foreach ($state['nodes'] as $index => $node) {
            $root = $this->root($state, $index);
            $nodes[$root] = ($nodes[$root] ?? 0) + 1;
            foreach ($node['ids'] as $id) {
                $groups[$root][] = $id;
            }
        }

        return array_values(array_filter($groups, fn ($ids, $root) => $nodes[$root] > 1, ARRAY_FILTER_USE_BOTH));
    }

    private function root(array &$state, int $index): int
    {
        while ($state['parent'][$index] !== $index) {
            $state['parent'][$index] = $state['parent'][$state['parent'][$index]];
            $index = $state['parent'][$index];
        }

        return $index;
    }

    private function union(array &$state, int $left, int $right): void
    {
        $state['parent'][$this->root($state, $right)] = $this->root($state, $left);
    }

    private function skeleton(string $value): string
    {
        return str_replace(['a', 'e', 'i', 'o', 'u', ' '], '', $value);
    }

    private function matches(array $left, array $right): bool
    {
        [$a, $b, $givenA, $givenB] = [$left['sur'], $right['sur'], $left['given'], $right['given']];
        if ($a === '' || $b === '' || ($a === $b && $givenA === $givenB) || abs(strlen($a) - strlen($b)) > 2) {
            return false;
        }
        if ($a !== $b) {
            if (min(strlen($a), strlen($b)) < 4) {
                return false;
            }
            $distance = levenshtein($a, $b);
            if ($distance > 1 && !($distance === 2 && $this->skeleton($a) !== '' && $this->skeleton($a) === $this->skeleton($b))) {
                return false;
            }
        }

        return $givenA !== '' && $givenB !== '' && ($givenA === $givenB
            || (min(strlen($givenA), strlen($givenB)) >= 4 && levenshtein($givenA, $givenB) <= 1)
            || (min(strlen($givenA), strlen($givenB)) >= 3 && (str_starts_with($givenA, $givenB) || str_starts_with($givenB, $givenA))));
    }

    private function extract(object $row): array
    {
        $first = trim((string) $row->first_name);
        $middle = trim((string) $row->middle_name);
        $last = trim((string) $row->last_name);
        if (str_contains($first, ',')) {
            $surname = explode(',', $first, 2)[0];
            $given = str_contains($middle, ',') ? explode(',', $middle, 2)[1] : $middle;
            if ($given === '' && preg_match('/^[a-z]{2,}$/i', str_replace(['.', ','], '', $last))
                && !in_array(strtolower(preg_replace('/[^a-z]/i', '', $last)), ['jr', 'sr', 'ii', 'iii', 'iv'], true)) {
                $given = $last;
            }
        } elseif (strlen(preg_replace('/[^a-z]/i', '', $last)) >= 2 && !in_array(strtolower($last), ['jr', 'sr', 'ii', 'iii', 'iv', 'v'], true)) {
            [$surname, $given] = [$last, $first];
        } elseif (str_contains($middle, ',')) {
            [$surname, $given] = explode(',', $middle, 2);
            $given = $given !== '' ? $given : $first;
        } else {
            [$surname, $given] = ['', $first.' '.$last];
        }
        $clean = fn ($value) => strtolower(trim(preg_replace('/\s+/', ' ', preg_replace('/[^a-z0-9 ]+/i', ' ', $value))));

        return [$clean($surname), $clean($given)];
    }
}
