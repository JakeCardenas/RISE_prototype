// The eye button in a password field shows what was typed, so a resident can check it before sending. It appears only
// when this script runs. Pressing it leaves the cursor in the field, so typing carries on and a phone's keyboard stays
// open. The field goes back to dots before the form is sent or the page is left, so the browser's password manager
// still sees a password field and the Back button never brings a shown password back on screen.

for (const button of document.querySelectorAll('[data-password-toggle]')) {
    const field = document.getElementById(button.getAttribute('aria-controls'));

    if (!field) {
        continue;
    }

    const show = (visible) => {
        const { selectionStart, selectionEnd } = field;

        field.type = visible ? 'text' : 'password';
        button.setAttribute('aria-pressed', String(visible));

        if (document.activeElement === field) {
            field.setSelectionRange(selectionStart, selectionEnd);
        }
    };

    button.hidden = false;
    button.addEventListener('mousedown', (event) => event.preventDefault());
    button.addEventListener('click', () => show(field.type === 'password'));
    field.form?.addEventListener('submit', () => show(false));
    window.addEventListener('pagehide', () => show(false));
}
