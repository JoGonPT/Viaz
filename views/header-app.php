<?php

declare(strict_types=1);

use App\Auth;

/** @var string $pageTitle */
/** @var string|null $activeTab */
$currentUser = Auth::check() ? Auth::user() : null;
$activeTab = $activeTab ?? '';
?>
<!DOCTYPE html>
<html lang="pt" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title><?= htmlspecialchars($pageTitle ?? 'Viaz', ENT_QUOTES) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#eef6f8', 100: '#d7eaef', 200: '#aed4df',
                            500: '#1d6f8c', 600: '#175a70', 700: '#124658'
                        }
                    }
                }
            }
        };
    </script>
</head>
<body class="h-full bg-slate-50 text-slate-800 pb-24 antialiased">
<header class="sticky top-0 z-30 bg-white/90 backdrop-blur border-b border-slate-200 px-4 py-3 flex items-center justify-between">
    <h1 class="text-base font-semibold text-brand-700"><?= htmlspecialchars($pageTitle ?? 'Viaz', ENT_QUOTES) ?></h1>
    <?php if ($currentUser !== null): ?>
        <span class="flex items-center gap-3 text-xs font-medium text-slate-400">
            <a href="/profile.php" class="active:text-slate-600">Perfil</a>
            <a href="/logout.php" class="active:text-slate-600">Sair</a>
        </span>
    <?php endif; ?>
</header>
<main class="px-3 pt-3 max-w-md mx-auto w-full">
