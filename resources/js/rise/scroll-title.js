// On phones, once the page's large title scrolls under the floating top bar, the bar shows that title in place of the
// RISE name, so you always know where you are. The bar's copy is decorative; the real heading stays in the page.

const title = document.querySelector('[data-page-title]');
const compact = document.querySelector('[data-compact-title]');

if (title && compact && 'IntersectionObserver' in window) {
    const barBottom = 80;

    new IntersectionObserver(
        ([entry]) => {
            const scrolledPast = !entry.isIntersecting && entry.boundingClientRect.bottom < barBottom;

            compact.textContent = title.textContent.trim();
            document.documentElement.toggleAttribute('data-title-collapsed', scrolledPast);
        },
        { rootMargin: `-${barBottom}px 0px 0px 0px` },
    ).observe(title);
}
