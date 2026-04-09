<?php
/**
 * Script de parcheo global para calendarios departamentales
 * Objetivos:
 * 1. Implementar casteo de booleanos para requiere_sala.
 * 2. Implementar renderizado del icono fa-person-chalkboard.
 */

$areasDir = __DIR__ . '/areas/';
$files = [
    'administrativo/calendario.php',
    'apoyo_investigacion/calendario.php',
    'archivo/calendario.php',
    'desarrollo_tecnologico/calendario.php',
    'desarrollo_vinculacion/calendario.php',
    'difusion/calendario.php',
    'direccion_general/calendario.php',
    'financiamiento/calendario.php',
    'financiamiento_divulgacion/calendario.php',
    'formacion_rrhh/calendario.php',
    'investigacion_cientifica/calendario.php',
    'juridico/calendario.php',
    'juridico_igualdad/calendario.php',
    'organo_interno/calendario.php',
    'planeacion_evaluacion/calendario.php',
    'quejas/calendario.php',
    'responsabilidades/calendario.php',
    'secretaria_particular/calendario.php',
    'vinculacion/calendario.php'
];

foreach ($files as $relPath) {
    $fullPath = $areasDir . $relPath;
    if (!file_exists($fullPath)) {
        echo "Saltando: $relPath (No existe)\n";
        continue;
    }

    $c = file_get_contents($fullPath);
    $changed = false;

    // 1. Parchear casteo de booleanos en el mapeo
    $toFindMapping = '$calendarioEventos[$dia][] = $ev;';
    if (strpos($c, $toFindMapping) !== false && strpos($c, "['requiere_sala']") === false) {
        $replacementMapping = "    \$v_sala = \$ev['requiere_sala'] ?? false;\n    \$ev['requiere_sala'] = (\$v_sala === true || \$v_sala === 't' || \$v_sala === 'true' || \$v_sala === '1' || \$v_sala === 1);\n    " . $toFindMapping;
        $c = str_replace($toFindMapping, $replacementMapping, $c);
        $changed = true;
    }

    // 2. Parchear UI Rendering (Icono)
    $toFindUI = '<div class="evento-titulo">';
    if (strpos($c, $toFindUI) !== false && strpos($c, 'fa-person-chalkboard') === false) {
        $toFindPublico = '<?php if ($ev[\'publico\']): ?>';
        // En algunos archivos puede variar el espaciado
        if (strpos($c, $toFindPublico) !== false) {
            $replacementUI = "<?php if (!empty(\$ev['requiere_sala'])): ?>\n                                      <i class=\"fa-solid fa-person-chalkboard\" style=\"color: var(--color-primary); margin-right: 4px;\" title=\"Requiere Sala de Juntas\"></i>\n                                  <?php endif; ?>\n                                  " . $toFindPublico;
            $c = str_replace($toFindPublico, $replacementUI, $c);
            $changed = true;
        }
    }

    if ($changed) {
        file_put_contents($fullPath, $c);
        echo "Parcheado: $relPath\n";
    } else {
        echo "Sin cambios necesarios: $relPath\n";
    }
}
echo "Proceso finalizado.\n";
