// Alpine is supplied once by Livewire. Do not import or start a second instance.
const sections = new Set(['#overview', '#foundation', '#preferences']);

function updateNavigation() {
    const current = sections.has(window.location.hash) ? window.location.hash : '#overview';

    document.querySelectorAll('.workspace-nav a').forEach((link) => {
        const active = link.getAttribute('href') === current;
        link.classList.toggle('nav-link-current', active);

        if (active) {
            link.setAttribute('aria-current', 'location');
        } else {
            link.removeAttribute('aria-current');
        }
    });
}

updateNavigation();
window.addEventListener('hashchange', updateNavigation);
