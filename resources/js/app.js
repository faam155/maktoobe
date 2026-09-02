// Alpine is supplied once by Livewire. Do not import or start a second instance.
const sections = new Set(['#overview', '#foundation', '#preferences']);

document.querySelectorAll('[data-generation-status]').forEach((status) => {
    const refresh = status.parentElement.querySelector('.generation-refresh');
    const timer = setInterval(async () => {
        try {
            const response = await fetch(status.dataset.generationStatus, {headers: {Accept: 'application/json'}});
            if (!response.ok) { clearInterval(timer); refresh.hidden = false; return; }
            const data = await response.json();
            if (!['queued', 'processing'].includes(data.status)) {
                clearInterval(timer);
                // Never reload over an editor's unsaved changes.
                refresh.hidden = false;
                status.hidden = true;
            }
        } catch { clearInterval(timer); refresh.hidden = false; }
    }, 8000);
});

document.querySelectorAll('[data-communication-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        const feedback = button.parentElement.querySelector('[role=status]');
        try {
            const content = document.getElementById(button.dataset.communicationCopy).textContent;
            try {
                await navigator.clipboard.writeText(content);
            } catch {
                const fallback = document.createElement('textarea');
                fallback.value = content;
                fallback.setAttribute('readonly', '');
                fallback.style.position = 'fixed';
                fallback.style.opacity = '0';
                document.body.appendChild(fallback);
                try {
                    fallback.select();
                    if (!document.execCommand('copy')) throw new Error('Clipboard unavailable');
                } finally { fallback.remove(); button.focus(); }
            }
            feedback.textContent = button.dataset.copied;
        } catch { feedback.textContent = button.dataset.failed; }
    });
});

document.querySelectorAll('[data-event-upload]').forEach((form) => {
    form.addEventListener('submit', (event) => {
        event.preventDefault();
        const button = form.querySelector('button');
        if (button.disabled) return;
        const body = new FormData(form);
        const feedback = form.querySelector('[data-upload-feedback]');
        const progress = feedback.querySelector('progress');
        const status = feedback.querySelector('[role=status]');
        feedback.hidden = false;
        progress.value = 0;
        status.textContent = '';
        button.disabled = true;
        const request = new XMLHttpRequest();
        request.open('POST', form.action);
        request.setRequestHeader('Accept', 'application/json');
        request.responseType = 'json';
        request.timeout = 120000;
        request.upload.onprogress = (upload) => {
            if (upload.lengthComputable) {
                progress.value = Math.round(upload.loaded / upload.total * 100);
                status.textContent = progress.value === 100 ? form.dataset.processing : `${progress.value}%`;
            }
        };
        const failed = () => { status.textContent = form.dataset.failed; button.disabled = false; };
        request.onerror = failed;
        request.ontimeout = failed;
        request.onload = () => {
            if (request.status >= 200 && request.status < 300 && request.response?.redirect) {
                const destination = new URL(request.response.redirect, window.location.href);
                if (destination.origin === window.location.origin && destination.pathname === window.location.pathname && destination.search === window.location.search) {
                    window.location.hash = destination.hash;
                    window.location.reload();
                } else {
                    window.location.assign(destination.href);
                }
            } else if (request.status === 200 && request.responseURL && new URL(request.responseURL).pathname === '/confirm-password') {
                window.location.assign(request.responseURL);
            } else {
                status.textContent = request.status === 422 && request.response?.errors
                    ? Object.values(request.response.errors).flat().join(' ') : form.dataset.failed;
                button.disabled = false;
            }
        };
        request.send(body);
    });
});

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

const pendingAi = document.querySelectorAll('[data-ai-poll]');
if (pendingAi.length) {
    const poll = async () => {
        for (const item of pendingAi) {
            try {
                const response = await fetch(item.dataset.aiPoll, {headers: {'Accept':'application/json'}});
                if (!response.ok) continue;
                const data = await response.json();
                if (['completed','failed','cancelled'].includes(data.status)) {
                    window.location.reload();
                    return;
                }
            } catch { /* Retry transient browser/network failures on the next interval. */ }
        }
        window.setTimeout(poll, 2000);
    };
    window.setTimeout(poll, 1000);
}
