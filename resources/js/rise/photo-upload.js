import { formatLocal } from './local-time';

// Enhances the Add photo form: a drop zone with "Choose photo" and (on touch screens) "Take photo",
// a preview to review before saving (for a follow-up, with the last photo faded over it to check the framing),
// checks before uploading, and upload progress. The last photo of the chosen point is shown as a framing guide:
// full size beside the steps on wide screens, and as a small guide above the picker on phones.
// Without JavaScript it stays a normal form post with a standard file control.

const form = document.querySelector('[data-photo-form]');

if (form) {
    enhance(form);
}

function enhance(form) {
    const find = (selector) => form.querySelector(selector);

    const maxBytes = Number(form.dataset.maxBytes);
    const maxLabel = form.dataset.maxLabel;
    const formatLabel = form.dataset.formatLabel;
    const heicSupported = form.dataset.heic === 'true';
    const webTypes = ['image/jpeg', 'image/png', 'image/webp'];

    const pointSelect = find('[data-point-select]');
    const nativePicker = find('[data-native-picker]');
    const photoInput = find('[data-photo-input]');
    const cameraInput = find('[data-camera-input]');
    const dropzone = find('[data-dropzone]');
    const cameraButton = find('[data-camera-button]');
    const libraryButton = find('[data-library-button]');
    const preview = find('[data-preview]');
    const previewFrame = find('[data-preview-frame]');
    const previewImage = find('[data-preview-image]');
    const heicNotice = find('[data-heic-notice]');
    const detail = (name) => find(`[data-detail="${name}"]`);
    const changeButton = find('[data-change-photo]');
    const noteField = find('#note');
    const references = form.querySelectorAll('[data-reference]');
    const checklist = find('[data-checklist]');
    const modeNote = find('[data-mode-note]');
    const pageTitle = document.querySelector('[data-page-title]');
    const ghost = find('[data-ghost]');
    const ghostControls = find('[data-ghost-controls]');
    const ghostRange = find('[data-ghost-range]');
    const ghostValue = find('[data-ghost-value]');
    const status = find('[data-upload-status]');
    const statusText = find('[data-upload-text]');
    const statusPercent = find('[data-upload-percent]');
    const progress = find('[data-upload-progress]');
    const failure = find('[data-upload-failure]');
    const submitButton = find('[data-submit]');

    let selectedFile = null;
    let previewUrl = null;
    let previewShown = false;

    // Swap the plain file control for the drop zone; offer the camera shortcut only on touch screens
    nativePicker.classList.add('sr-only');
    nativePicker.setAttribute('aria-hidden', 'true');
    photoInput.tabIndex = -1;
    dropzone.hidden = false;
    cameraButton.hidden = !window.matchMedia('(pointer: coarse)').matches;

    cameraButton.addEventListener('click', () => cameraInput.click());
    libraryButton.addEventListener('click', () => photoInput.click());
    cameraInput.addEventListener('change', () => chooseFrom(cameraInput));
    photoInput.addEventListener('change', () => chooseFrom(photoInput));

    changeButton.addEventListener('click', () => {
        clearSelection();
        pickerButton().focus();
    });

    // Drag and drop is an extra way in; the buttons always work too
    const holdsFiles = (event) => Array.from(event.dataTransfer?.types ?? []).includes('Files');

    for (const type of ['dragenter', 'dragover']) {
        dropzone.addEventListener(type, (event) => {
            if (holdsFiles(event)) {
                event.preventDefault();
                event.dataTransfer.dropEffect = 'copy';
                dropzone.dataset.dragging = '';
            }
        });
    }

    dropzone.addEventListener('dragleave', (event) => {
        if (!dropzone.contains(event.relatedTarget)) {
            delete dropzone.dataset.dragging;
        }
    });

    dropzone.addEventListener('drop', (event) => {
        event.preventDefault();
        delete dropzone.dataset.dragging;
        choose(event.dataTransfer.files[0], event.dataTransfer.files.length > 1);
    });

    // A photo dropped beside the zone would otherwise replace the page
    for (const type of ['dragover', 'drop']) {
        window.addEventListener(type, (event) => {
            if (holdsFiles(event) && !dropzone.contains(event.target)) {
                event.preventDefault();
            }
        });
    }

    pointSelect.addEventListener('change', () => {
        clearError('monitoring_point_id');
        showPoint();
    });

    ghostRange.addEventListener('input', showGhostOpacity);

    form.addEventListener('submit', (event) => {
        event.preventDefault();
        upload();
    });

    // The photo checklist starts open where there's room for it beside the picker
    if (window.matchMedia('(min-width: 64rem)').matches) {
        checklist.open = true;
    }

    showGhostOpacity();
    showPoint();

    function chooseFrom(input) {
        const file = input.files?.[0];

        // Reset so choosing the same file again still fires a change event
        input.value = '';
        choose(file, false);
    }

    function choose(file, severalDropped) {
        if (!file) {
            return;
        }

        clearError('photo');
        failure.hidden = true;

        const problem = check(file);

        if (problem) {
            showError('photo', problem);
            pickerButton().focus();

            return;
        }

        showPreview(file, severalDropped);
    }

    function isHeic(file) {
        return ['image/heic', 'image/heif'].includes(file.type) || /\.(heic|heif)$/i.test(file.name);
    }

    function check(file) {
        if (isHeic(file) && !heicSupported) {
            return 'This server can’t convert HEIC photos. On a Mac, open the photo in Photos, choose File › Export › Export 1 Photo, pick JPEG, then add the exported file.';
        }

        if (!isHeic(file) && file.type && !webTypes.includes(file.type)) {
            return `Use a ${formatLabel} photo.`;
        }

        if (file.size > maxBytes) {
            return `This photo is ${formatSize(file.size)}, which is over the ${maxLabel} limit. Choose a smaller photo.`;
        }

        return null;
    }

    function showPreview(file, severalDropped) {
        const url = URL.createObjectURL(file);
        const heic = isHeic(file);

        const ready = (canPreview) => {
            if (previewUrl) {
                URL.revokeObjectURL(previewUrl);
            }

            previewUrl = url;
            selectedFile = file;

            // The details block: rows without a value are hidden rather than left blank. The file's modified date isn't
            // shown or sent: it can come from exporting or copying, not from taking the photo.
            const details = {
                type: typeName(file),
                size: formatSize(file.size) + (severalDropped ? ' (only the first dropped photo is used)' : ''),
                pixels: canPreview ? `${previewImage.naturalWidth} × ${previewImage.naturalHeight}` : '',
            };

            for (const [name, value] of Object.entries(details)) {
                detail(name).textContent = value;
                detail(name).closest('[data-detail-row]').hidden = !value;
            }
            previewFrame.hidden = !canPreview;
            heicNotice.hidden = !heic;
            previewShown = canPreview;
            showGhost();

            dropzone.hidden = true;
            preview.hidden = false;
            preview.focus();
        };

        previewImage.onload = () => ready(true);

        previewImage.onerror = () => {
            if (heic) {
                // Most browsers other than Safari can't draw HEIC; the server converts it after upload
                ready(false);

                return;
            }

            URL.revokeObjectURL(url);
            showError('photo', `This file can’t be shown as a photo. Use a ${formatLabel} photo.`);
            pickerButton().focus();
        };

        previewImage.src = url;
    }

    function clearSelection() {
        if (previewUrl) {
            URL.revokeObjectURL(previewUrl);
        }

        previewUrl = null;
        selectedFile = null;
        previewShown = false;
        previewImage.removeAttribute('src');
        preview.hidden = true;
        dropzone.hidden = false;
        showGhost();
    }

    // A point with photos takes a follow-up; a point without one takes its baseline
    function showPoint() {
        const option = pointSelect.selectedOptions[0];
        const mode = option?.value ? (option.dataset.referenceSrc ? 'follow-up' : 'baseline') : '';
        const heading = { 'follow-up': 'Add follow-up photo', baseline: 'Add baseline photo' }[mode] ?? 'Add photo';
        const strong = (text) => Object.assign(document.createElement('strong'), { className: 'font-semibold text-ink', textContent: text });

        for (const block of form.querySelectorAll('[data-for-mode]')) {
            block.hidden = block.dataset.forMode !== mode;
        }

        pageTitle.textContent = heading;
        document.title = `${heading} · RISE`;
        modeNote.replaceChildren(...{
            'follow-up': ['This photo will be saved as ', strong(option?.dataset.nextLabel ?? 'a follow-up'), ' for this spot.'],
            baseline: ['This spot has no photos yet, so this one becomes its ', strong('baseline'), '.'],
        }[mode] ?? []);

        // A photo saved once the planned day has arrived completes that follow-up
        if (option?.dataset.plannedNote) {
            modeNote.append(' ', option.dataset.plannedNote);
        }

        showReference();
        showGhost();
    }

    // The previous photo can be faded over a new one only when both can be shown in this browser
    function showGhost() {
        const source = pointSelect.selectedOptions[0]?.dataset.referenceSrc;
        const available = Boolean(source && selectedFile && previewShown);

        if (available && ghost.getAttribute('src') !== source) {
            ghost.src = source;
        }

        ghost.hidden = !available;
        ghostControls.hidden = !available;
    }

    function showGhostOpacity() {
        const percent = ghostRange.valueAsNumber;

        ghost.style.opacity = String(percent / 100);
        ghostValue.textContent = `${percent}%`;
        ghostRange.setAttribute('aria-valuetext', `${percent}% previous photo`);
    }

    // Shows the point's most recent photo so the resident can line up the same view
    function showReference() {
        const option = pointSelect.selectedOptions[0];
        const source = option?.dataset.referenceSrc;

        for (const reference of references) {
            reference.hidden = !source;

            if (!source) {
                continue;
            }

            for (const image of reference.querySelectorAll('img')) {
                image.src = source;
            }

            for (const time of reference.querySelectorAll('time')) {
                time.dateTime = option.dataset.referenceDate;
                time.textContent = formatLocal(option.dataset.referenceDate, time.dataset.format ?? 'date');
            }
        }
    }

    function upload() {
        for (const field of ['monitoring_point_id', 'photo', 'note']) {
            clearError(field);
        }

        failure.hidden = true;

        let firstProblem = null;

        if (!pointSelect.value) {
            showError('monitoring_point_id', 'Choose a spot for this photo.');
            firstProblem = pointSelect;
        }

        if (!selectedFile) {
            showError('photo', 'Take or choose a photo to save.');
            firstProblem ??= pickerButton();
        }

        if (firstProblem) {
            firstProblem.focus();

            return;
        }

        const data = new FormData(form);
        data.set('photo', selectedFile, selectedFile.name || 'photo.jpg');

        const request = new XMLHttpRequest();
        request.open('POST', form.action);
        request.responseType = 'json';
        request.setRequestHeader('Accept', 'application/json');
        request.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

        request.upload.addEventListener('progress', (event) => {
            if (event.lengthComputable) {
                setProgress('Uploading photo…', Math.round((event.loaded / event.total) * 100));
            }
        });
        // The server converts HEIC photos after the upload finishes
        request.upload.addEventListener('load', () => setProgress(isHeic(selectedFile) ? 'Converting HEIC photo to JPEG…' : 'Saving photo…', 100));
        request.addEventListener('load', () => finish(request));
        request.addEventListener('error', () => fail('The upload didn’t finish. Check your connection and try again. Your photo hasn’t been saved yet.'));
        request.addEventListener('abort', () => fail('The upload was cancelled. Your photo hasn’t been saved.'));

        setBusy(true);
        setProgress('Uploading photo…', 0);
        request.send(data);
    }

    function finish(request) {
        const body = request.response ?? {};

        if (request.status === 201 && body.redirect) {
            setProgress('Photo saved. Opening the photo history…', 100);
            window.location.assign(body.redirect);

            return;
        }

        setBusy(false);
        status.hidden = true;

        if (request.status === 422 && body.errors) {
            for (const [field, messages] of Object.entries(body.errors)) {
                if (!showError(field, messages[0])) {
                    fail(messages[0]);
                }
            }

            const firstField = Object.keys(body.errors).find((field) => controlFor(field));
            controlFor(firstField)?.focus();

            return;
        }

        if (request.status === 413) {
            showError('photo', `This photo is over the ${maxLabel} upload limit. Choose a smaller photo.`);
            pickerButton().focus();

            return;
        }

        if (request.status === 401 || request.status === 419) {
            fail('Your session has ended. Reload the page, sign in if asked, and add the photo again.');

            return;
        }

        fail('Something went wrong and the photo wasn’t saved. Please try again.');
    }

    function setBusy(busy) {
        form.setAttribute('aria-busy', String(busy));
        submitButton.querySelector('[data-submit-label]').textContent = busy ? 'Saving…' : 'Save photo';

        for (const control of [submitButton, pointSelect, changeButton, cameraButton, libraryButton, noteField]) {
            control.disabled = busy;
        }
    }

    function setProgress(text, percent) {
        status.hidden = false;
        statusText.textContent = text;
        statusPercent.textContent = `${percent}%`;
        progress.value = percent;
    }

    function fail(message) {
        setBusy(false);
        status.hidden = true;
        failure.querySelector('[data-failure-text]').textContent = message;
        failure.hidden = false;
    }

    function showError(field, message) {
        const container = find(`[data-error-for="${field}"]`);

        if (!container) {
            return false;
        }

        container.querySelector('[data-error-text]').textContent = message;
        container.hidden = false;
        controlFor(field)?.setAttribute('aria-invalid', 'true');

        return true;
    }

    function clearError(field) {
        const container = find(`[data-error-for="${field}"]`);

        if (container) {
            container.hidden = true;
            container.querySelector('[data-error-text]').textContent = '';
        }

        controlFor(field)?.removeAttribute('aria-invalid');
    }

    function controlFor(field) {
        return { monitoring_point_id: pointSelect, photo: preview.hidden ? pickerButton() : changeButton, note: noteField }[field];
    }

    function pickerButton() {
        return cameraButton.hidden ? libraryButton : cameraButton;
    }
}

function formatSize(bytes) {
    return bytes >= 1024 * 1024 ? `${(bytes / (1024 * 1024)).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`;
}

function typeName(file) {
    return { 'image/jpeg': 'JPEG', 'image/png': 'PNG', 'image/webp': 'WebP', 'image/heic': 'HEIC', 'image/heif': 'HEIF' }[file.type]
        ?? (/\.(heic|heif)$/i.test(file.name) ? 'HEIC' : 'Photo');
}
