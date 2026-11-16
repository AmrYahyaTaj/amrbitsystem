<?php

// Composer ZIP extraction adapter using XAMPP's existing, working ZIP extension.
// Laravel and Composer themselves continue to run on the isolated PHP 8.4 runtime.
if ($argc !== 5 || $argv[1] !== '-qq' || $argv[3] !== '-d') {
    fwrite(STDERR, 'Unsupported arguments');
    exit(1);
}
$zip = new ZipArchive;
if ($zip->open($argv[2]) !== true) {
    fwrite(STDERR, 'Invalid ZIP archive');
    exit(1);
}
$total = 0;
for ($i = 0; $i < $zip->numFiles; $i++) {
    $entry = $zip->statIndex($i);
    $name = str_replace('\\', '/', $entry['name']);
    if (str_starts_with($name, '/') || preg_match('~(^|/)\.\.(/|$)|^[A-Za-z]:~', $name)) {
        fwrite(STDERR, 'Unsafe archive path');
        exit(1);
    }
    $total += $entry['size'];
}
if ($total > 50 * 1024 * 1024 && $total > filesize($argv[2]) * 100) {
    fwrite(STDERR, 'Possible zip bomb');
    exit(1);
}
$ok = $zip->extractTo($argv[4]);
$zip->close();
exit($ok ? 0 : 1);
