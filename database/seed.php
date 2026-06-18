<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Database;

$email = $argv[1] ?? null;
$password = $argv[2] ?? null;
$fullName = $argv[3] ?? 'Administrador';

if (!$email || !$password) {
    fwrite(STDERR, "Uso: php database/seed.php <email> <password> [\"Nome Completo\"]\n");
    exit(1);
}

$pdo = Database::connection();

$stmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
$stmt->execute(['email' => $email]);

if ($stmt->fetch()) {
    fwrite(STDERR, "Já existe um utilizador com este email.\n");
    exit(1);
}

$pdo->prepare(
    'INSERT INTO users (email, password_hash, role, full_name, status)
     VALUES (:email, :password_hash, \'admin\', :full_name, \'active\')'
)->execute([
    'email' => $email,
    'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    'full_name' => $fullName,
]);

echo "Utilizador admin '{$email}' criado com sucesso.\n";
