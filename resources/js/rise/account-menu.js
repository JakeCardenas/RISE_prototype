// Menus, sheets and delete confirmations are <details data-menu> elements: they close on Escape or a click
// elsewhere, as a native menu would, and a [data-dismiss] button inside closes its own one.

const menus = document.querySelectorAll('details[data-menu]');

const close = (menu, returnFocus) => {
    menu.open = false;

    if (returnFocus) {
        menu.querySelector('summary').focus();
    }
};

document.addEventListener('click', (event) => {
    for (const menu of menus) {
        if (menu.open && !menu.contains(event.target)) {
            close(menu, false);
        }
    }

    const dismiss = event.target.closest('[data-dismiss]');

    if (dismiss) {
        close(dismiss.closest('details'), true);
    }
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape') {
        return;
    }

    for (const menu of menus) {
        if (menu.open) {
            close(menu, menu.contains(document.activeElement));
        }
    }
});
