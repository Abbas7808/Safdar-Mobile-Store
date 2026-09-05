<?php
// Build 100% Linux/Hostinger-compatible zip archives with standard forward-slash paths

function buildZip($sourceDir, $outZipFile) {
    if (!file_exists($sourceDir)) {
        die("Source directory '{$sourceDir}' not found.\n");
    }

    $zip = new ZipArchive();
    if ($zip->open($outZipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        die("Failed to create zip file '{$outZipFile}'.\n");
    }

    $sourceRealPath = realpath($sourceDir);
    $dir = new RecursiveDirectoryIterator($sourceRealPath, RecursiveDirectoryIterator::SKIP_DOTS);
    $files = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

    $count = 0;
    foreach ($files as $file) {
        $realPath = $file->getRealPath();
        // Replace Windows backslashes with forward slashes for Linux web servers
        $relativePath = substr($realPath, strlen($sourceRealPath) + 1);
        $linuxRelativePath = str_replace('\\', '/', $relativePath);

        if ($file->isDir()) {
            $zip->addEmptyDir($linuxRelativePath);
        } else {
            $zip->addFile($realPath, $linuxRelativePath);
            $count++;
        }
    }

    $zip->close();
    $sizeMb = round(filesize($outZipFile) / (1024 * 1024), 2);
    echo "Successfully built '{$outZipFile}' ({$sizeMb} MB, {$count} files) with 100% Linux POSIX forward-slash paths.\n";
}

buildZip(__DIR__ . '/public_html', __DIR__ . '/hostinger_latest_update.zip');
buildZip(__DIR__ . '/public_html', __DIR__ . '/sms_quick_patch.zip');
