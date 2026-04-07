param()
$enc = New-Object System.Text.UTF8Encoding $False
$srcPath = 'c:\Intranet\areas\desarrollo_tecnologico\agenda.php'
$src = [System.IO.File]::ReadAllText($srcPath, $enc)
Write-Host "Source loaded: $($src.Length) chars"

$areas = @(
    @{ file='juridico_igualdad';  cve='17'; color='#1e3a5f'; dark='#142845'; hover='#254a78'; shadow='rgba(30,58,95,.4)';    nombre='Juridico-Administrativo';             suffix='ja'   },
    @{ file='apoyo_investigacion'; cve='9';  color='#3730a3'; dark='#1e1b6b'; hover='#4338ca'; shadow='rgba(55,48,163,.4)';  nombre='Apoyo a Investigacion Cientifica';    suffix='inv'  },
    @{ file='financiamiento';      cve='15'; color='#064e3b'; dark='#022c22'; hover='#065f46'; shadow='rgba(6,78,59,.4)';    nombre='Financiamiento';                      suffix='fin'  },
    @{ file='formacion_rrhh';      cve='10'; color='#0f766e'; dark='#0a4b46'; hover='#14b8a6'; shadow='rgba(15,118,110,.4)'; nombre='Formacion y Recursos Humanos';        suffix='rrhh' },
    @{ file='juridico';            cve='19'; color='#991b1b'; dark='#7f1d1d'; hover='#b91c1c'; shadow='rgba(153,27,27,.4)';  nombre='Asuntos Juridicos';                   suffix='aj'   }
)

foreach ($a in $areas) {
    $c = $src
    $c = $c.Replace('$cveArea      = 12;',   ('$cveArea      = ' + $a.cve + ';'))
    $c = $c.Replace('#6d28d9', $a.color)
    $c = $c.Replace('#4c1d95', $a.dark)
    $c = $c.Replace('#7c3aed', $a.hover)
    $c = $c.Replace('rgba(109,40,217,.4)', $a.shadow)
    $c = $c.Replace('Desarrollo Tecnologico y Vinculacion', $a.nombre)
    $c = $c.Replace('.nota-dt', ('.nota-' + $a.suffix))
    $c = $c.Replace("'nota-dt'", ("'nota-" + $a.suffix + "'"))

    $target = ('c:\Intranet\areas\' + $a.file + '\agenda.php')
    [System.IO.File]::WriteAllText($target, $c, $enc)
    Write-Host "Written: $target"
}
Write-Host "Done."
