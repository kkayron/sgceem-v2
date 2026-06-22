<?php
require_once __DIR__ . '/backend/bootstrap.php';
\Illuminate\Database\Capsule\Manager::table('modules')->where('id', 4)->update(['path' => 'os_principal']);
echo "Banco corrigido!\n";
