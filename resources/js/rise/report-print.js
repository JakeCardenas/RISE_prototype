// The observation report's button opens the browser's print dialog, which also offers "Save as PDF".
// Without JavaScript the page keeps its plain hint to use the browser's Print command.

const button = document.querySelector('[data-print]');

if (button) {
    button.hidden = false;
    document.querySelector('[data-print-hint]').hidden = false;
    document.querySelector('[data-print-fallback]').hidden = true;
    button.addEventListener('click', () => window.print());
}
