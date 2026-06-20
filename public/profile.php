<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;
use App\Csrf;
use App\Database;

Auth::requireLogin();

$pdo = Database::connection();

$userStmt = $pdo->prepare('SELECT id, full_name, email, phone, avatar_path, role FROM users WHERE id = :id');
$userStmt->execute(['id' => $_SESSION['user_id']]);
$user = $userStmt->fetch();

$partner = null;
if ($user['role'] === 'partner') {
    $partnerStmt = $pdo->prepare('SELECT id, company_name, tax_id, partner_type FROM partners WHERE user_id = :user_id');
    $partnerStmt->execute(['user_id' => $user['id']]);
    $partner = $partnerStmt->fetch();
}

$errors = [];
$success = false;

function save_uploaded_avatar(array $file, int $userId, array &$errors): ?string
{
    if ($file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'Não foi possível carregar a fotografia.';

        return null;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        $errors[] = 'A fotografia não pode exceder 2MB.';

        return null;
    }

    $imageInfo = @getimagesize($file['tmp_name']);
    $allowedTypes = [IMAGETYPE_JPEG => 'jpg', IMAGETYPE_PNG => 'png', IMAGETYPE_WEBP => 'webp'];

    if ($imageInfo === false || !isset($allowedTypes[$imageInfo[2]])) {
        $errors[] = 'A fotografia tem de ser um ficheiro JPG, PNG ou WEBP válido.';

        return null;
    }

    $uploadDir = __DIR__ . '/uploads/avatars';

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0755, true);
    }

    foreach (glob($uploadDir . '/user_' . $userId . '.*') as $existingFile) {
        unlink($existingFile);
    }

    $extension = $allowedTypes[$imageInfo[2]];
    $filename = 'user_' . $userId . '.' . $extension;

    if (!move_uploaded_file($file['tmp_name'], $uploadDir . '/' . $filename)) {
        $errors[] = 'Não foi possível guardar a fotografia.';

        return null;
    }

    return '/uploads/avatars/' . $filename;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Csrf::verify($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Sessão inválida, tente novamente.';
    } else {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');

        if ($fullName === '') {
            $errors[] = 'O nome não pode estar vazio.';
        }

        if ($partner !== null) {
            $companyName = trim($_POST['company_name'] ?? '');
            $taxId = trim($_POST['tax_id'] ?? '');
            $partnerType = $_POST['partner_type'] ?? '';

            if ($companyName === '') {
                $errors[] = 'O nome da empresa não pode estar vazio.';
            }

            if (!in_array($partnerType, ['company', 'individual'], true)) {
                $errors[] = 'Tipo de parceiro inválido.';
            }
        }

        $newAvatarPath = null;
        if ($errors === [] && isset($_FILES['avatar'])) {
            $newAvatarPath = save_uploaded_avatar($_FILES['avatar'], $user['id'], $errors);
        }

        if ($errors === []) {
            if ($newAvatarPath !== null) {
                $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone, avatar_path = :avatar_path WHERE id = :id')
                    ->execute([
                        'full_name' => $fullName,
                        'phone' => $phone !== '' ? $phone : null,
                        'avatar_path' => $newAvatarPath,
                        'id' => $user['id'],
                    ]);
            } else {
                $pdo->prepare('UPDATE users SET full_name = :full_name, phone = :phone WHERE id = :id')
                    ->execute([
                        'full_name' => $fullName,
                        'phone' => $phone !== '' ? $phone : null,
                        'id' => $user['id'],
                    ]);
            }

            $user['full_name'] = $fullName;
            $user['phone'] = $phone !== '' ? $phone : null;
            if ($newAvatarPath !== null) {
                $user['avatar_path'] = $newAvatarPath;
                $_SESSION['avatar_path'] = $newAvatarPath;
            }
            $_SESSION['full_name'] = $fullName;

            if ($partner !== null) {
                $pdo->prepare('UPDATE partners SET company_name = :company_name, tax_id = :tax_id, partner_type = :partner_type WHERE id = :id')
                    ->execute([
                        'company_name' => $companyName,
                        'tax_id' => $taxId !== '' ? $taxId : null,
                        'partner_type' => $partnerType,
                        'id' => $partner['id'],
                    ]);
                $partner['company_name'] = $companyName;
                $partner['tax_id'] = $taxId !== '' ? $taxId : null;
                $partner['partner_type'] = $partnerType;
            }

            $success = true;
        }
    }
}

$pageTitle = 'O meu perfil';
require __DIR__ . '/../views/header.php';
?>
    <h1>O meu perfil</h1>

    <?php if ($success): ?>
        <p class="alert alert-success">Perfil atualizado com sucesso.</p>
    <?php endif; ?>

    <?php foreach ($errors as $error): ?>
        <p class="alert alert-error"><?= htmlspecialchars($error, ENT_QUOTES) ?></p>
    <?php endforeach; ?>

    <?php if (empty($user['phone'])): ?>
        <p class="alert alert-warning">Sem telefone definido, o botão de WhatsApp não aparece nas viagens que crias.</p>
    <?php endif; ?>

    <?php if (!empty($user['avatar_path'])): ?>
        <img src="<?= htmlspecialchars($user['avatar_path'], ENT_QUOTES) ?>" alt="Foto de perfil"
             style="width:96px;height:96px;border-radius:50%;object-fit:cover;margin-bottom:16px;">
    <?php endif; ?>

    <form method="post" action="/profile.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(Csrf::token(), ENT_QUOTES) ?>">

        <label>Email <input type="email" value="<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>" disabled></label>
        <label>Nome completo <input type="text" name="full_name" value="<?= htmlspecialchars($user['full_name'], ENT_QUOTES) ?>" required></label>
        <label>Telefone (usado no botão de WhatsApp) <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '', ENT_QUOTES) ?>" placeholder="ex: 351912345678"></label>
        <label>Fotografia de perfil (JPG, PNG ou WEBP, máx. 2MB) <input type="file" name="avatar" accept="image/jpeg,image/png,image/webp"></label>

        <?php if ($partner !== null): ?>
            <h2>Dados da empresa</h2>
            <label>Nome da empresa <input type="text" name="company_name" value="<?= htmlspecialchars($partner['company_name'], ENT_QUOTES) ?>" required></label>
            <label>NIF <input type="text" name="tax_id" value="<?= htmlspecialchars($partner['tax_id'] ?? '', ENT_QUOTES) ?>"></label>
            <label>Tipo
                <select name="partner_type" required>
                    <option value="company" <?= $partner['partner_type'] === 'company' ? 'selected' : '' ?>>Empresa</option>
                    <option value="individual" <?= $partner['partner_type'] === 'individual' ? 'selected' : '' ?>>Individual</option>
                </select>
            </label>
        <?php endif; ?>

        <button type="submit">Guardar</button>
    </form>
<?php require __DIR__ . '/../views/footer.php'; ?>
