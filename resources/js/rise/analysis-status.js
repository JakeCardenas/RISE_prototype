// Keeps an AI request's waiting panel (x-ai-progress) live: the real stage from the server, a running clock, and a
// reload once the request settles, after which the result heading takes focus. If a status check fails, the panel says
// the status couldn't be refreshed and keeps trying; it never reports that the request itself failed.

const panel = document.querySelector('[data-analysis-waiting]');
const settledKey = 'rise-ai-settled';

if (panel) {
    const find = (selector) => panel.querySelector(selector);
    const steps = [...panel.querySelectorAll('[data-step]')];
    const order = steps.map((step) => step.dataset.step);
    const bar = find('[data-progress]');
    const clock = find('[data-elapsed-text]');
    const announcer = find('[data-stage-announcer]');
    const problem = find('[data-poll-problem]');
    const problemDetail = find('[data-poll-detail]');
    const stillWaiting = find('[data-still-waiting]');

    let stage = panel.dataset.stage;
    let elapsedAt = Number(panel.dataset.elapsed) || 0;
    let elapsedSince = performance.now();
    let checks = 0;
    let failures = 0;
    let timer = null;
    let stopped = false;

    const seconds = () => elapsedAt + Math.floor((performance.now() - elapsedSince) / 1000);

    const format = (total) => {
        const hours = Math.floor(total / 3600);
        const minutes = Math.floor((total % 3600) / 60);
        const rest = String(total % 60).padStart(2, '0');

        return hours ? `${hours}:${String(minutes).padStart(2, '0')}:${rest}` : `${minutes}:${rest}`;
    };

    const tick = () => {
        clock.textContent = format(seconds());
        // A request still in the queue after two minutes suggests no worker is running
        stillWaiting.hidden = !(stage === 'queued' && seconds() >= 120);
    };

    const showStage = (next) => {
        const index = order.indexOf(next);

        if (index === -1 || next === stage) {
            return;
        }

        stage = next;
        panel.dataset.stage = next;

        for (const [position, step] of steps.entries()) {
            const state = position < index ? 'done' : position === index ? 'current' : 'todo';
            step.dataset.stepState = state;
            step.querySelector('[data-step-sr]').textContent = { done: ', done', current: ', in progress', todo: '' }[state];
        }

        bar.setAttribute('aria-valuetext', steps[index].querySelector('[data-step-label]').textContent);
        announcer.textContent = steps[index].dataset.announce;
    };

    const schedule = (delay) => {
        window.clearTimeout(timer);

        if (!stopped && !document.hidden) {
            timer = window.setTimeout(check, delay);
        }
    };

    // Quick checks at first, then slower; after a failed check, back off up to 30 seconds
    const nextDelay = () => (failures ? Math.min(30000, 2000 * 2 ** failures) : checks < 30 ? 2000 : checks < 90 ? 5000 : 15000);

    const showProblem = (message) => {
        problemDetail.textContent = message;
        problem.hidden = false;
    };

    const check = async () => {
        checks += 1;

        try {
            const response = await fetch(panel.dataset.statusUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });

            if (response.status === 401 || response.status === 419) {
                stopped = true;
                showProblem('You’ve been signed out. Reload the page after signing in again; the analysis itself isn’t affected.');

                return;
            }

            if (!response.ok) {
                throw new Error(`Status check returned ${response.status}`);
            }

            const data = await response.json();

            failures = 0;
            problem.hidden = true;

            if (data.status !== 'pending' && data.status !== 'processing') {
                stopped = true;

                try {
                    window.sessionStorage.setItem(settledKey, window.location.pathname);
                } catch {
                    // Focus simply stays at the top of the page
                }

                window.location.reload();

                return;
            }

            if (Number.isFinite(data.elapsed)) {
                elapsedAt = data.elapsed;
                elapsedSince = performance.now();
            }

            showStage(data.stage);
        } catch {
            failures += 1;
            showProblem(`This page will try again in ${Math.round(nextDelay() / 1000)} seconds. The analysis itself may still be running.`);
        }

        schedule(nextDelay());
    };

    find('[data-poll-now]').addEventListener('click', () => {
        failures = 0;
        schedule(0);
    });

    // A hidden tab stops checking (schedule does nothing while hidden), and checks at once when it's shown again
    document.addEventListener('visibilitychange', () => schedule(0));

    tick();
    window.setInterval(tick, 1000);
    schedule(2000);
}

// After a reload that showed the result, move focus to the result's heading so screen readers read it
try {
    if (window.sessionStorage.getItem(settledKey) === window.location.pathname) {
        window.sessionStorage.removeItem(settledKey);
        const heading = document.querySelector('#result-heading, #ai-comparison-title');

        if (heading) {
            heading.setAttribute('tabindex', '-1');
            heading.focus({ preventScroll: false });
        }
    }
} catch {
    // Storage can be unavailable; the page works without it
}
