const urlList = document.getElementById('url-list');
const logContainer = document.getElementById('log-container');
const addUrlBtn = document.getElementById('add-url-btn');
const newUrlInput = document.getElementById('new-url-input');
const timerInput = document.getElementById('timer-input');
const startBtn = document.getElementById('start-btn');
const stopBtn = document.getElementById('stop-btn');
const timerSubmitBtn = document.getElementById('timer-submit-btn');

const loginForm = document.getElementById('login-form');
const registerForm = document.getElementById('register-form');
const loginModalBtn = document.getElementById('login-modal-btn');
const registerModalBtn = document.getElementById('register-modal-btn');
const logoutBtn = document.getElementById('logout-btn');

const API_URL = 'http://127.0.0.1:8000/api';

let timeRemaining = 0;
let submittedTime = 0;
let isLoop = false;
let timerInterval;

newUrlInput.value = "https://";

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
                errContainer.innerHTML = `<span class="text-danger">${data.message || JSON.stringify(data.errors)}</span>`;
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
                errContainer.innerHTML = `<span class="text-danger">${data.message || 'Invalid credentials.'}</span>`;
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
            updateHeaderStats([]);
            updateAuthUI();
        }
    });
}

function startTimer() {
    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
        timeRemaining--;
        console.log(`Time left: ${timeRemaining}s`);

        if (timeRemaining <= 0) {
            sendRequests();
            console.log("Time's up!");
            if (isLoop) {
                timeRemaining = submittedTime;
            } else {
                clearInterval(timerInterval);
            }
        }
    }, 1000);
}

async function sendRequests() {
    try {
        console.log("Starting ping checks...");
        await fetch(`${API_URL}/monitors/check`, {
            method: 'POST',
            headers: getAuthHeaders()
        });

        fetchMonitors();
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
        updateHeaderStats(monitors);

        monitors.forEach(monitor => {
            const li = document.createElement('li');
            li.className = 'list-group-item bg-dark text-light d-flex justify-content-between align-items-center border-secondary';
            li.innerHTML = `
            <span class="text-truncate">${monitor.url}</span>
            <button class="btn btn-sm btn-outline-danger" onclick="deleteMonitor(${monitor.id})">X</button>
            `;
            urlList.appendChild(li);

            if (monitor.status !== 'pending') {
                const time = monitor.last_checked_at ? new Date(monitor.last_checked_at).toLocaleTimeString() : '';
                const color = monitor.status === 'up' ? 'text-success' : 'text-danger';
                const logEntry = `<div><span class="text-secondary">[${time}]</span> <span class="${color}">[${monitor.status.toUpperCase()}]</span> ${monitor.name}</div>`;

                logContainer.insertAdjacentHTML('afterbegin', logEntry);
            }
        });
    } catch (error) {
        console.error('Fetch error:', error);
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
                check_interval: timerInput.value
            })
        });

        if (!response.ok) {
            const errorData = await response.json();

            if (errorData.errors && errorData.errors.check_interval) {
                timerErrorContainer.innerHTML = `<span class="text-danger">Error: ${errorData.errors.check_interval[0]}</span>`;
            } else {
                timerErrorContainer.innerHTML = `<span class="text-danger">An unexpected error occurred while adding the monitor.</span>`;
            }
            return;
        } else {
            timerErrorContainer.innerHTML = `<span class="text-success">Success: ${newUrlInput.value} Added Successfully</span>`;
        }

        newUrlInput.value = "https://";
        fetchMonitors();

    } catch (error) {
        console.error('Addition error:', error);
    }
});

window.deleteMonitor = async function(id) {
    if (!confirm('Are you sure you want to stop monitoring this site?')) return;

    try {
        const response = await fetch(`${API_URL}/monitors/${id}`, {
            method: 'DELETE',
            headers: getAuthHeaders()
        });

        if (response.ok) {
            fetchMonitors();
        }
    } catch (error) {
        console.error('Delete action failed:', error);
    }
};

timerSubmitBtn.addEventListener('click', () => {
    submittedTime = parseInt(timerInput.value) || 0;
    timeRemaining = submittedTime;
    console.log(`Timer set to ${submittedTime} seconds.`);
});

startBtn.addEventListener('click', () => {
    isLoop = true;
    if (timeRemaining > 0) startTimer();
});

stopBtn.addEventListener('click', () => {
    isLoop = false;
    clearInterval(timerInterval);
    console.log("Timer stopped.");
});

updateAuthUI();
fetchMonitors();