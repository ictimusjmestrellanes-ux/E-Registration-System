<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\TransactionEvent;
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

        $events = $query->orderBy('id', 'desc')->paginate(15)->withQueryString();

        return view('pages.transaction_events.transactionEvents', compact('events'));
    }

    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120',
        ]);

        $file = $request->file('csv_file');
        $contents = file_get_contents($file->getPathname());
        $contents = ltrim($contents, "\xEF\xBB\xBF");
        $tempPath = tempnam(sys_get_temp_dir(), 'csv');
        file_put_contents($tempPath, $contents);
        $handle = fopen($tempPath, 'r');

        if ($handle === false) {
            @unlink($tempPath);
            return back()->withErrors(['csv_file' => 'Unable to read the uploaded file.']);
        }

        $header = fgetcsv($handle);

        if ($header === false) {
            fclose($handle);
            @unlink($tempPath);
            return back()->withErrors(['csv_file' => 'The CSV file is empty or has no header row.']);
        }

        $header = array_map('trim', array_map('strtolower', $header));
        $header = array_filter($header);
        $header = array_values($header);
        $requiredColumns = ['full_name'];

        foreach ($requiredColumns as $col) {
            if (!in_array($col, $header)) {
                fclose($handle);
                @unlink($tempPath);
                return back()->withErrors(['csv_file' => "Missing required column: {$col}."]);
            }
        }

        $imported = 0;
        $skipped = 0;
        $batch = [];

        while (($row = fgetcsv($handle)) !== false) {
            $row = array_map('trim', $row);
            $row = array_values(array_filter($row));

            $data = array_combine($header, array_pad($row, count($header), ''));
            $fullName = $data['full_name'] ?? '';

            if (empty($fullName)) {
                $skipped++;
                continue;
            }

            $batch[] = [
                'full_name'  => $fullName,
                'contact_no' => $data['contact_no'] ?? '',
                'address'    => $data['address'] ?? '',
                'age'        => !empty($data['age']) ? (int) $data['age'] : null,
                'created_at' => now(),
                'updated_at' => now(),
            ];

            if (count($batch) >= 500) {
                TransactionEvent::insert($batch);
                $this->createClientsFromEvents($batch);
                $imported += count($batch);
                $batch = [];
            }
        }

        fclose($handle);
        @unlink($tempPath);

        if (!empty($batch)) {
            TransactionEvent::insert($batch);
            $this->createClientsFromEvents($batch);
            $imported += count($batch);
        }

        $message = "Successfully imported {$imported} event(s).";
        if ($skipped > 0) {
            $message .= " Skipped {$skipped} invalid row(s).";
        }

        return redirect()->route('transaction-events.index')->with('success', $message);
    }

    private function createClientsFromEvents(array $events): void
    {
        foreach ($events as $event) {
            $nameParts = $this->splitFullName($event['full_name']);

            Client::create([
                'client_id'   => Client::generateClientId(),
                'first_name'  => $nameParts['first_name'],
                'middle_name' => $nameParts['middle_name'],
                'last_name'   => $nameParts['last_name'],
                'suffix'      => $nameParts['suffix'],
                'age'         => $event['age'],
                'contact'     => $event['contact_no'],
                'address'     => $event['address'],
            ]);
        }
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

    public function destroy(TransactionEvent $transactionEvent)
    {
        $transactionEvent->delete();

        return redirect()->route('transaction-events.index')
            ->with('success', 'Transaction event deleted successfully.');
    }
}
