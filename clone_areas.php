<?php
$areas = [
    'direccion_general', 'secretaria_particular', 'organo_interno', 'quejas',
    'responsabilidades', 'planeacion_evaluacion', 'investigacion_cientifica',
    'apoyo_investigacion', 'formacion_rrhh', 'desarrollo_vinculacion',
    'desarrollo_tecnologico', 'vinculacion', 'financiamiento_divulgacion',
    'financiamiento', 'difusion', 'juridico_igualdad', 'administrativo',
    'juridico', 'archivo'
];

$source = __DIR__ . '/admin';
$base_dest = __DIR__ . '/areas';

if (!is_dir($base_dest)) mkdir($base_dest, 0775, true);

foreach ($areas as $area) {
    $dest = $base_dest . '/' . $area;
    if (!is_dir($dest)) mkdir($dest, 0775, true);
    
    // Using exec to copy recursively
    exec("cp -r " . escapeshellarg($source . '/*') . " " . escapeshellarg($dest . '/'));
    echo "Cloned to $area\n";
}
echo "Done.";
