<?php
require_once 'db.php';

header('Content-Type: application/json');

$db = new Database();
$packages = $db->getPackages();

echo json_encode($packages);
?>
