// Lets a researcher draw boxes on an evaluation image by dragging (mouse, pen or touch). Boxes are stored as
// [x, y, width, height] fractions of the image in a hidden field, and only for an image labelled crack-like.
// Without JavaScript the label form still works; boxes just can't be drawn.

const area = document.querySelector('[data-annotate]');

if (area) {
    const layer = area.querySelector('[data-annotate-layer]');
    const input = document.querySelector('[data-annotate-input]');
    const tools = document.querySelector('[data-annotate-tools]');
    const count = document.querySelector('[data-annotate-count]');
    const labels = document.querySelectorAll('[data-annotate-label]');
    const svg = 'http://www.w3.org/2000/svg';
    const minimum = 0.005;

    let boxes = [];
    let start = null;
    let drawing = null;

    try {
        boxes = JSON.parse(input.value || '[]').filter((box) => Array.isArray(box) && box.length === 4);
    } catch {
        boxes = [];
    }

    const crackLike = () => document.querySelector('[data-annotate-label]:checked')?.value === 'crack_like';
    const clamp = (value) => Math.min(1, Math.max(0, value));
    const round = (value) => Math.round(value * 100000) / 100000;

    const point = (event) => {
        const frame = area.getBoundingClientRect();

        return [clamp((event.clientX - frame.left) / frame.width), clamp((event.clientY - frame.top) / frame.height)];
    };

    const rect = ([x, y, w, h], className) => {
        const shape = document.createElementNS(svg, 'rect');

        for (const [name, value] of Object.entries({ x: x * 1000, y: y * 1000, width: w * 1000, height: h * 1000 })) {
            shape.setAttribute(name, String(value));
        }

        shape.setAttribute('class', className);
        shape.setAttribute('vector-effect', 'non-scaling-stroke');

        return shape;
    };

    const render = () => {
        layer.replaceChildren(...(crackLike() ? boxes : []).flatMap((box) => [rect(box, 'annotate-halo'), rect(box, 'annotate-box')]));
        input.value = JSON.stringify(crackLike() ? boxes : []);
        count.textContent = crackLike()
            ? `${boxes.length === 1 ? '1 box' : `${boxes.length} boxes`} drawn`
            : 'Boxes are kept only for an image labelled crack-like.';
        area.toggleAttribute('data-ready', crackLike());
    };

    area.addEventListener('pointerdown', (event) => {
        if (!crackLike() || event.button !== 0) {
            return;
        }

        area.setPointerCapture(event.pointerId);
        start = point(event);
        drawing = rect([...start, 0, 0], 'annotate-box');
        layer.append(drawing);
    });

    area.addEventListener('pointermove', (event) => {
        if (!start || !area.hasPointerCapture(event.pointerId)) {
            return;
        }

        const [x, y] = point(event);
        const box = [Math.min(start[0], x), Math.min(start[1], y), Math.abs(x - start[0]), Math.abs(y - start[1])];
        drawing.replaceWith((drawing = rect(box, 'annotate-box')));
    });

    const finish = (event) => {
        if (!start) {
            return;
        }

        const [x, y] = point(event);
        const box = [Math.min(start[0], x), Math.min(start[1], y), Math.abs(x - start[0]), Math.abs(y - start[1])].map(round);

        start = null;

        if (box[2] >= minimum && box[3] >= minimum && boxes.length < 20) {
            boxes.push(box);
        }

        render();
    };

    area.addEventListener('pointerup', finish);
    area.addEventListener('pointercancel', () => {
        start = null;
        render();
    });

    document.querySelector('[data-annotate-undo]').addEventListener('click', () => {
        boxes.pop();
        render();
    });

    document.querySelector('[data-annotate-clear]').addEventListener('click', () => {
        boxes = [];
        render();
    });

    for (const label of labels) {
        label.addEventListener('change', render);
    }

    tools.hidden = false;
    render();
}
