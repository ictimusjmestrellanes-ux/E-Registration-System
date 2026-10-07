<?php

namespace App\Support;

class ImportName
{
    public static function split(string $value): array
    {
        $value = preg_replace('/\s+/u', ' ', trim($value));
        $suffix = '';
        if (preg_match('/[\s,]+(JR\.?|SR\.?|II|III|IV|V)$/i', $value, $match)) {
            $suffix = strtoupper(rtrim($match[1], '.'));
            $value = trim(substr($value, 0, -strlen($match[0])));
        }
        $first = $middle = $last = '';
        if (str_contains($value, ',')) {
            [$last, $given] = array_map('trim', explode(',', $value, 2));
            $first = $given;
            if (preg_match('/^(.*?)\s+([\p{L}]\.?)$/u', $given, $match)) {
                $first = $match[1];
                $middle = $match[2];
            }
        } elseif (preg_match('/^(\S+)\s+(.+?)\s+(-{2,})$/u', $value, $match)) {
            // Some imports use dashes as an explicit missing-middle-name
            // placeholder in "LASTNAME FIRSTNAME -----" records.
            [, $last, $first, $middle] = $match;
        } elseif (preg_match('/^(\S+)\s+(.+?)\s+([\p{L}]\.?)$/u', $value, $match)) {
            // Some source files use "LASTNAME FIRSTNAME M.I." without a
            // comma. Do not mistake the trailing middle initial for the
            // surname (for example, "ALDEA LORETO A.").
            [, $last, $first, $middle] = $match;
        } elseif (preg_match('/^(.+?)\s+([\p{L}]\.?)\s+(.+)$/u', $value, $match)) {
            [, $first, $middle, $last] = $match;
        } else {
            // Preserve the existing interpretation for unmarked names.
            $parts = $value === '' ? [] : explode(' ', $value);
            $first = array_shift($parts) ?? '';
            $last = array_pop($parts) ?? '';
            $middle = implode(' ', $parts);
        }

        return compact('first', 'middle', 'last', 'suffix');
    }

    /**
     * Split an event name for use in a linked client profile.
     *
     * A comma-form event name with a full middle name is normally ambiguous
     * because given names can contain spaces. When the existing client has a
     * middle name with the same initial, preserve the established field split.
     *
     * @return array{first: string, middle: string, last: string, suffix: string}
     */
    public static function splitForClientProfile(string $value, ?string $currentMiddleName = null): array
    {
        $name = self::split($value);
        $currentMiddleName = trim((string) $currentMiddleName);

        if ($name['middle'] !== '' || $currentMiddleName === '' || ! str_contains($value, ',')) {
            return $name;
        }

        $givenParts = preg_split('/\s+/u', trim($name['first']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        if (count($givenParts) < 2) {
            return $name;
        }

        $middleCandidate = end($givenParts);
        $currentInitial = mb_strtolower(mb_substr($currentMiddleName, 0, 1));
        $candidateInitial = mb_strtolower(mb_substr((string) $middleCandidate, 0, 1));
        if ($currentInitial === '' || $currentInitial !== $candidateInitial) {
            return $name;
        }

        array_pop($givenParts);
        $name['first'] = implode(' ', $givenParts);
        $name['middle'] = (string) $middleCandidate;

        return $name;
    }

    public static function format(string $value): string
    {
        $name = self::split($value);
        $middle = $name['middle'];
        if (mb_strlen($middle) === 1) {
            $middle .= '.';
        }

        $given = trim(implode(' ', array_filter([$name['first'], $middle], fn ($part) => $part !== '')));
        $formatted = $name['last'] !== '' && $given !== ''
            ? $name['last'].', '.$given
            : $name['last'].$given;

        return trim(implode(' ', array_filter([$formatted, $name['suffix']], fn ($part) => $part !== '')));
    }
}
