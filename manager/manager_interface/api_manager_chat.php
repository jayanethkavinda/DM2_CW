<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../donor/donor_interface/mongo_connect.php';
require_once 'db_connect.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_messages') {
    $donor_id = intval($_GET['donor_id'] ?? 0);
    if ($donor_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid donor ID']);
        exit;
    }

    $messages = MongoDataService::getChatMessages($donor_id);
    echo json_encode([
        'status' => 'success',
        'messages' => $messages,
        'mongo_active' => MongoDataService::isMongoDBActive()
    ]);
    exit;
}

if ($action === 'send_message') {
    $donor_id = intval($_POST['donor_id'] ?? 0);
    $donor_name = trim($_POST['donor_name'] ?? 'Donor');
    $message = trim($_POST['message'] ?? '');

    if ($donor_id <= 0 || empty($message)) {
        echo json_encode(['status' => 'error', 'message' => 'Message and Donor ID are required']);
        exit;
    }

    $result = MongoDataService::saveChatMessage($donor_id, $donor_name, 'Manager', $message);
    if ($result) {
        echo json_encode(['status' => 'success', 'message' => 'Reply sent successfully']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save message']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
