<?php
session_start();
require_once 'db_connect.php';
require_once __DIR__ . '/../../donor/donor_interface/mongo_connect.php';

// Check Manager Authentication
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role']) || !in_array($_SESSION['role'], ['Staff', 'Admin'])) {
    header("Location: login.php");
    exit;
}

$manager_email = $_SESSION['email'] ?? 'manager@lifeline.lk';
$manager_role = $_SESSION['role'] ?? 'Staff';

// Fetch all registered donors from Oracle
$donors = [];
$d_stmt = oci_parse($conn, "SELECT donor_id, full_name, blood_group, contact_no, gender FROM donors ORDER BY donor_id ASC");
oci_execute($d_stmt);
while ($row = oci_fetch_assoc($d_stmt)) {
    $donors[] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Donor Messages & Chat - LifeLineConnect</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Chat Container Layout */
        .chat-portal-layout {
            display: grid;
            grid-template-columns: 320px 1fr;
            gap: 20px;
            background: #FFFFFF;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            border: 1px solid var(--border-color);
            height: calc(100vh - 160px);
            min-height: 550px;
            overflow: hidden;
        }

        /* Left Pane: Donor Conversations List */
        .donor-list-pane {
            border-right: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
            background: #FAFCFE;
        }

        .donor-list-header {
            padding: 18px 20px;
            border-bottom: 1px solid var(--border-color);
            background: #FFFFFF;
        }

        .donor-list-header h3 {
            font-size: 16px;
            color: var(--primary-color);
            margin-bottom: 8px;
        }

        .donor-search-input {
            width: 100%;
            padding: 8px 12px;
            border-radius: 6px;
            border: 1.5px solid var(--border-color);
            font-size: 13px;
            margin-bottom: 0;
        }

        .donor-items-container {
            overflow-y: auto;
            flex: 1;
        }

        .donor-item {
            padding: 14px 18px;
            border-bottom: 1px solid #EEF2F6;
            cursor: pointer;
            transition: all 0.2s ease;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .donor-item:hover {
            background: #F1F5F9;
        }

        .donor-item.active {
            background: #FFEBEE;
            border-left: 4px solid var(--primary-color);
        }

        .donor-item-avatar {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #B71C1C;
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 15px;
            flex-shrink: 0;
        }

        .donor-item-info {
            flex: 1;
            min-width: 0;
        }

        .donor-item-name {
            font-weight: 600;
            font-size: 14px;
            color: var(--text-primary);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .donor-item-sub {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 2px;
        }

        /* Right Pane: Active Chat Room */
        .chat-room-pane {
            display: flex;
            flex-direction: column;
            background: #FFFFFF;
            height: 100%;
        }

        .chat-room-header {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: #FFFFFF;
        }

        .chat-room-header-left {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-messages-container {
            flex: 1;
            padding: 24px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 14px;
            background: #F8FAFC;
        }

        .chat-bubble {
            max-width: 70%;
            padding: 12px 16px;
            border-radius: 14px;
            font-size: 14px;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
        }

        .bubble-donor {
            align-self: flex-start;
            background: #FFFFFF;
            color: #1E293B;
            border: 1px solid #E2E8F0;
            border-bottom-left-radius: 2px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }

        .bubble-manager {
            align-self: flex-end;
            background: linear-gradient(135deg, #B71C1C, #880E4F);
            color: #FFFFFF;
            border-bottom-right-radius: 2px;
            box-shadow: 0 2px 5px rgba(183, 28, 28, 0.2);
        }

        .bubble-meta {
            font-size: 11px;
            margin-top: 4px;
            opacity: 0.75;
            display: flex;
            justify-content: space-between;
            gap: 8px;
        }

        .bubble-donor .bubble-meta {
            color: #64748B;
        }

        .bubble-manager .bubble-meta {
            color: #FECDD3;
        }

        .chat-input-container {
            padding: 16px 24px;
            border-top: 1px solid var(--border-color);
            background: #FFFFFF;
            display: flex;
            gap: 12px;
            align-items: center;
        }

        .chat-input-field {
            flex: 1;
            padding: 12px 16px;
            border: 1.5px solid var(--border-color);
            border-radius: 24px;
            font-size: 14px;
            outline: none;
            margin-bottom: 0 !important;
            transition: border-color 0.2s;
        }

        .chat-input-field:focus {
            border-color: var(--primary-color);
        }

        .chat-send-btn {
            width: auto;
            padding: 11px 24px;
            border-radius: 24px;
            font-weight: 600;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 6px;
            background: #B71C1C;
        }

        .empty-chat-state {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100%;
            color: #94A3B8;
            text-align: center;
            padding: 40px;
        }
    </style>
</head>
<body>

    <!-- Sidebar Navigation -->
    <div class="sidebar">
        <div class="sidebar-brand">
            <span class="brand-icon">&#129656;</span>
            <div>
                <h2>LifeLineConnect</h2>
                <small class="badge-role">Manager Panel</small>
            </div>
        </div>

        <div class="sidebar-user">
            <div class="user-avatar">M</div>
            <div class="user-details">
                <strong><?php echo htmlspecialchars($manager_role === 'Admin' ? 'Admin / Manager' : 'Blood Bank Manager'); ?></strong>
                <small><?php echo htmlspecialchars($manager_email); ?></small>
            </div>
        </div>

        <ul class="nav-menu">
            <li><a href="manager_dashboard.php" class="nav-link"><span class="nav-icon">&#128202;</span> Back to Dashboard</a></li>
            <li><a href="../../reports/index.php" class="nav-link"><span class="nav-icon">&#128196;</span> PL/SQL Business Reports</a></li>
            <li><a href="manager_chat.php" class="nav-link active"><span class="nav-icon">&#128172;</span> Donor Messages (Chat)</a></li>
            <li><a href="manager_camps.php" class="nav-link"><span class="nav-icon">&#127973;</span> Camps Management</a></li>
            <li><a href="manager_dashboard.php#inventory-section" class="nav-link"><span class="nav-icon">&#129514;</span> Blood Inventory</a></li>
            <li><a href="manager_dashboard.php#hospital-req" class="nav-link"><span class="nav-icon">&#127973;</span> Hospital Requests</a></li>
            <li class="nav-divider"></li>
            <li><a href="logout.php" class="nav-link" style="color: #FECDD3;"><span class="nav-icon">&#128682;</span> Log Out</a></li>
        </ul>
    </div>

    <!-- Main Content Area -->
    <div class="main-content">
        
        <!-- Header Bar -->
        <div class="header">
            <div>
                <h1>Donor Inquiries & Live Chat Support</h1>
                <p class="header-sub">Connected to MongoDB Atlas Cloud &bull; Real-time messaging with registered donors</p>
            </div>
            <div>
                <span class="btn-small btn-success" style="padding: 6px 14px; font-weight: bold;">&#9889; MongoDB Cloud Connected</span>
            </div>
        </div>

        <!-- Chat Portal Layout -->
        <div class="chat-portal-layout">
            
            <!-- Left Pane: Registered Donors -->
            <div class="donor-list-pane">
                <div class="donor-list-header">
                    <h3>Donors Conversation List</h3>
                    <input type="text" id="donorSearch" class="donor-search-input" placeholder="Search donor name..." onkeyup="filterDonors()">
                </div>
                
                <div class="donor-items-container" id="donorsContainer">
                    <?php if (empty($donors)): ?>
                        <div style="padding: 20px; text-align: center; color: #94A3B8; font-size: 13px;">No registered donors found.</div>
                    <?php else: ?>
                        <?php foreach ($donors as $index => $donor): ?>
                            <div class="donor-item <?php echo ($index === 0) ? 'active' : ''; ?>" 
                                 data-id="<?php echo $donor['DONOR_ID']; ?>"
                                 data-name="<?php echo htmlspecialchars($donor['FULL_NAME']); ?>"
                                 data-bg="<?php echo htmlspecialchars($donor['BLOOD_GROUP'] ?: 'N/A'); ?>"
                                 data-phone="<?php echo htmlspecialchars($donor['CONTACT_NO'] ?: 'N/A'); ?>"
                                 onclick="selectDonor(this)">
                                <div class="donor-item-avatar"><?php echo strtoupper(substr($donor['FULL_NAME'], 0, 1)); ?></div>
                                <div class="donor-item-info">
                                    <div class="donor-item-name"><?php echo htmlspecialchars($donor['FULL_NAME']); ?></div>
                                    <div class="donor-item-sub">ID: #<?php echo $donor['DONOR_ID']; ?> &bull; Blood: <strong><?php echo $donor['BLOOD_GROUP']; ?></strong></div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Right Pane: Active Chat Room -->
            <div class="chat-room-pane">
                
                <!-- Chat Room Header -->
                <div class="chat-room-header" id="chatRoomHeader">
                    <div class="chat-room-header-left">
                        <div class="user-avatar" id="activeAvatar" style="background:#B71C1C; color:white;">D</div>
                        <div>
                            <strong id="activeDonorName" style="font-size:16px; color: #1E293B;">Select a donor</strong>
                            <small id="activeDonorDetails" style="display:block; color: #64748B; font-size:12.5px;">Donor ID & Blood Group</small>
                        </div>
                    </div>
                    <div>
                        <span class="badge-role" style="background:#16A34A; font-size:10px;">Direct Channel</span>
                    </div>
                </div>

                <!-- Messages Stream -->
                <div class="chat-messages-container" id="chatMessagesBox">
                    <div class="empty-chat-state">
                        <span style="font-size: 40px; margin-bottom: 10px;">&#128172;</span>
                        <p>Select a donor from the list to view incoming messages and reply.</p>
                    </div>
                </div>

                <!-- Message Reply Form -->
                <form class="chat-input-container" id="chatSendForm" onsubmit="handleSendReply(event)">
                    <input type="text" id="replyMessageInput" class="chat-input-field" placeholder="Type reply message to donor..." autocomplete="off" required>
                    <button type="submit" class="btn chat-send-btn">
                        <span>Send Reply</span> &#10148;
                    </button>
                </form>

            </div>

        </div>

    </div>

    <script>
        let currentDonorId = null;
        let currentDonorName = "";
        let pollInterval = null;

        document.addEventListener('DOMContentLoaded', () => {
            // Select first donor automatically if exists
            const firstDonor = document.querySelector('.donor-item');
            if (firstDonor) {
                selectDonor(firstDonor);
            }
        });

        function selectDonor(element) {
            document.querySelectorAll('.donor-item').forEach(item => item.classList.remove('active'));
            element.classList.add('active');

            currentDonorId = element.getAttribute('data-id');
            currentDonorName = element.getAttribute('data-name');
            const bg = element.getAttribute('data-bg');
            const phone = element.getAttribute('data-phone');

            document.getElementById('activeDonorName').innerText = currentDonorName;
            document.getElementById('activeDonorDetails').innerText = `Donor ID: #${currentDonorId} | Blood Group: ${bg} | Phone: ${phone}`;
            document.getElementById('activeAvatar').innerText = currentDonorName.charAt(0).toUpperCase();

            // Load messages for this donor
            fetchMessages();

            // Set up real-time polling every 3 seconds
            if (pollInterval) clearInterval(pollInterval);
            pollInterval = setInterval(fetchMessages, 3000);
        }

        async function fetchMessages() {
            if (!currentDonorId) return;

            try {
                const response = await fetch(`api_manager_chat.php?action=get_messages&donor_id=${currentDonorId}`);
                const data = await response.json();

                if (data.status === 'success') {
                    renderMessages(data.messages);
                }
            } catch (err) {
                console.error("Error fetching messages:", err);
            }
        }

        function renderMessages(messages) {
            const container = document.getElementById('chatMessagesBox');
            if (!messages || messages.length === 0) {
                container.innerHTML = `
                    <div class="empty-chat-state">
                        <span style="font-size: 36px; margin-bottom: 8px;">&#128392;</span>
                        <p>No message history with <strong>${currentDonorName}</strong> yet.<br>You can send an initial message or wait for their inquiry.</p>
                    </div>
                `;
                return;
            }

            let html = '';
            messages.forEach(msg => {
                const isManager = (msg.sender_role === 'Manager');
                const bubbleClass = isManager ? 'bubble-manager' : 'bubble-donor';
                const senderTitle = isManager ? 'You (Manager)' : (msg.donor_name || 'Donor');
                const timeText = msg.timestamp || '';

                html += `
                    <div class="chat-bubble ${bubbleClass}">
                        <div>${escapeHtml(msg.message)}</div>
                        <div class="bubble-meta">
                            <span>${senderTitle}</span>
                            <span>${timeText}</span>
                        </div>
                    </div>
                `;
            });

            container.innerHTML = html;
            container.scrollTop = container.scrollHeight;
        }

        async function handleSendReply(e) {
            e.preventDefault();
            const input = document.getElementById('replyMessageInput');
            const message = input.value.trim();

            if (!message || !currentDonorId) return;

            try {
                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('donor_id', currentDonorId);
                formData.append('donor_name', currentDonorName);
                formData.append('message', message);

                const response = await fetch('api_manager_chat.php', {
                    method: 'POST',
                    body: formData
                });
                const data = await response.json();

                if (data.status === 'success') {
                    input.value = '';
                    fetchMessages();
                } else {
                    alert('Error sending reply: ' + (data.message || 'Unknown error'));
                }
            } catch (err) {
                console.error('Send message error:', err);
            }
        }

        function filterDonors() {
            const filter = document.getElementById('donorSearch').value.toLowerCase();
            const items = document.querySelectorAll('.donor-item');
            items.forEach(item => {
                const name = item.getAttribute('data-name').toLowerCase();
                if (name.includes(filter)) {
                    item.style.display = 'flex';
                } else {
                    item.style.display = 'none';
                }
            });
        }

        function escapeHtml(text) {
            const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' };
            return text.replace(/[&<>"']/g, m => map[m]);
        }
    </script>
</body>
</html>
