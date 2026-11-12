// Felix on his stage moves like a little platform-game character, using the one picture of him: tap him to jump, tap the
// ground to walk there, or with him focused press Space or Up to jump and hold the left and right arrow keys to walk. He
// speeds up and slows down, bobs a block as he steps, falls back under gravity, squashes a little and puffs up dust as
// he lands, faces the way he's going, and always sits on whole picture blocks. With reduced motion he steps and pops in
// place instead of gliding and arcing.

const PIXEL = 2; // one block of his picture on screen
const EDGE = 26; // room kept at each side, so his speech bubble never leaves the stage
const TOP_SPEED = 150; // walking, in px per second
const ACCELERATION = 1100;
const FRICTION = 1300;
const GRAVITY = 1500;
const TAKE_OFF = 330; // a jump about 36 px high
const STEP = 12; // one key press with reduced motion

const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');

const snap = (value) => Math.round(value / PIXEL) * PIXEL;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

export function playable(stage) {
    const figure = stage.querySelector('[data-help-figure]');
    const actor = stage.querySelector('[data-help-actor]');
    const sprite = stage.querySelector('[data-help-sprite]');
    const me = { x: EDGE, y: 0, vx: 0, vy: 0, facing: 1, left: false, right: false, target: null, squash: 0, stride: 0 };
    let loop = 0;
    let previous = 0;
    let settleTimer = 0;
    let hops = 0;

    const maxX = () => Math.max(EDGE, stage.clientWidth - figure.offsetWidth - EDGE);

    const render = () => {
        // A one-block bob on every other step while he walks
        const bob = me.y === 0 && Math.abs(me.vx) > 20 && Math.floor(me.stride / 0.11) % 2 ? PIXEL : 0;
        const [squashX, squashY] = me.squash > 0 ? [1.08, 0.92] : [1, 1];

        figure.style.transform = `translate3d(${snap(me.x)}px, ${-snap(me.y) - bob}px, 0)`;
        sprite.style.transform = `scale(${me.facing * squashX}, ${squashY})`;
    };

    // The speech bubble steps aside while he moves, and comes back once he has stood still for a moment
    const moving = () => {
        window.clearTimeout(settleTimer);
        figure.dataset.moving = '';
    };

    const settle = () => {
        window.clearTimeout(settleTimer);
        settleTimer = window.setTimeout(() => delete figure.dataset.moving, 900);
    };

    const dust = () => {
        for (const direction of [-1, 1]) {
            const puff = document.createElement('span');
            puff.className = 'helper-dust';
            puff.style.left = `${snap(me.x) + 25 + direction * 14}px`;
            puff.style.setProperty('--dir', direction);
            puff.addEventListener('animationend', () => puff.remove());
            stage.append(puff);
        }
    };

    const busy = () => me.left || me.right || me.target !== null || me.vx !== 0 || me.y > 0 || me.vy !== 0 || me.squash > 0;

    const tick = (now) => {
        const seconds = clamp((now - previous) / 1000, 0, 0.05);
        previous = now;

        // Walking: toward a held arrow key, or toward the spot that was tapped, slowing in time to stop there
        let direction = (me.right ? 1 : 0) - (me.left ? 1 : 0);

        if (!direction && me.target !== null) {
            const distance = me.target - me.x;
            const stopping = (me.vx * me.vx) / (2 * FRICTION);

            if (Math.abs(distance) <= PIXEL && Math.abs(me.vx) < 40) {
                me.target = null;
                me.vx = 0;
            } else if (Math.abs(distance) > stopping) {
                direction = Math.sign(distance);
            }
        }

        if (direction) {
            me.facing = direction;
            me.vx = clamp(me.vx + direction * ACCELERATION * seconds, -TOP_SPEED, TOP_SPEED);
        } else {
            const slowing = FRICTION * seconds;
            me.vx = Math.abs(me.vx) <= slowing ? 0 : me.vx - Math.sign(me.vx) * slowing;
        }

        me.x += me.vx * seconds;

        if (me.x <= EDGE || me.x >= maxX()) {
            me.x = clamp(me.x, EDGE, maxX());
            me.vx = 0;
        }

        me.stride = me.vx ? me.stride + seconds : 0;

        // Jumping and falling; a queued hop (his hello) starts as soon as he lands
        if (me.y > 0 || me.vy > 0) {
            me.vy -= GRAVITY * seconds;
            me.y += me.vy * seconds;

            if (me.y <= 0) {
                me.y = 0;
                me.vy = 0;
                me.squash = 0.09;
                dust();
            }
        } else if (me.squash > 0) {
            me.squash = Math.max(0, me.squash - seconds);
        } else if (hops > 0) {
            hops -= 1;
            me.vy = TAKE_OFF * 0.6;
        }

        render();

        if (busy() || hops > 0) {
            loop = window.requestAnimationFrame(tick);
        } else {
            loop = 0;
            settle();
        }
    };

    const run = () => {
        if (!loop) {
            previous = performance.now();
            loop = window.requestAnimationFrame(tick);
        }
    };

    const jump = (strength = 1) => {
        if (reducedMotion.matches) {
            // A quick pop up and back down, without an arc
            moving();
            me.y = 8;
            render();
            window.setTimeout(() => {
                me.y = 0;
                render();
                settle();
            }, 160);

            return;
        }

        if (me.y === 0 && me.vy === 0) {
            moving();
            me.vy = TAKE_OFF * strength;
            run();
        }
    };

    const walkTo = (x) => {
        const target = clamp(x, EDGE, maxX());

        if (reducedMotion.matches) {
            me.facing = Math.sign(target - me.x) || me.facing;
            me.x = target;
            render();

            return;
        }

        moving();
        me.target = target;
        run();
    };

    const hold = (key, down) => {
        if (reducedMotion.matches) {
            if (down) {
                me.facing = key === 'left' ? -1 : 1;
                me.x = clamp(me.x + me.facing * STEP, EDGE, maxX());
                render();
            }

            return;
        }

        me[key] = down;
        me.target = null;

        if (down) {
            moving();
            run();
        }
    };

    actor.addEventListener('click', (event) => {
        event.stopPropagation();
        jump();
    });

    actor.addEventListener('keydown', (event) => {
        const key = { ArrowLeft: 'left', ArrowRight: 'right' }[event.key];

        if (key) {
            event.preventDefault();
            hold(key, true);
        } else if (event.key === 'ArrowUp') {
            event.preventDefault();
            jump();
        }
    });

    actor.addEventListener('keyup', (event) => {
        const key = { ArrowLeft: 'left', ArrowRight: 'right' }[event.key];

        if (key) {
            hold(key, false);
        }
    });

    // Leaving him never leaves him walking on his own
    actor.addEventListener('blur', () => {
        me.left = false;
        me.right = false;
    });

    stage.addEventListener('click', (event) => {
        const box = stage.getBoundingClientRect();
        walkTo(event.clientX - box.left - figure.offsetWidth / 2);
    });

    render();

    return {
        // Two little hops to say hello when the chat opens; his speech bubble stays up for them
        greet() {
            if (!reducedMotion.matches) {
                window.setTimeout(() => {
                    hops = 2;
                    run();
                }, 250);
            }
        },

        // A small hop when he has answered
        cheer() {
            if (!reducedMotion.matches) {
                jump(0.55);
            }
        },

        pause() {
            window.cancelAnimationFrame(loop);
            loop = 0;
            hops = 0;
            Object.assign(me, { y: 0, vx: 0, vy: 0, left: false, right: false, target: null, squash: 0, stride: 0 });
            render();
            delete figure.dataset.moving;
        },
    };
}
