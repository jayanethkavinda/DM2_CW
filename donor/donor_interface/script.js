// ==========================================================================
// LifeLineConnect - Blood Donor Module JavaScript (Pure Vanilla JS)
// ==========================================================================

let chatInterval = null;

document.addEventListener('DOMContentLoaded', () => {
    // Check URL hash for direct tab navigation
    const hash = window.location.hash.replace('#', '');
    if (hash) {
        const targetLink = document.querySelector(`.nav-link[onclick*="${hash}"]`);
        if (targetLink) {
            showTab(hash, targetLink);
        }
    }

    // Load initial reviews if review feed is present
    if (document.getElementById('reviewsFeedBox')) {
        loadCampReviews();
    }
});

// --- Tab Switching ---
function showTab(tabId, element = null) {
    // Hide all tabs
    document.querySelectorAll('.content-tab').forEach(tab => {
        tab.classList.remove('active');
    });

    // Deactivate all nav links
    document.querySelectorAll('.nav-link').forEach(link => {
        link.classList.remove('active');
    });

    // Show target tab
    const target = document.getElementById(tabId);
    if (target) {
        target.classList.add('active');
    }

    // Activate nav link
    if (element) {
        element.classList.add('active');
    } else {
        const matchingLink = document.querySelector(`.nav-link[onclick*="${tabId}"]`);
        if (matchingLink) matchingLink.classList.add('active');
    }

    // Manage Chat Polling
    if (tabId === 'tab-chat') {
        loadChatMessages();
        if (!chatInterval) {
            chatInterval = setInterval(loadChatMessages, 3000);
        }
    } else {
        if (chatInterval) {
            clearInterval(chatInterval);
            chatInterval = null;
        }
    }

    // Load reviews if navigating to reviews tab
    if (tabId === 'tab-reviews') {
        loadCampReviews();
    }
}

// --- Auth Tabs (Login / Register) ---
function switchAuthTab(type) {
    const loginPane = document.getElementById('login-form-pane');
    const regPane = document.getElementById('register-form-pane');
    const tabBtns = document.querySelectorAll('.tab-btn');

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

// --- Alert Box Closer ---
function closeAlert() {
    const alertBox = document.getElementById('alertBox');
    if (alertBox) {
        alertBox.style.display = 'none';
    }
}

// --- Camps District Filter ---
function filterCampsByDistrict(districtName) {
    const rows = document.querySelectorAll('#campsTable tbody tr');
    rows.forEach(row => {
        const rowDistrict = row.getAttribute('data-district');
        if (districtName === 'ALL' || rowDistrict === districtName || !rowDistrict) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// --- Camps Search Filter ---
function searchCampsTable() {
    const input = document.getElementById('campSearchInput').value.toLowerCase();
    const rows = document.querySelectorAll('#campsTable tbody tr');

    rows.forEach(row => {
        const text = row.innerText.toLowerCase();
        if (text.includes(input)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
}

// --- Chat with Manager (MongoDB) ---
async function loadChatMessages() {
    const chatBox = document.getElementById('chatMessagesBox');
    if (!chatBox) return;

    try {
        const res = await fetch('api_chat.php?action=get_messages');
        const data = await res.json();

        if (data.status === 'success') {
            if (!data.messages || data.messages.length === 0) {
                chatBox.innerHTML = `
                    <div style="text-align: center; color: #94A3B8; padding: 40px 20px;">
                        <p style="font-size: 32px;">&#128172;</p>
                        <p>No messages yet. Send a message to start a conversation with the Blood Bank Manager!</p>
                    </div>
                `;
                return;
            }

            let html = '';
            data.messages.forEach(msg => {
                const isDonor = (msg.sender_role === 'Donor');
                const bubbleClass = isDonor ? 'chat-bubble-donor' : 'chat-bubble-manager';
                const senderTitle = isDonor ? 'You' : 'Blood Bank Manager';
                
                html += `
                    <div class="chat-bubble ${bubbleClass}">
                        <strong style="font-size: 11.5px; display: block; margin-bottom: 2px; opacity: 0.9;">${senderTitle}</strong>
                        <div>${msg.message}</div>
                        <span class="chat-meta">${msg.timestamp || ''}</span>
                    </div>
                `;
            });

            // Only auto-scroll if content changed
            if (chatBox.innerHTML !== html) {
                chatBox.innerHTML = html;
                chatBox.scrollTop = chatBox.scrollHeight;
            }
        }
    } catch (e) {
        console.error('Chat load error:', e);
    }
}

async function sendChatMessage(e) {
    e.preventDefault();
    const input = document.getElementById('chatInputMessage');
    const msg = input.value.trim();
    if (!msg) return;

    const formData = new FormData();
    formData.append('action', 'send_message');
    formData.append('message', msg);

    input.value = '';

    try {
        const res = await fetch('api_chat.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.status === 'success') {
            loadChatMessages();
        } else if (data.status === 'error') {
            alert(data.message || 'Error communicating with MongoDB Atlas Cloud Cluster.');
        }
    } catch (e) {
        console.error('Chat send error:', e);
        alert('Network Error: Could not reach chat server.');
    }
}

// --- Star Rating Handler ---
function setStarRating(val) {
    document.getElementById('selectedRating').value = val;
    const stars = document.querySelectorAll('#starRatingBox .star');
    stars.forEach((s, idx) => {
        if (idx < val) {
            s.classList.add('active');
        } else {
            s.classList.remove('active');
        }
    });
}

function setModalStar(val) {
    document.getElementById('modalRatingVal').value = val;
    const stars = document.querySelectorAll('#modalStarBox .star');
    stars.forEach((s, idx) => {
        if (idx < val) {
            s.classList.add('active');
        } else {
            s.classList.remove('active');
        }
    });
}

// --- Reviews Operations (MongoDB) ---
async function loadCampReviews(campId = null) {
    const feedBox = document.getElementById('reviewsFeedBox');
    if (!feedBox) return;

    try {
        let url = 'api_review.php?action=get_reviews';
        if (campId) url += `&camp_id=${campId}`;

        const res = await fetch(url);
        const data = await res.json();

        if (data.status === 'success') {
            if (!data.reviews || data.reviews.length === 0) {
                feedBox.innerHTML = '<p class="text-muted" style="padding: 20px 0; text-align: center;">No reviews submitted yet. Be the first to share your experience!</p>';
                return;
            }

            let html = '';
            data.reviews.forEach(r => {
                const starsCount = parseInt(r.rating) || 5;
                const starStr = '&#9733;'.repeat(starsCount) + '&#9734;'.repeat(5 - starsCount);

                html += `
                    <div class="review-item">
                        <div class="review-header">
                            <strong>${r.donor_name || 'Anonymous Donor'}</strong>
                            <span class="review-stars">${starStr}</span>
                        </div>
                        <span class="review-camp">&#127973; ${r.camp_name || 'Blood Donation Camp'}</span>
                        <p class="review-text">${r.feedback || ''}</p>
                        <span class="review-date">&#128197; ${r.timestamp || ''}</span>
                    </div>
                `;
            });

            feedBox.innerHTML = html;
        }
    } catch (e) {
        console.error('Review load error:', e);
    }
}

async function submitCampReview(e) {
    e.preventDefault();
    const campSelect = document.getElementById('review_camp_select');
    const ratingInput = document.getElementById('selectedRating');
    const feedbackInput = document.getElementById('reviewFeedbackText');

    const formData = new FormData();
    formData.append('action', 'add_review');
    formData.append('camp_id', campSelect.value);
    formData.append('rating', ratingInput.value);
    formData.append('feedback', feedbackInput.value);

    try {
        const res = await fetch('api_review.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            feedbackInput.value = '';
            loadCampReviews();
        } else {
            alert(data.message || 'Could not submit review.');
        }
    } catch (e) {
        console.error('Submit review error:', e);
    }
}

// --- Quick Modal Review ---
function openReviewModal(campId, campName) {
    document.getElementById('modalCampId').value = campId;
    document.getElementById('modalCampTitle').innerText = 'Rate ' + campName;
    document.getElementById('reviewModal').style.display = 'flex';
}

function closeReviewModal() {
    document.getElementById('reviewModal').style.display = 'none';
}

async function submitModalReview(e) {
    e.preventDefault();
    const campId = document.getElementById('modalCampId').value;
    const rating = document.getElementById('modalRatingVal').value;
    const feedback = document.getElementById('modalFeedbackText').value;

    const formData = new FormData();
    formData.append('action', 'add_review');
    formData.append('camp_id', campId);
    formData.append('rating', rating);
    formData.append('feedback', feedback);

    try {
        const res = await fetch('api_review.php', {
            method: 'POST',
            body: formData
        });
        const data = await res.json();
        if (data.status === 'success') {
            alert(data.message);
            closeReviewModal();
            document.getElementById('modalFeedbackText').value = '';
            loadCampReviews();
        } else {
            alert(data.message || 'Could not submit review.');
        }
    } catch (e) {
        console.error('Modal review submit error:', e);
    }
}
