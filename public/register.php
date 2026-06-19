<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

if (Auth::check()) {
    header('Location: /index.php');
    exit;
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $phone = trim($_POST['phone'] ?? '');
        $companyName = trim($_POST['company_name'] ?? '');
        $taxId = trim($_POST['tax_id'] ?? '');
        $partnerType = $_POST['partner_type'] ?? '';

        if ($fullName === '' || $email === '' || $password === '' || $companyName === '') {
            $errors[] = 'Preenche todos os campos obrigatórios.';
        }

        if (strlen($password) < 8) {
            $errors[] = 'A password tem de ter pelo menos 8 caracteres.';
        }

        if (!in_array($partnerType, ['company', 'individual'], true)) {
            $errors[] = 'Tipo de parceiro inválido.';
        }

        if ($errors === []) {
            $pdo = Database::connection();

            $existsStmt = $pdo->prepare('SELECT id FROM users WHERE email = :email');
            $existsStmt->execute(['email' => $email]);

            if ($existsStmt->fetch()) {
                $errors[] = 'Já existe uma conta com este email.';
            } else {
                $pdo->beginTransaction();

                try {
                    $pdo->prepare(
                        "INSERT INTO users (email, password_hash, role, full_name, phone, status)
                         VALUES (:email, :password_hash, 'partner', :full_name, :phone, 'active')"
                    )->execute([
                        'email' => $email,
                        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                        'full_name' => $fullName,
                        'phone' => $phone !== '' ? $phone : null,
                    ]);
                    $userId = (int) $pdo->lastInsertId();

                    $pdo->prepare(
                        "INSERT INTO partners (user_id, company_name, tax_id, partner_type, status)
                         VALUES (:user_id, :company_name, :tax_id, :partner_type, 'pending_approval')"
                    )->execute([
                        'user_id' => $userId,
                        'company_name' => $companyName,
                        'tax_id' => $taxId !== '' ? $taxId : null,
                        'partner_type' => $partnerType,
                    ]);

                    $pdo->commit();
                    $success = true;
                } catch (Throwable $e) {
                    $pdo->rollBack();
                    throw $e;
                }
            }
        }
    }
}

$pageTitle = 'Criar conta de parceiro';
require __DIR__ . '/../views/header.php';
?>
    <div class="auth-container">
        <h1>Criar conta de parceiro</h1>

        <?php if ($success): ?>
            <p class="alert alert-success">Conta criada com sucesso. Fica pendente de aprovação por um administrador antes de poderes aceitar viagens.</p>
            <p><a href="/login.php">Ir para o login</a></p>
        <?php else: ?>
            <?php foreach ($errors as $error): ?>
                <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
            <?php endforeach; ?>

            <form method="post" action="/register.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

                <label>Nome completo <input type="text" name="full_name" required></label>
                <label>Email <input type="email" name="email" required></label>
                <label>Password (mín. 8 caracteres) <input type="password" name="password" minlength="8" required></label>
                <label>Telefone (opcional) <input type="text" name="phone"></label>
                <label>Nome da empresa <input type="text" name="company_name" required></label>
                <label>NIF (opcional) <input type="text" name="tax_id"></label>
                <label>Tipo
                    <select name="partner_type" required>
                        <option value="company">Empresa</option>
                        <option value="individual">Individual</option>
                    </select>
                </label>

                <button type="submit">Criar conta</button>
            </form>
            <p class="muted">Já tens conta? <a href="/login.php">Entrar</a></p>
        <?php endif; ?>
    </div>
<?php require __DIR__ . '/../views/footer.php'; ?>
