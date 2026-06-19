<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/config.php';

use App\Auth;

Auth::requireLogin();

$rawPhone = trim($_GET['phone'] ?? '');
$contactName = trim($_GET['name'] ?? '') ?: 'Contacto';
$digitsPhone = preg_replace('/\D+/', '', $rawPhone) ?? '';

$pageTitle = $contactName;
require __DIR__ . '/../views/header-app.php';
?>
    <?php if ($digitsPhone === ''): ?>
        <p class="rounded-2xl bg-red-100 text-red-800 text-sm p-4">Não há um número de telefone associado a este contacto.</p>
        <p class="mt-3"><a href="/index.php" class="text-brand-600 text-sm font-medium">← Voltar</a></p>
    <?php else: ?>
        <div class="flex items-center gap-3 mb-4">
            <span class="h-11 w-11 rounded-full bg-emerald-100 text-emerald-700 flex items-center justify-center font-semibold text-lg">
                <?= htmlspecialchars(mb_strtoupper(mb_substr($contactName, 0, 1)), ENT_QUOTES) ?>
            </span>
            <div>
                <p class="text-sm font-semibold text-slate-800"><?= htmlspecialchars($contactName, ENT_QUOTES) ?></p>
                <p class="text-xs text-slate-400">As mensagens são enviadas através do WhatsApp</p>
            </div>
        </div>

        <div id="chat-thread" class="space-y-2 mb-28">
            <div class="bg-white border border-slate-200 rounded-2xl rounded-tl-sm px-4 py-2.5 max-w-[80%] text-sm text-slate-600">
                Esta é uma conversa simulada. Ao enviares uma mensagem, o WhatsApp abre numa nova janela com o texto já preenchido para <?= htmlspecialchars($contactName, ENT_QUOTES) ?>.
            </div>
        </div>

        <form id="chat-form"
              class="fixed inset-x-0 bottom-0 z-30 bg-white border-t border-slate-200 px-3 py-3 flex items-center gap-2"
              style="padding-bottom: calc(env(safe-area-inset-bottom) + 0.75rem);"
              data-phone="<?= htmlspecialchars($digitsPhone, ENT_QUOTES) ?>">
            <input type="text" id="chat-input" placeholder="Escreve uma mensagem…" autocomplete="off"
                   class="flex-1 rounded-full border border-slate-200 px-4 py-2.5 text-sm">
            <button type="submit"
                    class="h-11 w-11 rounded-full bg-emerald-600 text-white flex items-center justify-center shrink-0 active:bg-emerald-700 transition">
                ➤
            </button>
        </form>

        <script>
            (function () {
                var form = document.getElementById('chat-form');
                var input = document.getElementById('chat-input');
                var thread = document.getElementById('chat-thread');
                var phone = form.getAttribute('data-phone');

                form.addEventListener('submit', function (event) {
                    event.preventDefault();

                    var message = input.value.trim();
                    if (message === '') {
                        return;
                    }

                    var bubble = document.createElement('div');
                    bubble.className = 'bg-emerald-600 text-white rounded-2xl rounded-tr-sm px-4 py-2.5 max-w-[80%] text-sm ml-auto';
                    bubble.textContent = message;
                    thread.appendChild(bubble);
                    thread.scrollIntoView({ block: 'end' });

                    var url = 'https://wa.me/' + phone + '?text=' + encodeURIComponent(message);
                    window.open(url, '_blank');

                    input.value = '';
                });
            })();
        </script>
    <?php endif; ?>
</main>
</body>
</html>
