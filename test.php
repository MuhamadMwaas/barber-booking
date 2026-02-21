<?php

require 'vendor/autoload.php';
use Illuminate\Support\Str;

$TaxCalculatorService = new \App\Services\TaxCalculatorService();

/**
 * Merge ALL files from a directory into one text file
 *
 * @param string $directory  Path to the folder
 * @param string $outputFile Output file path
 */
/**
 * Merge all files from directory and subdirectories up to specific depth
 *
 * @param string   $directory
 * @param resource $outputHandle
 * @param int      $currentDepth
 * @param int      $maxDepth
 */
function mergeFilesRecursive(
    string $directory,
    $outputHandle,
    int $currentDepth,
    int $maxDepth
): void {
    // ⛔ إيقاف عند تجاوز العمق
    if ($currentDepth > $maxDepth) {
        return;
    }

    $items = scandir($directory);

    foreach ($items as $item) {

        if ($item === '.' || $item === '..') {
            continue;
        }

        $fullPath = $directory . DIRECTORY_SEPARATOR . $item;

        // 📄 إذا كان ملف
        if (is_file($fullPath) && is_readable($fullPath)) {

            $content = file_get_contents($fullPath);
            if ($content === false) {
                continue;
            }

            fwrite($outputHandle, $fullPath . PHP_EOL);
            fwrite($outputHandle, $content . PHP_EOL);
            fwrite($outputHandle, str_repeat('+', 20) . PHP_EOL . PHP_EOL);
        }

        // 📁 إذا كان مجلد → ندخل recursion
        if (is_dir($fullPath)) {
            mergeFilesRecursive(
                $fullPath,
                $outputHandle,
                $currentDepth + 1,
                $maxDepth
            );
        }
    }
}

/**
 * Entry point
 */
function mergeDirectoryWithDepth(
    string $directory,
    string $outputFile,
    int $maxDepth = 3
): void {
    if (!is_dir($directory)) {
        throw new InvalidArgumentException("المجلد غير موجود: $directory");
    }

    $handle = fopen($outputFile, 'w');

    if ($handle === false) {
        throw new RuntimeException("لا يمكن إنشاء ملف الإخراج");
    }

    mergeFilesRecursive($directory, $handle, 0, $maxDepth);

    fclose($handle);
}
mergeDirectoryWithDepth(
    __DIR__ .'\app\Services\Fiskaly',
    __DIR__ .'\Fiskaly.txt',
    4
);
