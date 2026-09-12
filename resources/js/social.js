const workspace = document.querySelector('.social-workspace');
if (workspace) {
    const feedback = workspace.querySelector('[data-social-feedback]');
    workspace.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', () => {
            form.setAttribute('aria-busy', 'true');
            form.querySelectorAll('button').forEach(button => { button.disabled = true; });
            if (feedback) { feedback.hidden = false; feedback.textContent = feedback.dataset.processing; }
        });
    });
    window.addEventListener('pageshow', () => {
        workspace.querySelectorAll('form[aria-busy]').forEach(form => {
            form.removeAttribute('aria-busy');
            form.querySelectorAll('button').forEach(button => { button.disabled = false; });
        });
    });
    workspace.querySelectorAll('a[href*="/export/"]').forEach(link => {
        link.addEventListener('click', async event => {
            event.preventDefault();
            if (link.getAttribute('aria-busy') === 'true') return;
            link.setAttribute('aria-busy', 'true');
            if (feedback) { feedback.hidden = false; feedback.textContent = feedback.dataset.processing; }
            try {
                const response = await fetch(link.href, { headers: { Accept: 'application/octet-stream' } });
                const disposition = response.headers.get('content-disposition') || '';
                if (!response.ok || !disposition.includes('attachment')) throw new Error('export');
                const blob = await response.blob();
                const objectUrl = URL.createObjectURL(blob);
                const download = document.createElement('a');
                download.href = objectUrl;
                download.download = disposition.match(/filename="?([^";]+)"?/)?.[1] || 'linkedin-report';
                document.body.appendChild(download); download.click(); download.remove();
                setTimeout(() => URL.revokeObjectURL(objectUrl), 30000);
                if (feedback) feedback.textContent = feedback.dataset.completed;
            } catch {
                if (feedback) feedback.textContent = feedback.dataset.failed;
            } finally { link.removeAttribute('aria-busy'); }
        });
    });
}
