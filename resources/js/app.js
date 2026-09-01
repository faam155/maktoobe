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

document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-copy-prompt]');
    if (!button) return;
    const status = document.querySelector('[data-copy-status]');
    const content = document.querySelector('#prompt-copy-content code')?.textContent ?? '';
    try {
        try {
            await navigator.clipboard.writeText(content);
        } catch {
            const fallback = document.createElement('textarea');
            fallback.value = content;
            fallback.setAttribute('readonly', '');
            fallback.style.position = 'fixed';
            fallback.style.opacity = '0';
            document.body.appendChild(fallback);
            fallback.select();
            const copied = document.execCommand('copy');
            fallback.remove();
            if (!copied) throw new Error('Clipboard unavailable');
        }
        const response = await fetch(button.dataset.copyUrl, {
            method: 'POST', headers: {'Content-Type':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content,'Accept':'application/json'},
            body: JSON.stringify({client_operation_id: crypto.randomUUID()}),
        });
        if (!response.ok) throw new Error('Usage tracking failed');
        status.textContent = button.dataset.copySuccess;
    } catch {
        status.textContent = button.dataset.copyFailed;
    }
});
