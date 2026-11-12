// Fix-It Felix, RISE's helper: the floating button opens a chat that starts with a greeting and a few questions. His answers
// are written into the page, so they work without any AI, and after each one he offers the questions that naturally
// come next. "What should I do next?" is worked out on the server from the resident's own spots. A typed question goes
// to the server, which answers greetings and questions about RISE itself straight away, and otherwise either sends only
// the typed words (with this chat's earlier typed questions and AI answers) to the AI provider, when one is set up, or
// picks the closest built-in answer. Nothing is stored, here or on the server: leaving the page ends the conversation.

import { playable } from './help-mascot';

const dialog = document.querySelector('[data-help-dialog]');
const opener = document.querySelector('[data-help-open]');

if (dialog && opener && typeof dialog.showModal === 'function') {
    const panel = dialog.querySelector('.helper-window');
    const scroller = dialog.querySelector('[data-help-scroll]');
    const log = dialog.querySelector('[data-help-log]');
    const chips = dialog.querySelector('[data-help-chips]');
    const form = dialog.querySelector('[data-help-form]');
    const input = form.elements.message;
    const send = dialog.querySelector('[data-help-send]');
    const status = dialog.querySelector('[data-help-status]');
    const provider = dialog.dataset.provider;
    const questions = JSON.parse(dialog.dataset.questions);
    const defaultFollow = dialog.dataset.defaultFollow.split(' ');
    const mascot = playable(dialog.querySelector('[data-help-stage]'));
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    // Only typed questions and the AI's answers to them; never a built-in answer or anything about the resident's spots
    const history = [];
    let busy = false;
    let noted = false;
    let pressedOutside = false;

    const element = (tag, className = '', text = null) => {
        const node = document.createElement(tag);
        node.className = className;

        if (text !== null) {
            node.textContent = text;
        }

        return node;
    };

    const templateFor = (topic) =>
        dialog.querySelector(`template[data-help-answer="${topic}"]`) ?? dialog.querySelector('template[data-help-answer="unclear"]');

    const answerFor = (topic) => templateFor(topic).content.cloneNode(true);

    const followFor = (topic) => templateFor(topic).dataset.follow.split(' ');

    // The server's next-step answer is its own escaped Blade view
    const fragmentFrom = (html) => {
        const holder = document.createElement('template');
        holder.innerHTML = html;

        return holder.content;
    };

    // The server's note that AI chat isn't set up comes with the first answer it applies to, not every one
    const withNote = (note, content) => {
        if (!note || noted) {
            return content;
        }

        noted = true;
        const both = document.createDocumentFragment();
        both.append(element('p', 'helper-note', note), content);

        return both;
    };

    const thinking = (text) => {
        const line = element('p', 'helper-typing');
        const dots = element('span', 'helper-dots');
        dots.setAttribute('aria-hidden', 'true');
        dots.append(element('i'), element('i'), element('i'));
        line.append(dots, document.createTextNode(text));

        return line;
    };

    // The questions that naturally come next, arriving one after another
    const offer = (topics) => {
        chips.replaceChildren(
            ...topics
                .filter((topic) => questions[topic])
                .map((topic, index) => {
                    const chip = element('button', 'helper-chip', questions[topic]);
                    chip.type = 'button';
                    chip.dataset.helpQuestion = topic;
                    chip.style.setProperty('--i', index);

                    return chip;
                }),
        );
    };

    const addQuestion = (text) => {
        const item = element('li', 'helper-msg helper-msg-you');
        item.append(element('span', 'sr-only', 'You asked: '), document.createTextNode(text));
        log.append(item);

        return item;
    };

    const addAnswer = (content) => {
        const item = element('li', 'helper-msg helper-msg-reply');
        const body = element('div', 'helper-answer');
        item.tabIndex = -1;
        body.append(content);
        item.append(body);
        log.append(item);

        return item;
    };

    const fill = (answer, content) => answer.querySelector('.helper-answer').replaceChildren(content);

    // The question scrolls to the top of the chat, so its whole answer reads from the start
    const reveal = (asked) => {
        scroller.scrollTo({ top: Math.max(0, asked.offsetTop - 12), behavior: reducedMotion.matches ? 'auto' : 'smooth' });
    };

    const setBusy = (state) => {
        busy = state;
        send.setAttribute('aria-disabled', String(state));
        log.setAttribute('aria-busy', String(state));
    };

    const announce = (text) => {
        status.textContent = '';
        window.setTimeout(() => {
            status.textContent = text;
        }, 60);
    };

    // After an answer: Felix hops, offers what comes next, and either moves focus to the answer (a tapped question, whose
    // chip is gone) or leaves it where the resident is typing and reads the answer out instead
    const done = (asked, answer, moveFocus, follow) => {
        offer(follow);
        reveal(asked);
        mascot.cheer();

        if (moveFocus) {
            answer.focus({ preventScroll: true });
        } else {
            announce(`Felix: ${answer.textContent.replace(/\s+/g, ' ').trim()}`);
        }
    };

    const answerTopic = async (topic, asked, moveFocus, note = null) => {
        if (topic !== 'next') {
            done(asked, addAnswer(withNote(note, answerFor(topic))), moveFocus, followFor(topic));

            return;
        }

        const answer = addAnswer(thinking('Checking your spots…'));
        reveal(asked);
        setBusy(true);

        try {
            const response = await fetch(dialog.dataset.nextUrl, { headers: { Accept: 'application/json' }, cache: 'no-store' });

            if (!response.ok) {
                throw new Error(`Next step returned ${response.status}`);
            }

            fill(answer, withNote(note, fragmentFrom((await response.json()).html)));
        } catch {
            fill(answer, withNote(note, answerFor('next')));
        }

        setBusy(false);
        done(asked, answer, moveFocus, followFor('next'));
    };

    const problem = (code, data) =>
        ({
            401: 'You’ve been signed out. Reload the page and sign in to keep chatting.',
            419: 'This page has been open a long time. Reload it to keep chatting.',
            422: data?.errors?.message?.[0] ?? data?.message,
            429: data?.message,
        })[code] ??
        (code === 0 ? 'I couldn’t reach RISE. Check your connection and try again.' : 'Something went wrong. Please try again.');

    const show = async (reply, text, asked, answer) => {
        if (reply.kind === 'builtin' && reply.topic === 'next') {
            answer.remove();
            await answerTopic('next', asked, false, reply.note);

            return;
        }

        let follow = defaultFollow;

        if (reply.kind === 'ai') {
            const content = document.createDocumentFragment();
            content.append(element('p', 'helper-ai-text', reply.answer), element('p', 'helper-tag', `Answered by ${reply.provider} · may be wrong`));
            fill(answer, content);
            history.push({ role: 'resident', text }, { role: 'helper', text: reply.answer });
        } else if (reply.kind === 'builtin') {
            fill(answer, withNote(reply.note, answerFor(reply.topic)));
            follow = followFor(reply.topic);
        } else if (reply.kind === 'unavailable') {
            fill(answer, answerFor('unavailable'));
        } else if (reply.kind === 'error') {
            const content = document.createDocumentFragment();
            content.append(element('p', '', reply.message));

            if (reply.topic && reply.topic !== 'next') {
                content.append(element('p', 'helper-note', 'Here’s what I know without AI:'), answerFor(reply.topic));
                follow = followFor(reply.topic);
            }

            fill(answer, content);
        } else {
            fill(answer, element('p', '', problem(reply.status, reply.data)));
        }

        done(asked, answer, false, follow);
    };

    // Keeps the window above a phone's on-screen keyboard while the chat is open
    const keyboard = () => {
        const viewport = window.visualViewport;
        dialog.style.setProperty('--help-keyboard', `${Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop)}px`);
    };

    const watchKeyboard = (on) => {
        if (!window.visualViewport) {
            return;
        }

        for (const type of ['resize', 'scroll']) {
            window.visualViewport[on ? 'addEventListener' : 'removeEventListener'](type, keyboard);
        }

        if (on) {
            keyboard();
        }
    };

    // Tidies up once the chat has closed, however it closed; runs once per opening
    let tidied = true;

    const closed = () => {
        if (tidied) {
            return;
        }

        tidied = true;
        delete dialog.dataset.closing;
        opener.setAttribute('aria-expanded', 'false');
        watchKeyboard(false);
        mascot.pause();

        // Back to the button that opened it; Safari doesn't focus a button when it's clicked, so this is done by hand
        if (document.activeElement === document.body || dialog.contains(document.activeElement)) {
            opener.focus({ preventScroll: true });
        }
    };

    // The window shrinks back into Felix's corner before it closes; with reduced motion it simply closes
    const close = () => {
        if (!dialog.open || 'closing' in dialog.dataset) {
            return;
        }

        if (reducedMotion.matches) {
            dialog.close();
            closed();

            return;
        }

        dialog.dataset.closing = '';

        const finish = () => {
            window.clearTimeout(fallback);
            panel.removeEventListener('animationend', ended);
            dialog.close();
            closed();
        };
        const ended = (event) => {
            if (event.target === panel) {
                finish();
            }
        };
        const fallback = window.setTimeout(finish, 300);

        panel.addEventListener('animationend', ended);
    };

    opener.hidden = false;

    opener.addEventListener('click', () => {
        if (dialog.open) {
            return;
        }

        dialog.showModal();
        tidied = false;
        opener.setAttribute('aria-expanded', 'true');
        watchKeyboard(true);
        // A suggestion first, so a phone's keyboard doesn't cover the greeting; the screen reader hears the greeting
        (chips.querySelector('button') ?? input).focus();
        mascot.greet();
    });

    // Also covers a close the browser makes by itself, such as a second Escape during the closing animation
    dialog.addEventListener('close', closed);

    // Escape closes with the same animation
    dialog.addEventListener('cancel', (event) => {
        event.preventDefault();
        close();
    });

    dialog.querySelector('[data-help-close]').addEventListener('click', close);

    // A click on the dimmed page around the window closes it, like the app's menus; a drag that only ends there doesn't
    dialog.addEventListener('pointerdown', (event) => {
        pressedOutside = event.target === dialog;
    });

    dialog.addEventListener('click', (event) => {
        if (event.target === dialog && pressedOutside) {
            close();
        }
    });

    chips.addEventListener('click', (event) => {
        const chip = event.target.closest('[data-help-question]');

        if (!chip || busy) {
            return;
        }

        const asked = addQuestion(chip.textContent.trim());
        answerTopic(chip.dataset.helpQuestion, asked, true);
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();

        const text = input.value.replace(/\s+/g, ' ').trim();

        if (busy || !text) {
            input.focus();

            return;
        }

        input.value = '';
        const asked = addQuestion(text);
        const answer = addAnswer(thinking(provider ? `Asking ${provider}…` : 'Thinking…'));
        chips.replaceChildren();
        reveal(asked);
        setBusy(true);

        let reply;

        try {
            const response = await fetch(dialog.dataset.askUrl, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': form.elements._token.value,
                },
                body: JSON.stringify({ message: text, history: history.slice(-6) }),
            });
            const data = await response.json().catch(() => ({}));
            reply = response.ok ? data : { kind: 'problem', status: response.status, data };
        } catch {
            reply = { kind: 'problem', status: 0, data: {} };
        }

        setBusy(false);
        await show(reply, text, asked, answer);
    });
}
