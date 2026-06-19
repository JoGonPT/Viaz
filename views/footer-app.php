</main>

<?php if ($currentUser !== null && $currentUser['role'] === 'partner'): ?>
    <a href="/new-trip.php"
       class="fixed right-4 bottom-20 z-40 h-12 w-12 rounded-full bg-brand-600 text-white text-2xl leading-none flex items-center justify-center shadow-lg active:scale-95 transition">
        +
    </a>

    <nav class="fixed bottom-0 inset-x-0 z-30 bg-white border-t border-slate-200 flex justify-around"
         style="padding-bottom: env(safe-area-inset-bottom);">
        <a href="/mural.php" class="flex-1 flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium <?= $activeTab === 'mural' ? 'text-brand-600' : 'text-slate-400' ?>">
            <span class="text-lg">🧭</span>Mural
        </a>
        <a href="/my-trips.php" class="flex-1 flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium <?= $activeTab === 'trips' ? 'text-brand-600' : 'text-slate-400' ?>">
            <span class="text-lg">🚐</span>Viagens
        </a>
        <a href="/calendar.php" class="flex-1 flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium <?= $activeTab === 'calendar' ? 'text-brand-600' : 'text-slate-400' ?>">
            <span class="text-lg">📅</span>Calendário
        </a>
        <a href="/my-vehicles.php" class="flex-1 flex flex-col items-center gap-0.5 py-2 text-[11px] font-medium <?= $activeTab === 'vehicles' ? 'text-brand-600' : 'text-slate-400' ?>">
            <span class="text-lg">🚙</span>Viaturas
        </a>
    </nav>
<?php endif; ?>

<div id="drawer-backdrop"
     class="fixed inset-0 bg-black/40 z-40 opacity-0 pointer-events-none transition-opacity duration-300"
     data-close-drawer></div>

<div id="drawer"
     class="fixed inset-x-0 bottom-0 z-50 translate-y-full transition-transform duration-300 ease-out bg-white rounded-t-2xl shadow-xl max-h-[85vh] overflow-y-auto">
    <button type="button" class="w-full flex justify-center py-2" data-close-drawer aria-label="Fechar">
        <span class="h-1.5 w-10 rounded-full bg-slate-300"></span>
    </button>
    <div class="px-4 pb-8" id="drawer-body"></div>
</div>

<script src="/assets/js/app.js"></script>
</body>
</html>
