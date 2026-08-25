<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
auth()->login($user);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$r = $kernel->handle(Illuminate\Http\Request::create('/clients', 'GET'));
$html = $r->getContent();
echo 'STATUS: ' . $r->getStatusCode() . PHP_EOL;

// every id required by the Create Client script guard (line ~655)
$ids = ['openCameraBtn','capturePhotoBtn','retakePhotoBtn','cameraWrapper','cameraView',
    'cameraUnsupportedMessage','cameraUnsupportedReason','cameraCanvas','clientPhotoData',
    'birthDateInput' => 0,'ageInput' => 0,'preview','clientPhotoFileInput','provinceSelect',
    'citySelect','barangaySelect','cameraModal'];
foreach ($ids as $key => $fallback) {
    $id = is_string($key) ? $key : $fallback;
    $count = substr_count($html, 'id="' . $id . '"');
    echo str_pad($id, 28) . " x{$count}" . ($count === 0 ? '   <-- MISSING' : '') . PHP_EOL;
}

// syntax check main script
preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $scripts);
$biggest = ''; foreach ($scripts[1] as $b) if (strlen(trim($b)) > strlen(trim($biggest))) $biggest = trim($b);
file_put_contents(__DIR__ . '/main.js', $biggest);
echo 'MAIN_SCRIPT_BYTES: ' . strlen($biggest) . PHP_EOL;
