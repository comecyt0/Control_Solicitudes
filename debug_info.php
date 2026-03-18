<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/config/auth.php';

echo "BASE_URL: " . BASE_URL . "\n";
echo "SCRIPT_NAME: " . $_SERVER['SCRIPT_NAME'] . "\n";
echo "DIRNAME: " . dirname($_SERVER['SCRIPT_NAME']) . "\n";
