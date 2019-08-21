<?php
declare(strict_types=1);
require_once '../src/Sbdb.php';

use \Betam\Sbdb;

#need to create db first

$dbHost = 'localhost';
$dbName = 'sdbstest';
$dbLogin = 'sdbstest';
$dbPassword = 'sdbstest';

$logfile = 'logfile.log';
file_put_contents($logfile, '');

$db = Sbdb::getInstance();
$db->setDisplayLog(false);
$db->PDOConnect($dbHost, $dbName, $dbLogin, $dbPassword);
$db->setLog($db::LOGDEBUG, $logfile);

$test = $db->query('DROP TABLE IF EXISTS testtable', [], true);

echo 'Test connection: ' . ($db->isConnected ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test query (DROP TABLE): ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;

$test = $db->query('Incorrect query', [], true);
echo 'Test bad query failed: ' . ($test['error'] !== false ? 'PASSED' : 'FAILED') . PHP_EOL;

$test = $db->query('CREATE TABLE `testtable` (
	`auto_id` INT(11) NOT NULL AUTO_INCREMENT,
	`name` VARCHAR(255) NULL DEFAULT NULL,
	`author` VARCHAR(255) NULL DEFAULT NULL,
	`is_disabled` TINYINT(4) NULL DEFAULT \'0\',
	`dt_created` DATETIME NULL DEFAULT NULL,
	PRIMARY KEY (`auto_id`)
    )
    COLLATE=\'utf8_general_ci\'
    ENGINE=InnoDB', [], true);

echo 'Test query (CREATE TABLE): ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;

$test = $db->insert('testtable', 'auto_id');
echo 'Test insert: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test inserted id equal 1: ' . ($test['inserted_id'] === 1 ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test count inserted rows equal 1: ' . ($test['count_rows'] === 1 ? 'PASSED' : 'FAILED') . PHP_EOL;

$test = $db->insert('notable', 'auto_id');
echo 'Test bad insert failed: ' . ($test['error'] !== false ? 'PASSED' : 'FAILED') . PHP_EOL;


$test=$db->update('testtable', ['name'=>'test11', 'a'=>'b', 'dt_created'=>'now()', 'where_auto_id'=>'1', 'auto_id'=>2], 'auto_id=:where_auto_id');
echo 'Test update: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;

$test=$db->query('select * from testtable WHERE auto_id=:where_auto_id', ['where_auto_id'=>'2']);
echo 'Test query select: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test selected auto_id equal 2: ' . ($test['rows'][0]['auto_id'] == 2 ? 'PASSED' : 'FAILED') . PHP_EOL;

$test=$db->update('testtable', ['name'=>'test12', 'a'=>'b', 'dt_created'=>'now()', 'where_auto_id'=>'2', 'auto_id'=>2], 'auto_id=:where_auto_id', ['auto_id']);
echo 'Test update with depricated field: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
$test=$db->query('select * from testtable WHERE name=:where_name', ['where_name'=>'test12']);
echo 'Test query select: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test selected auto_id not updated to 1 by deprication and equal 2: ' . ($test['rows'][0]['auto_id'] == 2 ? 'PASSED' : 'FAILED') . PHP_EOL;

$test=$db->update('testtable', ['name'=>'test11', 'a'=>'b', 'dt_created'=>'now()', 'where_auto_id'=>'2', 'auto_id'=>1], 'auto_id=:where_auto_id');
echo 'Test update back to auto_id=1: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
$test=$db->query('select * from testtable WHERE auto_id=:where_auto_id', ['where_auto_id'=>'1']);
echo 'Test query select: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test selected auto_id equal 1: ' . ($test['rows'][0]['auto_id'] == 1 ? 'PASSED' : 'FAILED') . PHP_EOL;


//reconnect
$db->PDOConnect($dbHost, $dbName, $dbLogin, $dbPassword);

$test=$db->beginTransaction();
echo 'Test beginTransaction: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
$test = $db->insert('testtable', 'auto_id');
echo 'Test insert: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test inserted id equal 2: ' . ($test['inserted_id'] === 2 ? 'PASSED' : 'FAILED') . PHP_EOL;
$test=$db->rollbackTransaction();
echo 'Test rollbackTransaction: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;

$test=$db->beginTransaction();
echo 'Test beginTransaction: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
$test = $db->insert('testtable', 'auto_id');
echo 'Test insert: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;
echo 'Test inserted id equal 3: ' . ($test['inserted_id'] === 3 ? 'PASSED' : 'FAILED') . PHP_EOL;
$test=$db->commitTransaction();
echo 'Test commitTransaction: ' . ($test['error'] !== true ? 'PASSED' : 'FAILED') . PHP_EOL;

