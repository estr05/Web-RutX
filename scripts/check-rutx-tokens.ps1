$errorCount = 0
$files = Get-ChildItem -Path "resources/views", "resources/css" -Recurse -File | Where-Object { $_.Name -ne "tokens.css" }

foreach ($file in $files) {
    $matches = Select-String -Path $file.FullName -Pattern "#[0-9a-fA-F]{3,6}\b"
    if ($matches) {
        Write-Host "Hexadecimal found in $($file.FullName):" -ForegroundColor Red
        foreach ($match in $matches) {
            Write-Host "  Line $($match.LineNumber): $($match.Line)"
        }
        $errorCount++
    }
}

if ($errorCount -gt 0) {
    Write-Host "Anti-hex audit failed. Found $errorCount files with hexadecimals." -ForegroundColor Red
    exit 1
}

Write-Host "Anti-hex audit passed." -ForegroundColor Green
exit 0
