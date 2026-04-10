<?php
require_once __DIR__ . '/config/database.php';
$pdo = getConnection();
echo "Checking for duplicates in 'eventos' with [DG:] markers...\n";
$stmt = $pdo->query("SELECT descripcion, COUNT(*) as c FROM eventos WHERE descripcion LIKE '%[DG:%' GROUP BY descripcion HAVING COUNT(*) > 1");
$dupes = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($dupes)) {
    echo "No actual duplicates found in the 'eventos' table (grouped by description).\n";
} else {
    echo "Found actual duplicates in 'eventos':\n";
    foreach ($dupes as $d) {
        echo "- " . $d['descripcion'] . " (" . $d['c'] . " times)\n";
    }
}

echo "\nChecking for records with markers but different areas...\n";
$stmt = $pdo->query("SELECT id, titulo, cve_area, descripcion FROM eventos WHERE descripcion LIKE '%[DG:%' LIMIT 20");
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    echo "ID: " . $row['id'] . " | Title: " . $row['titulo'] . " | Area: " . $row['cve_area'] . " | Desc: " . $row['descripcion'] . "\n";
}
