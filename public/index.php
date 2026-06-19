<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;

Auth::requireLogin();

$user = Auth::user();

$pageTitle = 'Painel';
require __DIR__ . '/../views/header.php';
?>
    <h1>Bem-vindo, <?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?></h1>
    <p class="muted">Perfil: <?= htmlspecialchars($user['role'], ENT_QUOTES) ?></p>

    <?php if ($user['role'] === 'partner'): ?>
        <div class="card">
            <h2 style="margin-top:0;">Parceiro</h2>
            <p><a href="/mural.php">Mural de viagens</a></p>
            <p><a href="/my-trips.php">As minhas viagens</a></p>
            <p><a href="/my-vehicles.php">As minhas viaturas</a></p>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'admin'): ?>
        <div class="card">
            <h2 style="margin-top:0;">Administração</h2>
            <p><a href="/private-trip-new.php">Criar envio privado</a></p>
            <p><a href="/groups.php">Grupos de serviço (desdobramento)</a></p>
            <p><a href="/partners.php">Gerir parceiros</a></p>
            <p><a href="/password-reset-requests.php">Pedidos de recuperação de password</a></p>
        </div>
    <?php endif; ?>

    <?php if ($user['role'] === 'client'): ?>
        <div class="card">
            <h2 style="margin-top:0;">Cliente</h2>
            <p><a href="/my-requests.php">Os meus pedidos de transporte</a></p>
        </div>
    <?php endif; ?>
<?php require __DIR__ . '/../views/footer.php'; ?>
