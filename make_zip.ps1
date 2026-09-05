Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

function Create-LinuxCompatibleZip($sourceDir, $zipPath) {
    if (Test-Path $zipPath) {
        Remove-Item $zipPath -Force
    }
    
    $zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)
    $sourceFull = (Get-Item $sourceDir).FullName
    $files = Get-ChildItem -Path $sourceDir -Recurse -File

    foreach ($f in $files) {
        $relativePath = $f.FullName.Substring($sourceFull.Length + 1).Replace('\', '/')
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $f.FullName, $relativePath, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
    }
    
    $zip.Dispose()
    $sizeMb = [Math]::Round((Get-Item $zipPath).Length / 1MB, 2)
    Write-Host "Created Linux-compatible ZIP: $zipPath ($sizeMb MB, $($files.Count) files)" -ForegroundColor Green
}

Create-LinuxCompatibleZip -sourceDir "public_html" -zipPath "hostinger_latest_update.zip"
Create-LinuxCompatibleZip -sourceDir "public_html" -zipPath "sms_quick_patch.zip"
