// Main Application Client Scripts
document.addEventListener('DOMContentLoaded', () => {
    // 1. Mobile Sidebar Toggle
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.app-sidebar');
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', () => {
            sidebar.classList.toggle('show');
        });
    }

    // 2. Auto-dismiss alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) bsAlert.close();
        }, 6000);
    });

    // 3. Quick Run Worker AJAX button
    const runWorkerBtn = document.getElementById('btnRunWorkerAjax');
    if (runWorkerBtn) {
        runWorkerBtn.addEventListener('click', async (e) => {
            e.preventDefault();
            const originalHtml = runWorkerBtn.innerHTML;
            runWorkerBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Processing...';
            runWorkerBtn.setAttribute('disabled', 'true');

            try {
                const response = await fetch(runWorkerBtn.getAttribute('href') || '/api/worker/run', {
                    headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                });
                const data = await response.json();
                
                showToast(data.message || 'Worker finished processing.', data.worked ? 'success' : 'info');
                
                // If on queue or dashboard page, reload after 1.5s
                setTimeout(() => window.location.reload(), 1500);
            } catch (err) {
                showToast('Failed to execute worker request: ' + err.message, 'danger');
                runWorkerBtn.innerHTML = originalHtml;
                runWorkerBtn.removeAttribute('disabled');
            }
        });
    }

    // 4. Copy to Clipboard
    document.querySelectorAll('[data-copy]').forEach(btn => {
        btn.addEventListener('click', () => {
            const text = btn.getAttribute('data-copy');
            if (text) {
                navigator.clipboard.writeText(text);
                const originalTitle = btn.getAttribute('title') || 'Copy';
                btn.setAttribute('title', 'Copied!');
                showToast('Copied to clipboard!', 'success');
            }
        });
    });
});

// Toast notification helper
function showToast(message, type = 'info') {
    let container = document.getElementById('toastContainer');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toastContainer';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center text-white bg-${type === 'info' ? 'primary' : type} border-0`;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');
    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body">${message}</div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
    `;

    container.appendChild(toastEl);
    const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
    toast.show();
    toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
}
