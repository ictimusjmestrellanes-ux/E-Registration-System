<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Client;
use App\Models\TransactionEvent;
use App\Models\TransactionHistory;
use Illuminate\Http\Request;

class TransactionEventsController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    public function index(Request $request)
    {
        $query = TransactionEvent::query();

        if ($search = $request->input('search')) {
            $query->where('full_name', 'like', "%{$search}%");
        }

        if ($contact = $request->input('contact')) {
            $query->where('contact_no', 'like', "%{$contact}%");
        }

        if ($ageFrom = $request->input('age_from')) {
            $query->where('age', '>=', (int) $ageFrom);
        }

        if ($ageTo = $request->input('age_to')) {
            $query->where('age', '<=', (int) $ageTo);
        }

        if ($dateFrom = $request->input('date_from')) {
            $query->whereDate('created_at', '>=', $dateFrom);
        }

        if ($dateTo = $request->input('date_to')) {
            $query->whereDate('created_at', '<=', $dateTo);
        }

        if ($request->boolean('duplicate_names')) {
            $duplicateNames = TransactionEvent::query()
                ->select('full_name')
                ->whereNotNull('full_name')
                ->where('full_name', '<>', '')
                ->groupBy('full_name')
                ->havingRaw('COUNT(*) > 1');

            $query->whereIn('full_name', $duplicateNames);
        }

        if ($request->boolean('duplicate_names')) {
            $query->orderBy('full_name')->orderBy('id', 'desc');
        } else {
            $query->orderByRaw('ISNULL(transferred_at) DESC, id DESC');
        }

        $events = $query->paginate(15)->withQueryString();

        return view('pages.transaction_events.transactionEvents', compact('events'));
    }

    public function preview(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $this->parseCsv($request->file('csv_file'));

        if (!empty($result['errors'])) {
            return response()->json(['success' => false, 'message' => $result['errors'][0]], 422);
        }

        return response()->json([
            'success'      => true,
            'rows'         => $result['rows'],
            'total'        => $result['total'],
            'skipped'      => $result['skipped'],
            'skipped_rows' => $result['skipped_rows'],
        ]);
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $result = $this->parseCsv($request->file('csv_file'));

        if (!empty($result['errors'])) {
            return back()->withErrors(['csv_file' => $result['errors'][0]]);
        }

        $rows = $result['rows'];
        $imported = 0;
        $batch = [];

        foreach ($rows as $data) {
            $batch[] = [
                'full_name'           => $data['full_name'],
                'contact_no'          => $data['contact_no'],
                'address'             => $data['address'],
                'age'                 => $data['age'],
                'birth_date'          => $data['birth_date'],
                'client_category'     => $data['client_category'],
                'transaction_category' => $data['transaction_category'],
                'transaction_type'    => $data['transaction_type'],
                'created_at'          => now(),
                'updated_at'          => now(),
            ];

            if (count($batch) >= 500) {
                TransactionEvent::insert($batch);
                $this->createClientsFromEvents($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        if (!empty($batch)) {
            TransactionEvent::insert($batch);
            $this->createClientsFromEvents($batch);
            $imported += count($batch);
        }

        $skipped = $result['skipped'];
        $message = "Successfully imported {$imported} event(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        return redirect()->route('transaction-events.index')->with('success', $message);
    }

    public function transfer(TransactionEvent $event)
    {
        $nameParts = $this->splitFullName($event->full_name);

        $client = Client::whereRaw('LOWER(first_name) = ?', [strtolower($nameParts['first_name'])])
            ->whereRaw('LOWER(last_name) = ?', [strtolower($nameParts['last_name'])])
            ->first();

        if (!$client) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'No matching client found for "' . $event->full_name . '". Transfer the client first via CSV import.');
        }

        if (empty($event->transaction_category) && empty($event->transaction_type)) {
            return redirect()->route('transaction-events.index')
                ->with('error', 'Event #' . $event->id . ' has no transaction category or type to transfer.');
        }

        $transactionId = $this->nextTransferredTransactionId();
        $clientCategory = $event->client_category ?: $client->sector;

        $transaction = TransactionHistory::create([
            'client_id'        => $client->client_id,
            'client_category'  => $clientCategory,
            'transaction_id'   => $transactionId,
            'transaction_date' => now(),
            'category'         => $event->transaction_category,
            'type'             => $event->transaction_type,
            'source'           => 'E-Registration',
            'clerk'            => auth()->user()->name ?? 'System',
            'status'           => 'Approved',
            'description'      => 'Transferred from imported event for ' . $event->full_name,
        ]);

        ActivityLog::create([
            'user_id'      => auth()->id(),
            'action'       => 'transaction_created',
            'description'  => 'Created transaction ' . $transactionId . ' from imported event.',
            'subject_type' => 'TransactionHistory',
            'subject_id'   => $transaction->id,
            'properties'   => json_encode(['event_id' => $event->id]),
        ]);

        if (!empty($event->client_category)) {
            $client->update(['sector' => $event->client_category]);
        }

        $event->update(['transferred_at' => now()]);

        return redirect()->route('transaction-events.index')
            ->with('success', 'Transaction ' . $transactionId . ' created successfully for ' . $client->full_name . '.');
    }

    private function nextTransferredTransactionId(): string
    {
        $prefix = '2600000-' . now()->format('y') . '-';

        $maxSequence = TransactionHistory::query()
            ->where('transaction_id', 'like', $prefix . '%')
            ->pluck('transaction_id')
            ->reduce(function (int $max, string $transactionId) use ($prefix) {
                $suffix = substr($transactionId, strlen($prefix));

                return ctype_digit($suffix) ? max($max, (int) $suffix) : $max;
            }, -1);

        $sequence = $maxSequence + 1;

        return $prefix . str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }

    private function parseCsv($file): array
    {
        $contents = file_get_contents($file->getPathname());
        $contents = ltrim($contents, "\xEF\xBB\xBF");
        $tempPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempPath, $contents);
        $handle = fopen($tempPath, 'r');

        if ($handle === false) {
            @unlink($tempPath);
            return ['errors' => ['Unable to read the uploaded file.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            @unlink($tempPath);
            return ['errors' => ['The CSV file is empty or has no header row.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $header = array_map(function ($column) {
            return preg_replace('/[^a-z0-9]+/', '_', trim(strtolower($column)));
        }, $header);
        $header = array_filter($header);
        $header = array_values($header);

        if (!in_array('full_name', $header)) {
            fclose($handle);
            @unlink($tempPath);
            return ['errors' => ['Missing required column: full_name.'], 'rows' => [], 'total' => 0, 'skipped' => 0];
        }

        $rows = [];
        $skippedRows = [];
        $lineNumber = 1;

        while (($row = fgetcsv($handle)) !== false) {
            $lineNumber++;
            $row = array_map('trim', $row);
            $row = array_slice(array_pad($row, count($header), ''), 0, count($header));

            $data = array_combine($header, $row);
            $fullName = $data['full_name'] ?? '';

            if (empty($fullName)) {
                $skippedRows[] = [
                    'line'   => $lineNumber,
                    'reason' => 'Empty full_name',
                    'data'   => $data,
                ];
                continue;
            }

            $age = $this->parseImportedAge($data['age'] ?? null);
            if (($data['age'] ?? '') !== '' && $age === null) {
                $skippedRows[] = [
                    'line'   => $lineNumber,
                    'reason' => 'Invalid age value',
                    'data'   => $data,
                ];
                continue;
            }

            $rows[] = [
                'full_name'           => $fullName,
                'contact_no'          => $data['contact_no'] ?? '',
                'address'             => $data['address'] ?? '',
                'age'                 => $age,
                'birth_date'          => $this->parseImportedDate($data['birth_date'] ?? $data['birthdate'] ?? null),
                'client_category'     => $data['client_category'] ?? '',
                'transaction_category' => $data['transaction_category'] ?? '',
                'transaction_type'    => $data['transaction_type'] ?? '',
            ];
        }

        fclose($handle);
        @unlink($tempPath);

        return [
            'errors'       => [],
            'rows'         => $rows,
            'total'        => count($rows),
            'skipped'      => count($skippedRows),
            'skipped_rows' => $skippedRows,
        ];
    }

    private function createClientsFromEvents(array $events): void
    {
        $existingNames = [];

        foreach ($events as $event) {
            $nameParts = $this->splitFullName($event['full_name']);
            $nameKey = strtolower(trim($nameParts['first_name'] . '|' . $nameParts['last_name']));
            $existingNames[$nameKey] = true;
        }

        $existing = [];
        if (!empty($existingNames)) {
            $nameKeys = array_keys($existingNames);
            $existing = Client::where(function ($q) use ($nameKeys) {
                foreach ($nameKeys as $key) {
                    [$first, $last] = explode('|', $key);
                    $q->orWhere(function ($q) use ($first, $last) {
                        $q->whereRaw('LOWER(first_name) = ?', [$first])
                          ->whereRaw('LOWER(last_name) = ?', [$last]);
                    });
                }
            })->get()->keyBy(function ($c) {
                return strtolower(trim($c->first_name . '|' . $c->last_name));
            })->toArray();
        }

        foreach ($events as $event) {
            $nameParts = $this->splitFullName($event['full_name']);
            $nameKey = strtolower(trim($nameParts['first_name'] . '|' . $nameParts['last_name']));

            if (isset($existing[$nameKey])) {
                continue;
            }

            $clientData = [
                'client_id'   => Client::generateClientId(),
                'first_name'  => $nameParts['first_name'],
                'middle_name' => $nameParts['middle_name'],
                'last_name'   => $nameParts['last_name'],
                'suffix'      => $nameParts['suffix'],
                'age'         => $event['age'],
                'contact'     => $event['contact_no'],
                'address'     => $event['address'],
            ];

            if (!empty($event['birth_date'])) {
                $clientData['birth_date'] = $event['birth_date'];
            }

            if (!empty($event['client_category'])) {
                $clientData['sector'] = $event['client_category'];
            }

            Client::create($clientData);
        }
    }

    private function parseImportedDate(?string $date): ?string
    {
        if (empty($date)) {
            return null;
        }

        $date = trim($date);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $date;
        }

        $formats = ['m/d/Y', 'm/d/y', 'Y-m-d', 'd/m/Y', 'd-m-Y', 'Y/m/d'];
        foreach ($formats as $format) {
            $parsed = \DateTime::createFromFormat($format, $date);
            if ($parsed !== false) {
                return $parsed->format('Y-m-d');
            }
        }

        $timestamp = strtotime($date);
        if ($timestamp !== false) {
            return date('Y-m-d', $timestamp);
        }

        return null;
    }

    private function parseImportedAge(?string $age): ?int
    {
        if ($age === null || $age === '') {
            return null;
        }

        $age = filter_var($age, FILTER_VALIDATE_INT, [
            'options' => [
                'min_range' => 0,
                'max_range' => 120,
            ],
        ]);

        return $age === false ? null : $age;
    }

    private function splitFullName(string $fullName): array
    {
        $suffixes = ['jr', 'sr', 'ii', 'iii', 'iv', 'v'];
        $parts = array_values(array_filter(explode(' ', trim($fullName))));
        $count = count($parts);

        $suffix = '';
        $lastPart = strtolower(end($parts));

        if (in_array($lastPart, $suffixes) && $count > 1) {
            $suffix = array_pop($parts);
            $count--;
        }

        $firstName = '';
        $lastName = '';
        $middleName = '';

        if ($count === 1) {
            $firstName = $parts[0];
        } elseif ($count === 2) {
            $firstName = $parts[0];
            $lastName = $parts[1];
        } else {
            $firstName = array_shift($parts);
            $lastName = array_pop($parts);
            $middleName = implode(' ', $parts);
        }

        return [
            'first_name'  => $firstName,
            'middle_name' => $middleName,
            'last_name'   => $lastName,
            'suffix'      => $suffix,
        ];
    }
}
