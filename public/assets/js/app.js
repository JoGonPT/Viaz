function closeDrawer() {
    var drawer = document.getElementById('drawer');
    var backdrop = document.getElementById('drawer-backdrop');

    if (drawer) {
        drawer.classList.add('translate-y-full');
    }

    if (backdrop) {
        backdrop.classList.add('opacity-0', 'pointer-events-none');
        backdrop.classList.remove('opacity-100');
    }
}

function openDrawer(templateId) {
    var template = document.getElementById(templateId);
    var drawerBody = document.getElementById('drawer-body');
    var drawer = document.getElementById('drawer');
    var backdrop = document.getElementById('drawer-backdrop');

    if (!template || !drawerBody || !drawer || !backdrop) {
        return;
    }

    drawerBody.innerHTML = '';
    drawerBody.appendChild(template.content.cloneNode(true));
    drawer.classList.remove('translate-y-full');
    backdrop.classList.remove('opacity-0', 'pointer-events-none');
    backdrop.classList.add('opacity-100');
}

document.addEventListener('click', function (event) {
    var opener = event.target.closest('[data-open-drawer]');

    if (opener) {
        openDrawer(opener.getAttribute('data-open-drawer') + '-content');
        return;
    }

    if (event.target.closest('[data-close-drawer]')) {
        closeDrawer();
    }
});

document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
        closeDrawer();
    }
});
