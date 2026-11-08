// Adds a before/after wipe and an overlay to the Compare page. Without JavaScript only the side-by-side view exists.
// The divider follows a drag anywhere on the photo (mouse, pen or touch) and a hidden range input,
// so arrow keys, Page Up/Down and Home/End move it too. The overlay fades the later photo with a range input.

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

const overlay = document.querySelector('[data-overlay]');

if (overlay) {
    const input = document.querySelector('[data-overlay-input]');
    const value = document.querySelector('[data-overlay-value]');

    document.querySelector('[data-compare-overlay]').hidden = false;

    const show = () => {
        overlay.style.setProperty('--overlay', String(input.valueAsNumber / 100));
        value.textContent = `${input.value}%`;
        input.setAttribute('aria-valuetext', `Later photo at ${input.value}% over the earlier photo`);
    };

    input.addEventListener('input', show);
    show();
}
