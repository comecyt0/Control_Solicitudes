<?php
echo "DB_USER (_ENV): " . ($_ENV['DB_USER'] ?? 'EMPTY') . "\n";
echo "DB_USER (getenv): " . (getenv('DB_USER') ?: 'EMPTY') . "\n";
echo "variables_order: " . ini_get('variables_order') . "\n";
