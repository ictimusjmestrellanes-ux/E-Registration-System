<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Event Records</title>
    <style>
        @page { margin: 22pt 30pt; }
        body { margin: 0; font-family: DejaVu Sans, sans-serif; font-size: 7pt; color: #000; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        h1 { text-align: center; font-size: 9pt; margin: 0; }
        .date { text-align: center; margin: 2pt 0 10pt; font-size: 8pt; }
        table { border-collapse: collapse; width: 100%; table-layout: fixed; }
        th, td { border: .5pt solid #000; padding: 1pt 2pt; overflow-wrap: break-word; }
        th { font-size: 7pt; height: 35pt; text-align: center; }
        td { font-size: 6.5pt; height: 19.5pt; line-height: 8pt; vertical-align: middle; }
        .center { text-align: center; }
        .signoffs { margin-top: 5pt; }
        .signoffs td { border: 0; height: auto; font-size: 8pt; vertical-align: top; padding: 0; }
        .name { margin-top: 12pt; font-weight: bold; }
        .page-number { text-align: right; font-size: 6pt; margin-top: 4pt; }
    </style>
</head>
<body>
@php
    $pages = $events->isEmpty() ? collect([collect()]) : $events->chunk(20);
@endphp
@foreach ($pages as $pageIndex => $pageEvents)
    <div class="page">
        <h1>{{ $isRice ? 'ASSISTANCE TO INDIGENT INDIVIDUALS OR FAMILIES - FOOD ASSISTANCE / RICE DISTRIBUTION PROJECT' : 'EVENT RECORDS - ASSISTANCE DISTRIBUTION' }}</h1>
        <div class="date">DATE: {{ $dateLabel ?: '____________________________' }}</div>
        <table>
            <thead><tr>
                <th style="width:3%">NO</th><th style="width:9%">CLIENT NAME</th><th style="width:16%">BENEF'S NAME</th>
                <th style="width:11%">ADDRESS</th><th style="width:3%">SEX</th><th style="width:3%">AGE</th>
                <th style="width:7%">CLIENT<br>CATEGORY</th><th style="width:12%">TYPE OF<br>ASSISTANCE</th>
                <th style="width:9%">SIGNATURE<br>1st TRANCHE</th><th style="width:9%">SIGNATURE<br>2nd TRANCHE</th>
                <th style="width:9%">SIGNATURE<br>3rd TRANCHE</th><th style="width:9%">SIGNATURE<br>4th TRANCHE</th>
            </tr></thead>
            <tbody>
            @forelse ($pageEvents as $event)
                @php($recordNumber = ($rowOffset ?? 0) + $pageIndex * 20 + $loop->iteration)
                <tr>
                    <td class="center">{{ $recordNumber }}</td><td></td>
                    <td>{{ mb_strtoupper($event->full_name ?? '') }}</td>
                    <td>{{ mb_strtoupper($event->address ?? '') }}</td>
                    <td class="center">{{ mb_strtoupper($event->export_sex ?? '') }}</td>
                    <td class="center">{{ $event->age }}</td>
                    <td>{{ mb_strtoupper($event->client_category ?? '') }}</td>
                    <td class="center">{{ mb_strtoupper(trim((string) $event->transaction_category)) === 'BIGAY BIGAS SA MASA' ? 'FOOD ASSISTANCE' : mb_strtoupper($event->transaction_category ?? '') }}</td>
                    @for ($tranche = 1; $tranche <= 4; $tranche++)
                        <td>{{ in_array($tranche, $details['numbered_tranches'] ?? []) ? $recordNumber : '' }}</td>
                    @endfor
                </tr>
            @empty
                <tr><td colspan="12" class="center">No records match the selected filters.</td></tr>
            @endforelse
            @if ($pageEvents->isNotEmpty())
                @for ($blankRow = $pageEvents->count(); $blankRow < 20; $blankRow++)
                    <tr>
                        @for ($column = 0; $column < 12; $column++)
                            <td></td>
                        @endfor
                    </tr>
                @endfor
            @endif
            </tbody>
        </table>
        <table class="signoffs"><tr>
            <td>Prepared by:<div class="name">{{ $details['prepared_by'] ?? ($isRice ? 'LONIZA B. ESGUERRA' : '________________________') }}</div></td>
            <td>Reviewed by:<div class="name">{{ $details['reviewed_by'] ?? ($isRice ? 'JOSEPHINE G. VILLANUEVA' : '________________________') }}</div></td>
            <td>Approved by:<div class="name">{{ $details['approved_by'] ?? ($isRice ? 'ALEX L. ADVINCULA' : '________________________') }}</div></td>
            <td></td><td></td>
        </tr></table>
        @unless ($mergedExport ?? false)
            <div class="page-number">Page {{ $pageIndex + 1 }} of {{ $pages->count() }}</div>
        @endunless
    </div>
@endforeach
</body>
</html>
