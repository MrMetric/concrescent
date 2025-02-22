<?php

require_once __DIR__ . '/../../config/config.php';
error_reporting(0);
header('Content-Type: text/plain');

try {
	$host = $cm_config['database']['host'];
	$dbname = $cm_config['database']['database'];
	$connection = new PDO(
		"mysql:host=$host;dbname=$dbname",
		$cm_config['database']['username'],
		$cm_config['database']['password']
	);
} catch (PDOException $e) {
	echo 'NG Could not connect to database: ' . $e->getMessage();
	if ($e->getCode() === 2002) {
		die('. Check if the service is running.');
	}
	die();
}

$query = $connection->query('SELECT 6*7');
if (!$query) {
	die('NG Connection to database is not working. Check database configuration.');
}

$row = $query->fetch(PDO::FETCH_NUM);
if (!$row) {
	die('NG Connection to database is not working. Check database configuration.');
}

$answer = $row[0];
if ($answer != 42) {
	die('NG Connection to database is not working. Check database configuration.');
}

$query->closeCursor();
echo 'OK Successfully connected to database.';
