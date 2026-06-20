<?php

declare(strict_types=1);

use App\Auth;

/** @var string $pageTitle */
$currentUser = Auth::check() ? Auth::user() : null;
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($pageTitle ?? 'Viaz', ENT_QUOTES) ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<?php if ($currentUser !== null): ?>
    <header class="topbar">
        <div class="topbar-inner">
            <a class="brand" href="/index.php">Viaz</a>
            <nav>
                <?php if ($currentUser['role'] === 'partner'): ?>
                    <a href="/mural.php">Mural</a>
                    <a href="/my-trips.php">As minhas viagens</a>
                    <a href="/calendar.php">Calendário</a>
                    <a href="/my-vehicles.php">As minhas viaturas</a>
                    <a href="/new-trip.php">Criar viagem</a>
                <?php endif; ?>
                <?php if ($currentUser['role'] === 'admin'): ?>
                    <a href="/private-trip-new.php">Envio privado</a>
                    <a href="/groups.php">Grupos</a>
                    <a href="/partners.php">Parceiros</a>
                    <a href="/password-reset-requests.php">Recuperações de password</a>
                <?php endif; ?>
                <?php if ($currentUser['role'] === 'client'): ?>
                    <a href="/my-requests.php">Os meus pedidos</a>
                <?php endif; ?>
            </nav>
            <div class="topbar-user">
                <a href="/profile.php"><?= htmlspecialchars($currentUser['full_name'], ENT_QUOTES) ?></a>
                <a href="/logout.php">Sair</a>
            </div>
        </div>
    </header>
<?php endif; ?>
<main class="container">
