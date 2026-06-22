<?php
require_once __DIR__ . '/backend/bootstrap.php';
$modules = \Illuminate\Database\Capsule\Manager::table('modules')->get();
echo json_encode($modules, JSON_PRETTY_PRINT);
