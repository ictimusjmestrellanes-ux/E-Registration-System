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
