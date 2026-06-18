<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;

Auth::requireLogin();

$user = Auth::user();
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title>Painel</title>
</head>
<body>
    <h1>Bem-vindo, <?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?></h1>
    <p>Perfil: <?= htmlspecialchars($user['role'], ENT_QUOTES) ?></p>
    <a href="/logout.php">Sair</a>
</body>
</html>
