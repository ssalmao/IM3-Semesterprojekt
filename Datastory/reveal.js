/**
 * Blendet den Inhalt jedes Abschnitts ein, sobald er beim Scrollen
 * ins Bild kommt. Die Animation selbst steht in style.css.
 */
export function initReveal() {
    const sections = document.querySelectorAll('.hero, .intro, .chapter, .statement, .method');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('is-visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.2 });

    sections.forEach((section) => {
        section.classList.add('reveal');
        observer.observe(section);
    });
}