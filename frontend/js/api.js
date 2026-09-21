/**
 * LifeLink Blood Bank Management System
 * Course: Database Management Systems Laboratory (CSE 3522)
 * 
 * Central API Client & Toast Notification Utility
 */

const API_BASE = (function() {
    // Dynamically calculate the backend API path relative to current page
    const loc = window.location.pathname;
    if (loc.includes('/frontend/')) {
        return '../backend/api/';
    }
    return 'backend/api/';
})();

/**
 * Execute an API request with fetch
 */
async function apiRequest(endpoint, method = 'GET', data = null) {
    const url = API_BASE + endpoint;
    const options = {
        method: method,
        headers: {
            'Accept': 'application/json'
        },
        credentials: 'same-origin'
    };

    if (data && (method === 'POST' || method === 'PUT' || method === 'PATCH')) {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(data);
    }

    try {
        const response = await fetch(url, options);
        const json = await response.json();

        if (!response.ok || json.success === false) {
            const msg = json.message || 'An error occurred while processing request.';
            throw new Error(msg);
        }

        return json;
    } catch (err) {
        console.error('API Error:', err);
        throw err;
    }
}

/**
 * Show a bootstrap toast notification
 */
function showToast(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        container.style.zIndex = '9999';
        document.body.appendChild(container);
    }

    const toastId = 'toast_' + Date.now();
    const bgClass = type === 'success' ? 'text-bg-success' : 
                    type === 'danger' ? 'text-bg-danger' : 
                    type === 'warning' ? 'text-bg-warning' : 'text-bg-primary';

    const toastEl = document.createElement('div');
    toastEl.className = `toast align-items-center ${bgClass} border-0 shadow`;
    toastEl.id = toastId;
    toastEl.setAttribute('role', 'alert');
    toastEl.setAttribute('aria-live', 'assertive');
    toastEl.setAttribute('aria-atomic', 'true');

    toastEl.innerHTML = `
        <div class="d-flex">
            <div class="toast-body fw-semibold">
                ${message}
            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;

    container.appendChild(toastEl);
    if (window.bootstrap && window.bootstrap.Toast) {
        const toast = new bootstrap.Toast(toastEl, { delay: 4000 });
        toast.show();
        toastEl.addEventListener('hidden.bs.toast', () => toastEl.remove());
    } else {
        setTimeout(() => toastEl.remove(), 4000);
    }
}

/**
 * Retrieve current authenticated session
 */
async function getCurrentUser() {
    try {
        const res = await apiRequest('auth.php?action=me');
        return res.data ? res.data.user : null;
    } catch (e) {
        return null;
    }
}

/**
 * Sign out current session
 */
async function logout() {
    try {
        await apiRequest('auth.php?action=logout', 'POST');
        showToast('Signed out successfully.', 'info');
        setTimeout(() => {
            window.location.href = 'index.html';
        }, 600);
    } catch (e) {
        window.location.href = 'index.html';
    }
}
