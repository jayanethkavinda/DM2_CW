<?php
session_start();
header('Content-Type: application/json');
require_once 'mongo_connect.php';
require_once 'db_connect.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['donor_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$donor_id = $_SESSION['donor_id'];
$donor_name = $_SESSION['full_name'] ?? 'Donor #' . $donor_id;

$action = $_GET['action'] ?? ($_POST['action'] ?? '');

if ($action === 'get_reviews') {
    $camp_id = isset($_GET['camp_id']) && $_GET['camp_id'] !== '' ? intval($_GET['camp_id']) : null;
    $reviews = MongoDataService::getCampReviews($camp_id);
    echo json_encode([
        'status' => 'success',
        'is_mongo' => MongoDataService::isMongoDBActive(),
        'reviews' => $reviews
    ]);
    exit;
}

if ($action === 'add_review' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $camp_id = intval($_POST['camp_id'] ?? 0);
    $rating = intval($_POST['rating'] ?? 5);
    $feedback = trim($_POST['feedback'] ?? '');

    if ($camp_id <= 0 || empty($feedback)) {
        echo json_encode(['status' => 'error', 'message' => 'Please select a camp and write your feedback.']);
        exit;
    }

    if ($rating < 1 || $rating > 5) {
        $rating = 5;
    }

    // Get Camp Name from Oracle
    $camp_name = "Blood Donation Camp";
    $c_stmt = oci_parse($conn, "SELECT camp_name, venue FROM camps WHERE camp_id = :p_cid");
    oci_bind_by_name($c_stmt, ":p_cid", $camp_id);
    oci_execute($c_stmt);
    if ($c_row = oci_fetch_assoc($c_stmt)) {
        $camp_name = $c_row['CAMP_NAME'] . ' (' . $c_row['VENUE'] . ')';
    }

    $saved = MongoDataService::saveCampReview($donor_id, $donor_name, $camp_id, $camp_name, $rating, $feedback);
    if (!$saved) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database Error: Could not connect to MongoDB Atlas Cloud Cluster to save review.'
        ]);
        exit;
    }

    $reviews = MongoDataService::getCampReviews();

    echo json_encode([
        'status' => 'success',
        'message' => 'Thank you! Your feedback has been submitted to MongoDB.',
        'is_mongo' => MongoDataService::isMongoDBActive(),
        'reviews' => $reviews
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
