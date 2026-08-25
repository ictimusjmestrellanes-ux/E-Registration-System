<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$user = App\Models\User::first();
auth()->login($user);
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);

$r = $kernel->handle(Illuminate\Http\Request::create('/client-list', 'GET'));
$html = $r->getContent();

// extract all inline script bodies
preg_match_all('/<script(?![^>]*src=)[^>]*>(.*?)<\/script>/s', $html, $scripts);
foreach ($scripts[1] as $i => $body) {
    $body = trim($body);
    if ($body === '') continue;
    file_put_contents(__DIR__ . "/script_{$i}.js", $body);
    echo "script {$i}: " . strlen($body) . " bytes" . PHP_EOL;
}
echo 'TOGGLE_LISTENER_PRESENT: ' . (str_contains($html, "clientFiltersToggleBtn.addEventListener") ? 'yes' : 'NO') . PHP_EOL;
echo 'GUARD_HAS_FORMEL: ' . (str_contains($html, "!clientFiltersFormEl") ? 'yes' : 'NO') . PHP_EOL;
