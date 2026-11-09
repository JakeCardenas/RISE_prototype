// AI requests send photos to an outside service and can cost money, so a form marked data-submit-once is sent once:
// its button shows the request is on its way and further presses do nothing. The server also refuses a second
// request for the same photo while one is running.

for (const form of document.querySelectorAll('form[data-submit-once]')) {
    form.addEventListener('submit', (event) => {
        if (form.dataset.sending) {
            event.preventDefault();

            return;
        }

        form.dataset.sending = 'true';

        for (const button of form.querySelectorAll('button[type="submit"]')) {
            button.dataset.label = button.innerHTML;
            button.setAttribute('aria-busy', 'true');
            button.setAttribute('aria-disabled', 'true');
            button.innerHTML = '<span class="ai-spinner ai-spinner-sm" aria-hidden="true"></span> Sending request…';
        }
    });
}

// Coming back with the browser's Back button shows the page as it was left, so the buttons are reset
window.addEventListener('pageshow', (event) => {
    if (!event.persisted) {
        return;
    }

    for (const form of document.querySelectorAll('form[data-submit-once][data-sending]')) {
        delete form.dataset.sending;

        for (const button of form.querySelectorAll('button[aria-busy="true"]')) {
            button.innerHTML = button.dataset.label;
            button.removeAttribute('aria-busy');
            button.removeAttribute('aria-disabled');
        }
    }
});
