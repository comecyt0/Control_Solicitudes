param()
$enc = New-Object System.Text.UTF8Encoding $False
$areas = @('juridico_igualdad','apoyo_investigacion','financiamiento','formacion_rrhh','juridico')
foreach ($a in $areas) {
    $fPath = "c:\Intranet\areas\$a\agenda.php"
    if (-not (Test-Path $fPath)) { Write-Host "MISSING: $fPath"; continue }
    $content = [System.IO.File]::ReadAllText($fPath, $enc)
    $hasFix    = $content.Contains('agCerrarModal')
    $hasCumple = $content.Contains('modalVerCumple')
    Write-Host "$a :: hasFix=$hasFix cumpleModal=$hasCumple"
}
