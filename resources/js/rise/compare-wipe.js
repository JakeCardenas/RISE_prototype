// Adds a before/after wipe to the Compare page. Without JavaScript only the side-by-side view exists.
// The divider follows a drag anywhere on the photo (mouse, pen or touch) and a hidden range input,
// so arrow keys, Page Up/Down and Home/End move it too.

const wipe = document.querySelector('[data-wipe]');

if (wipe) {
    const input = wipe.querySelector('[data-wipe-input]');

    document.querySelector('[data-compare-switch]').hidden = false;
    document.querySelector('[data-compare-wipe]').hidden = false;

    const set = (value) => {
        const position = Math.min(100, Math.max(0, Math.round(value)));

        input.value = position;
        input.setAttribute('aria-valuetext', `${position}% earlier photo, ${100 - position}% later photo`);
        wipe.style.setProperty('--wipe', `${position}%`);
    };

    const follow = (event) => {
        const box = wipe.getBoundingClientRect();
        set(((event.clientX - box.left) / box.width) * 100);
    };

    input.addEventListener('input', () => set(input.valueAsNumber));

    wipe.addEventListener('pointerdown', (event) => {
        wipe.setPointerCapture(event.pointerId);
        follow(event);
        input.focus({ preventScroll: true });
    });

    wipe.addEventListener('pointermove', (event) => {
        if (wipe.hasPointerCapture(event.pointerId)) {
            follow(event);
        }
    });

    set(50);
}
