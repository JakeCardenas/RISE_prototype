// While an AI analysis is waiting or running, checks its status and reloads the page once it settles.

const panel = document.querySelector('[data-analysis-waiting]');

if (panel) {
    let checks = 0;

    const check = async () => {
        checks += 1;

        try {
            const response = await fetch(panel.dataset.statusUrl, { headers: { Accept: 'application/json' } });

            if (response.ok) {
                const { status } = await response.json();

                if (status !== 'pending' && status !== 'processing') {
                    window.location.reload();

                    return;
                }
            }
        } catch {
            // A dropped connection is retried on the next check
        }

        // About five minutes in all, slowing down over time
        if (checks < 40) {
            window.setTimeout(check, Math.min(10000, 2000 + checks * 500));
        } else {
            panel.querySelector('[data-still-waiting]').hidden = false;
        }
    };

    window.setTimeout(check, 2000);
}
