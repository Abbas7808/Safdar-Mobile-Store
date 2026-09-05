Add-Type -AssemblyName System.IO.Compression
Add-Type -AssemblyName System.IO.Compression.FileSystem

$zipPath = Join-Path $PSScriptRoot "sms_updated_files.zip"
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

$zip = [System.IO.Compression.ZipFile]::Open($zipPath, [System.IO.Compression.ZipArchiveMode]::Create)

$files = @(
    "index.php",
    "backend/data/categories.json",
    "backend/config.php",
    "admin/product-add.php",
    "admin/categories.php",
    "admin/pos.php",
    "admin/includes/smart_importer_modal.php"
)

$baseDir = Join-Path $PSScriptRoot "public_html"

foreach ($rel in $files) {
    $src = Join-Path $baseDir $rel
    if (Test-Path $src) {
        $entryName = $rel.Replace("\", "/")
        [System.IO.Compression.ZipFileExtensions]::CreateEntryFromFile($zip, $src, $entryName, [System.IO.Compression.CompressionLevel]::Optimal) | Out-Null
        Write-Host "Added: $entryName"
    } else {
        Write-Host "Not found: $src" -ForegroundColor Red
    }
}

$zip.Dispose()
$sizeKb = [Math]::Round((Get-Item $zipPath).Length / 1KB, 2)
Write-Host "Created $zipPath ($sizeKb KB)" -ForegroundColor Green
