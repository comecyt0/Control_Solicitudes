<?php
// Mock server variables
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['accion'] = 'gestionar';
$_REQUEST['accion'] = 'gestionar';

// Require helpers and the API (we need to bypass some checks or just test the logic)
$accion = $_REQUEST['accion'] ?? '';
echo "Action caught: " . $accion . "\n";
if ($accion === 'gestionar') {
    echo "SUCCESS: Action recognized as gestionar in POST.\n";
} else {
    echo "FAILURE: Action not recognized.\n";
}
