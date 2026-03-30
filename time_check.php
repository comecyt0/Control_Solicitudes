<?php
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/helpers.php';

header('Content-Type: text/plain');

echo "PHP Timezone: " . date_default_timezone_get() . "\n";
echo "PHP Date: " . date('Y-m-d H:i:s') . "\n";
echo "PHP Date (Y-m-d): " . date('Y-m-d') . "\n";

$pdo = getConnection();
$dbInfo = $pdo->query("SELECT NOW() as now, NOW()::date as date, CURRENT_SETTING('TIMEZONE') as tz")->fetch();

echo "DB Time: " . $dbInfo['now'] . "\n";
echo "DB Date: " . $dbInfo['date'] . "\n";
echo "DB Timezone: " . $dbInfo['tz'] . "\n";

if (date('Y-m-d') === $dbInfo['date']) {
    echo "SYNC_OK: Dates match.\n";
} else {
    echo "SYNC_ERROR: Dates mismatch!\n";
}
