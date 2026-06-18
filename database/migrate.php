<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/env.php';
load_env(__DIR__ . '/../.env');

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$name = getenv('DB_NAME') ?: '';
$user = getenv('DB_USER') ?: '';
$pass = getenv('DB_PASS') ?: '';

if ($name === '') {
    fwrite(STDERR, "DB_NAME não definido. Copia .env.example para .env e configura as credenciais.\n");
    exit(1);
}

$pdo = new PDO("mysql:host={$host};port={$port};charset=utf8mb4", $user, $pass, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
]);

$pdo->exec("CREATE DATABASE IF NOT EXISTS `{$name}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
$pdo->exec("USE `{$name}`");

$sql = file_get_contents(__DIR__ . '/schema.sql');

foreach (explode(";\n", $sql) as $statement) {
    $statement = trim($statement);

    if ($statement === '') {
        continue;
    }

    $pdo->exec($statement);
}

echo "Base de dados '{$name}' criada/atualizada com sucesso.\n";
