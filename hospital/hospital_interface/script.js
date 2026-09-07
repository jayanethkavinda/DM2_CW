// ==============================================================================
// LifeLineConnect - Hospital Portal JavaScript (Vanilla JS)
// ==============================================================================

document.addEventListener('DOMContentLoaded', () => {
    // Check URL hash for direct tab switching
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const targetLink = document.querySelector(`.nav-link[onclick*="${hash}"]`);
        if (targetLink) {
            showHospitalTab(hash, targetLink);
        }
    }
});

// --- Tab Switching ---
function showHospitalTab(tabId, element = null) {
    document.querySelectorAll('.content-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    const target = document.getElementById(tabId);
    if (target) {
        target.classList.add('active');
    }

    if (element) {
        element.classList.add('active');
    } else {
        const matchingLink = document.querySelector(`.nav-link[onclick*="${tabId}"]`);
        if (matchingLink) matchingLink.classList.add('active');
    }
}

// --- Auth Tabs (Login / Register) ---
function switchAuthTab(type) {
    const loginPane = document.getElementById('login-pane');
    const regPane = document.getElementById('register-pane');
    const tabBtns = document.querySelectorAll('.auth-tab-btn');

    if (type === 'login') {
        if (loginPane) loginPane.classList.add('active');
        if (regPane) regPane.classList.remove('active');
        if (tabBtns[0]) tabBtns[0].classList.add('active');
        if (tabBtns[1]) tabBtns[1].classList.remove('active');
    } else {
        if (loginPane) loginPane.classList.remove('active');
        if (regPane) regPane.classList.add('active');
        if (tabBtns[0]) tabBtns[0].classList.remove('active');
        if (tabBtns[1]) tabBtns[1].classList.add('active');
    }
}

// --- Status Filter for Hospital Requisitions ---
function filterRequestsByStatus(statusVal) {
    const rows = document.querySelectorAll('#hospitalRequestsTable tbody tr');
    rows.forEach(row => {
        const rowStatus = row.getAttribute('data-status');
        if (statusVal === 'ALL' || rowStatus === statusVal || !rowStatus) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// --- Search Requisitions Table ---
function searchRequestsTable() {
    const input = document.getElementById('requestSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#hospitalRequestsTable tbody tr');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// --- Alert Box Closer ---
function closeAlert() {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        alertBox.style.display = 'none';
    }
}
