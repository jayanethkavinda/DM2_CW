<?php
session_start();
header('Content-Type: application/json');
require_once 'mongo_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['donor_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$donor_id = $_SESSION['donor_id'];
$donor_name = $_SESSION['full_name'] ?? 'Donor #' . $donor_id;

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'get_messages') {
    $messages = MongoDataService::getChatMessages($donor_id);
    echo json_encode([
        'status' => 'success',
        'is_mongo' => MongoDataService::isMongoDBActive(),
        'messages' => $messages
    ]);
    exit;
}

if ($action === 'send_message' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $msg = trim($_POST['message'] ?? '');
    if (empty($msg)) {
        echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
        exit;
    }

    $saved = MongoDataService::saveChatMessage($donor_id, $donor_name, 'Donor', $msg);
    if (!$saved) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Error: Could not connect to MongoDB Atlas Cloud Cluster.'
        ]);
        exit;
    }

    // Provide automated manager response if this is a query to ensure interactive demonstration
    $msg_lower = strtolower($msg);
    if (strpos($msg_lower, 'camp') !== false || strpos($msg_lower, 'time') !== false || strpos($msg_lower, 'venue') !== false || strpos($msg_lower, 'hello') !== false || strpos($msg_lower, 'hi') !== false) {
        $reply = "Hello $donor_name, thank you for reaching out to the Blood Bank Manager. Our blood donation camps operate from 9:00 AM to 3:00 PM. Please check the Find Camps tab for upcoming venues near your district!";
        MongoDataService::saveChatMessage($donor_id, 'Blood Bank Manager', 'Manager', $reply);
    }

    $messages = MongoDataService::getChatMessages($donor_id);
    echo json_encode([
        'status' => 'success',
        'message' => 'Message sent successfully to MongoDB',
        'is_mongo' => MongoDataService::isMongoDBActive(),
        'messages' => $messages
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
