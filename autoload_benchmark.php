<?php

function benchmark($name, callable $fn) {
    $start = microtime(true);
    $fn();
    $end = microtime(true);
    echo str_pad($name, 35) . " : " . number_format($end - $start, 4) . " seconds\n";
}

echo "=== Laravel Composer Autoload Benchmark ===\n\n";

// 1. Hitung jumlah file vendor
benchmark('Count vendor files', function () {
    $count = 0;
    $dir = new RecursiveDirectoryIterator(__DIR__ . '/vendor', FilesystemIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($dir) as $file) {
        $count++;
    }
    echo "Vendor files: $count\n";
});

// 2. Tes read speed vendor directory (simulate I/O load)
benchmark('Read all vendor file paths', function () {
    $dir = new RecursiveDirectoryIterator(__DIR__ . '/vendor', FilesystemIterator::SKIP_DOTS);
    foreach (new RecursiveIteratorIterator($dir) as $file) {
        $file->getPathname();
    }
});

// 3. Tes composer dump-autoload -o
benchmark('Composer dump-autoload -o', function () {
    shell_exec('composer dump-autoload -o 2>&1');
});

// 4. Tes composer dump-autoload normal
benchmark('Composer dump-autoload (no -o)', function () {
    shell_exec('composer dump-autoload 2>&1');
});

echo "\nSelesai!\n";
