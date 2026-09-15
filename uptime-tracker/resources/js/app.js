import 'bootstrap';

const urlList = document.getElementById('url-list');
const logContainer = document.getElementById('log-container');
const addUrlBtn = document.getElementById('add-url-btn');
const newUrlInput = document.getElementById('new-url-input');
const timerInput = document.getElementById('timer-input');
const startBtn = document.getElementById('start-btn');
const stopBtn = document.getElementById('stop-btn');
const timerSubmitBtn = document.getElementById('timer-submit-btn');

let timeRemaining = 0;
let submittedTime = 0
let isLoop = false;
let timerInterval;

newUrlInput.value = "https://";

function startTimer() {

    if (timerInterval) clearInterval(timerInterval);

    timerInterval = setInterval(() => {
      
      timeRemaining--;
    
      console.log(`Time left: ${timeRemaining}s`);
    
      if (timeRemaining <= 0) {
        sendRequests()
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
        console.log("Ping atma işlemi başlatılıyor...");
        await fetch('/api/monitors/check', { 
            method: 'POST',
            headers: { 'Accept': 'application/json' }
        });

        fetchMonitors(); 
    } catch (error) {
        console.error("Ping işlemi sırasında hata oluştu:", error);
    }
}

async function fetchMonitors() {
    const response = await fetch('/api/monitors');
    const monitors = await response.json();

    urlList.innerHTML = '';

    monitors.forEach(monitor => {
        try {
            const li = document.createElement('li');
            li.className = 'list-group-item bg-dark text-light d-flex justify-content-between align-items-center border-secondary';
            li.innerHTML = `
                <span class="text-truncate">${monitor.url}</span>
                <button class="btn btn-sm btn-outline-danger" onclick="deleteMonitor(${monitor.id})">X</button>
            `;
            urlList.appendChild(li);
    
            if (monitor.status !== 'pending') {
                const time = monitor.last_checked_at ? new Date(monitor.last_checked_at).toLocaleTimeString('tr-TR') : '';
                const color = monitor.status === 'up' ? 'text-success' : 'text-danger';
                const logEntry = `<div><span class="text-secondary">[${time}]</span> <span class="${color}">[${monitor.status.toUpperCase()}]</span> ${monitor.name}</div>`;
                
                logContainer.insertAdjacentHTML('afterbegin', logEntry);
            }
            
        } catch (error) {
            console.error('Veri çekme hatası:', error);
        }
    });

}
addUrlBtn.addEventListener('click', async () => {
    console.log('aa')
    const url = newUrlInput.value.trim();
    if (!url) return;

    try {
        const response = await fetch('/api/monitors', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({
                name: new URL(url).hostname,
                url: url,
                check_interval: 1
            })
        });

        if (response.ok) {
            newUrlInput.value = "https://";
            fetchMonitors();
        }

    } catch (error) {
        console.error('Ekleme hatası:', error);
    }

    
});

timerSubmitBtn.addEventListener('click', () => {
    submittedTime = parseInt(timerInput.value) || 0; 
    timeRemaining = submittedTime; 
    console.log(`Timer ${submittedTime} saniyeye kuruldu.`);
});

startBtn.addEventListener('click', () => {
    isLoop = true;
    if (timeRemaining > 0) startTimer();
});

stopBtn.addEventListener('click', () => {
    isLoop = false;
    clearInterval(timerInterval);
    console.log("Timer durduruldu.");
});

window.deleteMonitor = async function(id) {
    if (!confirm('Bu siteyi takipten çıkarmak istediğine emin misin?')) return;
    
    try {
        const response = await fetch(`/api/monitors/${id}`, {
            method: 'DELETE',
            headers: { 'Accept': 'application/json' }
        });

        if (response.ok) {
            fetchMonitors();
        }
    } catch (error) {
        console.error('Silme işlemi başarısız:', error);
    }
};

fetchMonitors()