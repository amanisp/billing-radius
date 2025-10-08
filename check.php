<?php

/**
 * Laravel Case Sensitivity Checker
 * --------------------------------
 * Cek apakah semua file class Laravel (app/) punya nama file dan namespace yang sesuai PSR-4.
 * Cocok buat mencegah error "Class not found" di server Linux.
 */

$baseDir = __DIR__ . '/app';
$namespaceBase = 'App';

$rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir));
$errors = [];

foreach ($rii as $file) {
    if ($file->isDir() || $file->getExtension() !== 'php') continue;

    $relativePath = str_replace($baseDir . '/', '', $file->getPathname());
    $classPath = str_replace('/', '\\', substr($relativePath, 0, -4)); // remove .php
    $expectedClass = $namespaceBase . '\\' . $classPath;

    $content = file_get_contents($file->getPathname());

    if (preg_match('/namespace\s+([^;]+);/i', $content, $nsMatch)) {
        $namespace = trim($nsMatch[1]);
    } else {
        $namespace = $namespaceBase;
    }

    if (preg_match('/class\s+([^\s]+)/i', $content, $classMatch)) {
        $className = trim($classMatch[1]);
    } elseif (preg_match('/trait\s+([^\s]+)/i', $content, $classMatch)) {
        $className = trim($classMatch[1]);
    } elseif (preg_match('/interface\s+([^\s]+)/i', $content, $classMatch)) {
        $className = trim($classMatch[1]);
    } else {
        continue;
    }

    $declaredClass = $namespace . '\\' . $className;

    // Cek apakah sesuai PSR-4 dan case-sensitive
    if ($declaredClass !== $expectedClass) {
        $errors[] = [
            'file' => $relativePath,
            'expected' => $expectedClass,
            'found' => $declaredClass,
        ];
    }
}

if (empty($errors)) {
    echo "✅ Semua file class Laravel sudah sesuai PSR-4 (case-sensitive)\n";
} else {
    echo "⚠️ Ditemukan ketidaksesuaian namespace/nama file:\n\n";
    foreach ($errors as $err) {
        echo "- File: {$err['file']}\n";
        echo "  Expected: {$err['expected']}\n";
        echo "  Found:    {$err['found']}\n\n";
    }
    echo "💡 Perbaiki nama file atau namespace di atas agar sesuai PSR-4.\n";
}
