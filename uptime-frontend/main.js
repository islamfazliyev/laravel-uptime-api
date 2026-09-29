const urlList = document.getElementById('url-list');
const logContainer = document.getElementById('log-container');
const addUrlBtn = document.getElementById('add-url-btn');
const newUrlInput = document.getElementById('new-url-input');
const intervalInput = document.getElementById('interval-input');
const checkNowBtn = document.getElementById('check-now-btn');

const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const loginModalBtn = document.getElementById('login-modal-btn');
const registerModalBtn = document.getElementById('register-modal-btn');
const logoutBtn = document.getElementById('logout-btn');

const statsPanel = document.getElementById('stats-panel');

const API_URL = 'http://127.0.0.1:8000/api';
const REFRESH_MS = 10000;

let statsChart = null;

newUrlInput.value = "https://";

function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str ?? '';
    return div.innerHTML;
}

function getAuthHeaders() {
    const token = localStorage.getItem('token');
    return {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
        ...(token ? { 'Authorization': `Bearer ${token}` } : {})
    };
}

function updateAuthUI() {
    const token = localStorage.getItem('token');
    if (token) {
        if (loginModalBtn) loginModalBtn.classList.add('d-none');
        if (registerModalBtn) registerModalBtn.classList.add('d-none');
        if (logoutBtn) logoutBtn.classList.remove('d-none');
    } else {
        if (loginModalBtn) loginModalBtn.classList.remove('d-none');
        if (registerModalBtn) registerModalBtn.classList.remove('d-none');
        if (logoutBtn) logoutBtn.classList.add('d-none');
    }
}

function hideStats() {
    if (statsChart) {
        statsChart.destroy();
        statsChart = null;
    }
    if (statsPanel) statsPanel.classList.add('d-none');
}

if (registerForm) {
    registerForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errContainer = document.getElementById('register-error-container');
        errContainer.innerHTML = '';

        const name = document.getElementById('register-name').value;
        const email = document.getElementById('register-email').value;
        const password = document.getElementById('register-password').value;

        try {
            const res = await fetch(`${API_URL}/register`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ name, email, password })
            });
            const data = await res.json();

            if (res.ok) {
                localStorage.setItem('token', data.token);
                const modal = bootstrap.Modal.getInstance(document.getElementById('registerModal'));
                if (modal) modal.hide();
                registerForm.reset();
                updateAuthUI();
                fetchMonitors();
            } else {
                errContainer.innerHTML = `<span class="text-danger">${escapeHtml(data.message || JSON.stringify(data.errors))}</span>`;
            }
        } catch (err) {
            errContainer.innerHTML = `<span class="text-danger">Connection error.</span>`;
        }
    });
}

if (loginForm) {
    loginForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const errContainer = document.getElementById('login-error-container');
        errContainer.innerHTML = '';

        const email = document.getElementById('login-email').value;
        const password = document.getElementById('login-password').value;

        try {
            const res = await fetch(`${API_URL}/login`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                body: JSON.stringify({ email, password })
            });
            const data = await res.json();

            if (res.ok) {
                localStorage.setItem('token', data.token);
                const modal = bootstrap.Modal.getInstance(document.getElementById('loginModal'));
                if (modal) modal.hide();
                loginForm.reset();
                updateAuthUI();
                fetchMonitors();
            } else {
                errContainer.innerHTML = `<span class="text-danger">${escapeHtml(data.message || 'Invalid credentials.')}</span>`;
            }
        } catch (err) {
            errContainer.innerHTML = `<span class="text-danger">Connection error.</span>`;
        }
    });
}

if (logoutBtn) {
    logoutBtn.addEventListener('click', async () => {
        try {
            await fetch(`${API_URL}/logout`, {
                method: 'POST',
                headers: getAuthHeaders()
            });
        } catch (err) {
            console.error("Logout error:", err);
        } finally {
            localStorage.removeItem('token');
            urlList.innerHTML = '';
            logContainer.innerHTML = '';
            hideStats();
            updateHeaderStats([]);
            updateAuthUI();
        }
    });
}

// Scheduling now lives on the backend (schedule:work + queue:work).
// This only asks the API to queue an immediate check for the user's monitors.
async function sendRequests() {
    try {
        await fetch(`${API_URL}/monitors/check`, {
            method: 'POST',
            headers: getAuthHeaders()
        });

        // jobs run in the background, give the worker a moment
        setTimeout(fetchMonitors, 3000);
    } catch (error) {
        console.error("Error during ping process:", error);
    }
}

function updateHeaderStats(monitors) {
    const pulseDot = document.getElementById('pulse-dot');
    const statUp = document.getElementById('stat-up');
    const statDown = document.getElementById('stat-down');
    const statTotal = document.getElementById('stat-total');
    if (!pulseDot || !statUp || !statDown || !statTotal) return;

    const upCount = monitors.filter(m => m.status === 'up').length;
    const downCount = monitors.filter(m => m.status === 'down').length;

    statUp.textContent = upCount;
    statDown.textContent = downCount;
    statTotal.textContent = monitors.length;

    pulseDot.classList.remove('is-idle', 'is-down');
    if (monitors.length === 0) {
        pulseDot.classList.add('is-idle');
    } else if (downCount > 0) {
        pulseDot.classList.add('is-down');
    }
}

async function fetchMonitors() {
    if (!localStorage.getItem('token')) return;

    try {
        const response = await fetch(`${API_URL}/monitors`, {
            headers: getAuthHeaders()
        });

        if (response.status === 401) {
            console.warn("Unauthorized: Please log in.");
            return;
        }

        const monitors = await response.json();

        urlList.innerHTML = '';
        logContainer.innerHTML = ''; // refreshed periodically, so don't stack duplicate log lines
        updateHeaderStats(monitors);

        monitors.forEach(monitor => {
            const li = document.createElement('li');
            li.className = 'list-group-item bg-dark text-light d-flex justify-content-between align-items-center border-secondary';

            const span = document.createElement('span');
            span.className = 'text-truncate';
            span.style.cursor = 'pointer';
            span.title = 'Show stats';
            span.textContent = monitor.url;
            span.addEventListener('click', () => showStats(monitor.id, monitor.name));

            const btn = document.createElement('button');
            btn.className = 'btn btn-sm btn-outline-danger';
            btn.textContent = 'X';
            btn.addEventListener('click', () => deleteMonitor(monitor.id));

            li.append(span, btn);
            urlList.appendChild(li);

            if (monitor.status !== 'pending') {
                const time = monitor.last_checked_at ? new Date(monitor.last_checked_at).toLocaleTimeString() : '';
                const color = monitor.status === 'up' ? 'text-success' : 'text-danger';
                const logEntry = `<div><span class="text-secondary">[${time}]</span> <span class="${color}">[${monitor.status.toUpperCase()}]</span> ${escapeHtml(monitor.name)}</div>`;

                logContainer.insertAdjacentHTML('afterbegin', logEntry);
            }
        });
    } catch (error) {
        console.error('Fetch error:', error);
    }
}

async function showStats(id, name) {
    try {
        const res = await fetch(`${API_URL}/monitors/${id}/stats`, {
            headers: getAuthHeaders()
        });
        if (!res.ok) return;

        const data = await res.json();

        document.getElementById('stats-title').textContent = name;
        document.getElementById('stats-uptime').textContent =
            data.uptime_24h === null
                ? 'No checks in the last 24h'
                : `${data.uptime_24h}% uptime (last 24h, ${data.total_checks_24h} checks)`;

        const labels = data.recent.map(p => new Date(p.created_at).toLocaleTimeString());
        const values = data.recent.map(p => p.response_time_ms);

        if (statsChart) statsChart.destroy();

        statsChart = new Chart(document.getElementById('stats-chart'), {
            type: 'line',
            data: {
                labels,
                datasets: [{
                    label: 'Response time (ms)',
                    data: values,
                    borderColor: '#35d68e',
                    backgroundColor: 'rgba(53, 214, 142, 0.1)',
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                plugins: { legend: { labels: { color: '#6d8388' } } },
                scales: {
                    x: { ticks: { color: '#6d8388' }, grid: { color: '#182226' } },
                    y: { ticks: { color: '#6d8388' }, grid: { color: '#182226' }, beginAtZero: true }
                }
            }
        });

        statsPanel.classList.remove('d-none');
    } catch (error) {
        console.error('Stats error:', error);
    }
}

addUrlBtn.addEventListener('click', async () => {
    const timerErrorContainer = document.getElementById("timer-error-container");

    const url = newUrlInput.value.trim();
    if (!url) return;
    timerErrorContainer.innerHTML = "";

    try {
        const response = await fetch(`${API_URL}/monitors`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({
                name: new URL(url).hostname,
                url: url,
                check_interval: intervalInput.value
            })
        });

        if (!response.ok) {
            const errorData = await response.json();

            if (errorData.errors && errorData.errors.check_interval) {
                timerErrorContainer.innerHTML = `<span class="text-danger">Error: ${escapeHtml(errorData.errors.check_interval[0])}</span>`;
            } else {
                timerErrorContainer.innerHTML = `<span class="text-danger">An unexpected error occurred while adding the monitor.</span>`;
            }
            return;
        }

        timerErrorContainer.innerHTML = `<span class="text-success">Success: ${escapeHtml(url)} Added Successfully</span>`;

        newUrlInput.value = "https://";
        fetchMonitors();

    } catch (error) {
        console.error('Addition error:', error);
    }
});

window.deleteMonitor = async function (id) {
    if (!confirm('Are you sure you want to stop monitoring this site?')) return;

    try {
        const response = await fetch(`${API_URL}/monitors/${id}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        if (response.ok) {
            hideStats();
            fetchMonitors();
        }
    } catch (error) {
        console.error('Delete action failed:', error);
    }
};

checkNowBtn.addEventListener('click', sendRequests);

updateAuthUI();
fetchMonitors();
setInterval(fetchMonitors, REFRESH_MS);
