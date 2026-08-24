<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\TransactionEvent;
use Illuminate\Support\Facades\Storage;

$user = App\Models\User::first();
auth()->login($user);

$beforeRecords = TransactionEvent::whereNotNull('transferred_at')->count();
$beforePending = TransactionEvent::whereNull('transferred_at')->count();
echo "BEFORE: records={$beforeRecords}, pending={$beforePending}" . PHP_EOL;

// build a small clean CSV (no duplicate names) like the importer expects
$csv = "full_name,contact_no,address,age,birth_date,client_category,transaction_category,transaction_type\n";
$csv .= "ZZZ Test Import One,09170000001,Somewhere,30,1995-05-10,SENIOR,ID,NEW\n";
$csv .= "ZZZ Test Import Two,09170000002,Anywhere,25,2000-02-20,PWD,CERTIFICATE,RENEWAL\n";
Storage::disk('local')->put('import-test.csv', $csv);

$uploaded = new Illuminate\Http\UploadedFile(
    Storage::disk('local')->path('import-test.csv'),
    'import-test.csv', 'text/csv', null, true
);
$req = Illuminate\Http\Request::create('/transaction-events/import', 'POST');
$req->files->set('csv_file', $uploaded);

$ctrl = app(App\Http\Controllers\TransactionEventsController::class);
$resp = $ctrl->import($req);

echo 'REDIRECTS_TO: ' . ($resp->getTargetUrl() ? basename(parse_url($resp->getTargetUrl(), PHP_URL_PATH)) : '?') . PHP_EOL;

$afterRecords = TransactionEvent::whereNotNull('transferred_at')->count();
echo "AFTER: records={$afterRecords} (+" . ($afterRecords - $beforeRecords) . ")" . PHP_EOL;

$newest = TransactionEvent::whereNotNull('transferred_at')->orderByDesc('id')->take(2)->get();
foreach ($newest as $e) {
    echo "  #{$e->id} {$e->full_name} | tx: " . ($e->transferredTransaction?->transaction_id ?? '-') . PHP_EOL;
}

// cleanup test rows + client + history + archive
foreach ($newest as $e) {
    if ($e->transferred_transaction_id) {
        App\Models\TransactionHistory::whereKey($e->transferred_transaction_id)->delete();
    }
    $e->delete();
}
App\Models\Client::where('first_name', 'like', 'ZZZ Test Import%')->delete();
Storage::disk('local')->delete('import-test.csv');
// remove the archive file created by the import
$files = Storage::disk('local')->files('transaction-events-archive');
foreach ($files as $f) {
    if (str_contains($f, 'import-test')) Storage::disk('local')->delete($f);
}
echo 'CLEANED' . PHP_EOL;
