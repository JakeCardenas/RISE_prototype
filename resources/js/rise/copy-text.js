// "Copy wording" buttons copy their data-copy-text to the clipboard and say so, on the button and to screen readers
// (through the status line beside it). They stay hidden where the clipboard isn't available; the "Edit into my note"
// form works either way.
for (const button of document.querySelectorAll('[data-copy-text]')) {
    if (!navigator.clipboard) {
        continue;
    }

    const label = button.querySelector('[data-copy-label]');
    const status = button.parentElement.querySelector('[data-copy-status]');
    const text = label.textContent;
    let timer = null;
    button.hidden = false;

    const say = (message) => {
        label.textContent = message;

        if (status) {
            status.textContent = message === 'Copied' ? 'Wording copied' : 'Couldn’t copy the wording';
        }

        window.clearTimeout(timer);
        timer = window.setTimeout(() => {
            label.textContent = text;
        }, 2000);
    };

    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copyText);
            say('Copied');
        } catch {
            say('Couldn’t copy');
        }
    });
}
