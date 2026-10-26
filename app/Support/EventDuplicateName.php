<?php

namespace App\Support;

class EventDuplicateName
{
    /**
     * Parse an event name into the identity used for initial/full-middle
     * duplicate matching.
     *
     * @return array{last: string, first: string, suffix: string, middle: string, middle_initial: string, middle_is_initial: bool}|null
     */
    public static function identity(string $fullName): ?array
    {
        $normalize = static fn ($value) => mb_strtolower(
            preg_replace('/\s+/u', ' ', trim((string) $value)) ?? ''
        );
        $name = ImportName::split($fullName);
        $firstParts = preg_split('/\s+/u', trim($name['first']), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $middle = $normalize($name['middle']);

        if ($middle === '' && count($firstParts) > 1) {
            $middle = $normalize(array_pop($firstParts));
        }

        $first = $normalize(implode(' ', $firstParts));
        $last = $normalize($name['last']);
        $suffix = $normalize($name['suffix']);
        $compactMiddle = preg_replace('/[^\p{L}\p{N}]+/u', '', $middle) ?? '';

        if ($first === '' || $last === '') {
            return null;
        }

        return [
            'last' => $last,
            'first' => $first,
            'suffix' => $suffix,
            'middle' => $compactMiddle,
            'middle_initial' => $compactMiddle === '' ? '' : mb_substr($compactMiddle, 0, 1),
            'middle_is_initial' => $compactMiddle !== '' && mb_strlen($compactMiddle) === 1,
        ];
    }

    /**
     * Columns persisted on transaction_events for scalable duplicate queries.
     * The SHA-256 key keeps the index small even when imported names are long.
     *
     * @return array{duplicate_person_key: ?string, duplicate_middle_is_initial: ?bool, duplicate_name_parsed: bool}
     */
    public static function attributes(string $fullName): array
    {
        $identity = self::identity($fullName);
        if ($identity === null || $identity['middle'] === '') {
            return [
                'duplicate_person_key' => null,
                'duplicate_middle_is_initial' => null,
                'duplicate_name_parsed' => true,
            ];
        }

        $person = [
            $identity['last'],
            $identity['first'],
            $identity['suffix'],
            $identity['middle_initial'],
        ];

        return [
            'duplicate_person_key' => hash('sha256', json_encode(
                $person,
                JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE
            )),
            'duplicate_middle_is_initial' => $identity['middle_is_initial'],
            'duplicate_name_parsed' => true,
        ];
    }
}
