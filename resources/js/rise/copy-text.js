// "Copy wording" buttons copy their data-copy-text to the clipboard and say so. They stay hidden where the clipboard
// isn't available; the "Edit into my note" form works either way.
for (const button of document.querySelectorAll('[data-copy-text]')) {
    if (!navigator.clipboard) {
        continue;
    }

    const label = button.querySelector('[data-copy-label]');
    const text = label.textContent;
    button.hidden = false;

    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copyText);
            label.textContent = 'Copied';
            window.setTimeout(() => {
                label.textContent = text;
            }, 2000);
        } catch {
            label.textContent = 'Couldn’t copy';
        }
    });
}
