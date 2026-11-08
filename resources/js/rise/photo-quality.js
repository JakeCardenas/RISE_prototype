// Quick checks of a chosen photo before it is saved: does it look dark, washed out or glary, possibly blurred, and is
// its framing very different from the spot's baseline? They run in this browser on a small copy, nothing is sent
// anywhere, and they give plain advice rather than a score. They can be wrong either way, and the page says so.
// The thresholds were set on synthetic sample photos with added blur, darkness and glare; they are a rough guide.

const SIZE = 512;
const THUMB = 32;

const DARK = 60; // median light, 0–255
const WASHED_OUT = 240;
const GLARE = 0.03; // share of pixels at full brightness
const BLUR = 22; // edge strength (99.9th percentile of the Laplacian); sharp samples measured 170–190
const DIFFERENT_VIEW = 0.3; // correlation of small grey copies; the same view usually measures well above this
const FLAT = 6; // copies with less contrast than this can't be compared this way
const LIGHT = 45; // difference in average light

const advice = {
    dark: 'It looks dark, so faint lines may not show. Add light, such as daylight or a lamp, and try again.',
    washedOut: 'It looks very bright or washed out, so faint lines may not show.',
    glare: 'Part of it looks very bright, which may be glare or a reflection. Try a slightly different angle, or turn off the flash.',
    blur: 'It may be out of focus or blurred by movement. Hold the camera steady and let it focus before you take the photo.',
    orientation: (now, then) => `It’s ${now} but your baseline is ${then}. Hold the camera the same way as for the baseline.`,
    shape: 'Its shape is different from your baseline’s, so the framing won’t line up exactly. Use the same camera setting as before if you can.',
    view: 'It may show a different area, or be taken from a different angle or distance than your baseline. Line it up with the overlay of your last photo below.',
    light: 'The light looks quite different from your baseline, which can make the surface look different even when nothing has changed.',
};

// Light (0–255) of each pixel of the photo drawn at the given size; stretch ignores the photo's shape
function sample(image, size, stretch = false) {
    const scale = Math.min(1, size / Math.max(image.naturalWidth, image.naturalHeight));
    const width = stretch ? size : Math.max(1, Math.round(image.naturalWidth * scale));
    const height = stretch ? size : Math.max(1, Math.round(image.naturalHeight * scale));
    const canvas = Object.assign(document.createElement('canvas'), { width, height });
    const context = canvas.getContext('2d', { willReadFrequently: true });

    context.imageSmoothingQuality = 'high';
    context.drawImage(image, 0, 0, width, height);

    const { data } = context.getImageData(0, 0, width, height);
    const light = new Float32Array(width * height);

    for (let i = 0; i < light.length; i++) {
        light[i] = 0.2126 * data[i * 4] + 0.7152 * data[i * 4 + 1] + 0.0722 * data[i * 4 + 2];
    }

    return { light, width, height };
}

function percentile(values, fraction) {
    const sorted = Float32Array.from(values).sort();

    return sorted[Math.min(sorted.length - 1, Math.floor(sorted.length * fraction))];
}

// How sharp the strongest edges are: a crack, a corner or a texture all count
function edgeStrength({ light, width, height }) {
    const edges = [];

    for (let y = 1; y < height - 1; y++) {
        for (let x = 1; x < width - 1; x++) {
            const i = y * width + x;
            edges.push(Math.abs(4 * light[i] - light[i - 1] - light[i + 1] - light[i - width] - light[i + width]));
        }
    }

    return edges.length ? percentile(edges, 0.999) : 0;
}

function stats(values) {
    const mean = values.reduce((sum, value) => sum + value, 0) / values.length;
    const deviation = Math.sqrt(values.reduce((sum, value) => sum + (value - mean) ** 2, 0) / values.length);

    return { mean, deviation };
}

function framing(image, baseline) {
    const issues = [];
    const ratio = image.naturalWidth / image.naturalHeight;
    const baseRatio = baseline.naturalWidth / baseline.naturalHeight;
    const shape = (value) => (value > 1 ? 'landscape' : 'portrait');

    if ((ratio > 1.05 && baseRatio < 0.95) || (ratio < 0.95 && baseRatio > 1.05)) {
        return [advice.orientation(shape(ratio), shape(baseRatio))];
    }

    if (Math.abs(Math.log(ratio / baseRatio)) > 0.12) {
        issues.push(advice.shape);
    }

    const now = sample(image, THUMB, true).light;
    const then = sample(baseline, THUMB, true).light;
    const a = stats(now);
    const b = stats(then);

    if (a.deviation > FLAT && b.deviation > FLAT) {
        let correlation = 0;

        for (let i = 0; i < now.length; i++) {
            correlation += ((now[i] - a.mean) / a.deviation) * ((then[i] - b.mean) / b.deviation);
        }

        if (correlation / now.length < DIFFERENT_VIEW) {
            issues.push(advice.view);
        }
    }

    if (Math.abs(a.mean - b.mean) > LIGHT) {
        issues.push(advice.light);
    }

    return issues;
}

/**
 * @param {HTMLImageElement} image  The chosen photo, already loaded
 * @param {HTMLImageElement|null} baseline  The spot's baseline photo, for follow-ups
 * @returns {string[]} Plain advice, one sentence per possible problem; empty when nothing obvious was found
 */
export function checkPhoto(image, baseline = null) {
    const photo = sample(image, SIZE);
    const median = percentile(photo.light, 0.5);
    const glare = photo.light.filter((value) => value >= 250).length / photo.light.length;
    const issues = [];

    if (median < DARK) {
        issues.push(advice.dark);
    } else if (median > WASHED_OUT) {
        issues.push(advice.washedOut);
    } else if (glare > GLARE) {
        issues.push(advice.glare);
    }

    // A dark photo has soft edges anyway, so it isn't also called blurred
    if (median >= DARK && edgeStrength(photo) < BLUR) {
        issues.push(advice.blur);
    }

    return baseline ? [...issues, ...framing(image, baseline)] : issues;
}
