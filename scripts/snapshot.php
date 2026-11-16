<?php

$root = dirname(__DIR__);
$path = getenv('FIELDFLOW_SNAPSHOT_PATH');
if (! $path || ! str_starts_with(str_replace('\\', '/', $path), str_replace('\\', '/', $root).'/.runtime/')) {
    throw new RuntimeException('Invalid snapshot target.');
}
if (file_exists($path)) {
    throw new RuntimeException('Snapshot already exists.');
}
$source = new SQLite3($root.'/database/database.sqlite', SQLITE3_OPEN_READONLY);
$destination = new SQLite3($path);
if (! $source->backup($destination)) {
    throw new RuntimeException('SQLite backup failed.');
}
$destination->close();
$source->close();
