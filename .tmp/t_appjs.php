<?php
$s = file_get_contents('public/assets/js/app.js');
echo 'len: ' . strlen($s) . PHP_EOL;
foreach (['collapse', 'menu-dropdown', 'navbar-nav', 'data-bs-toggle'] as $kw) {
    echo $kw . ': ' . substr_count($s, $kw) . PHP_EOL;
}
// find context around 'collapse'
$pos = 0;
while (($pos = strpos($s, 'collapse', $pos)) !== false) {
    echo "---\n" . substr($s, max(0, $pos - 150), 320) . "\n";
    $pos += 8;
}
