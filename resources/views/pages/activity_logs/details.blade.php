@php
    $details = $activity->properties;
    // Older log entries stored properties as a JSON string inside the JSON column.
    if (is_string($details)) {
        $details = json_decode($details, true);
    }
    $formatValue = static function ($value) {
        if ($value === null || $value === '') return '—';
        if (is_bool($value)) return $value ? 'Yes' : 'No';
        return is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) : (string) $value;
    };
@endphp
@if (is_array($details) && count($details))
    <details class="mt-2 small">
        <summary class="text-primary" style="cursor: pointer">View details</summary>
        @if (isset($details['before']) || isset($details['after']))
            @php
                $before = $details['before'] ?? [];
                $after = $details['after'] ?? [];
                $fields = array_unique(array_merge(array_keys($before), array_keys($after)));
            @endphp
            <div class="table-responsive mt-2">
                <table class="table table-sm table-bordered mb-2">
                    <thead><tr><th>Field</th><th>Before</th><th>After</th></tr></thead>
                    <tbody>
                        @foreach ($fields as $field)
                            <tr>
                                <th>{{ \Illuminate\Support\Str::headline($field) }}</th>
                                <td style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $formatValue($before[$field] ?? null) }}</td>
                                <td style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $formatValue($after[$field] ?? null) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
        @foreach (array_diff_key($details, array_flip(['before', 'after', 'changed_fields'])) as $key => $value)
            <div class="mt-1"><strong>{{ \Illuminate\Support\Str::headline($key) }}:</strong>
                <span style="white-space: pre-wrap; overflow-wrap: anywhere">{{ $formatValue($value) }}</span>
            </div>
        @endforeach
    </details>
@endif
