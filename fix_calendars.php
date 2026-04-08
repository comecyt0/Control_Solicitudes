<?php
$adminFile = 'c:/Intranet/admin/calendario.php';
$publicFile = 'c:/Intranet/public/calendario.php';

// Fix Admin
if (file_exists($adminFile)) {
    $content = file_get_contents($adminFile);
    $pattern = "/abrirModalVer\(\s+'<\?= esc\(addslashes\(\$ev\['titulo'\]\)\) \?>', \s+'<\?= esc\(addslashes\(\$ev\['descripcion'\] \?\? ''\)\) \?>', \s+'<\?= date\('d\/m\/Y H:i', strtotime\(\$ev\['fecha_inicio'\]\)\) \?>', \s+'<\?= date\('d\/m\/Y H:i', strtotime\(\$ev\['fecha_fin'\]\)\) \?>'\s+\)/s";
    $replacement = "abrirModalVer('<?= esc(addslashes(\$ev['titulo'])) ?>', '<?= esc(addslashes(\$ev['descripcion'] ?? '')) ?>', '<?= date('d/m/Y H:i', strtotime(\$ev['fecha_inicio'])) ?>', '<?= date('d/m/Y H:i', strtotime(\$ev['fecha_fin'])) ?>', '<?= \$ev['requiere_sala'] ? 1 : 0 ?>', '<?= esc(addslashes(\$ev['area_solicitante'] ?? 'General')) ?>', '<?= esc(addslashes(\$ev['persona_solicitante'] ?? 'Admin')) ?>')";
    $content = preg_replace($pattern, $replacement, $content);
    file_put_contents($adminFile, $content);
    echo "Admin fixed\n";
}

// Fix Public
if (file_exists($publicFile)) {
    $content = file_get_contents($publicFile);
    
    // Fix verDetalleEvento call
    $pCell = "/verDetalleEvento\('<\?=esc\(\$ev\['titulo'\]\)\?>', '<\?=esc\(\$ev\['descripcion'\]\?\?'Sin detalles'\)\?>', '<\?=date\('d\/m\/Y H:i', strtotime\(\$ev\['fecha_inicio'\]\)\)\?>', '<\?=date\('d\/m\/Y H:i', strtotime\(\$ev\['fecha_fin'\]\)\)\?>'\)/";
    $rCell = "verDetalleEvento('<?=esc(\$ev['titulo'])?>', '<?=esc(\$ev['descripcion']??'Sin detalles')?>', '<?=date('d/m/Y H:i', strtotime(\$ev['fecha_inicio']))?>', '<?=date('d/m/Y H:i', strtotime(\$ev['fecha_fin']))?>', '<?=($ev['requiere_sala']?1:0)?>', '<?=esc(addslashes(\$ev['area_solicitante']??''))?>', '<?=esc(addslashes(\$ev['persona_solicitante']??''))?>')";
    $content = preg_replace($pCell, $rCell, $content);
    
    // Fix grid icons
    $pIcon = "/<\?php if \(!\$esCumple && !empty\(\$ev\['publico'\]\)\): \?>\s+<i class=\"fa-solid fa-earth-americas\" style=\"font-size:0.75rem; flex-shrink: 0; color: #3b82f6;\" title=\"Evento Público\"><\/i>\s+<\?php endif; \?>/";
    $rIcon = "<?php if (!empty(\$ev['requiere_sala'])): ?>
                                     <i class=\"fa-solid fa-person-chalkboard\" style=\"font-size:0.75rem; flex-shrink: 0; color: #ca8a04;\" title=\"Sala de Juntas Reservada\"></i>
                                 <?php endif; ?>
                                 <?php if (!\$esCumple && !empty(\$ev['publico'])): ?>
                                     <i class=\"fa-solid fa-earth-americas\" style=\"font-size:0.75rem; flex-shrink: 0; color: #3b82f6;\" title=\"Evento Público\"></i>
                                 <?php endif; ?>";
    $content = preg_replace($pIcon, $rIcon, $content);
    
    file_put_contents($publicFile, $content);
    echo "Public fixed\n";
}
?>
