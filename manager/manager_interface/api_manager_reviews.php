<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../donor/donor_interface/mongo_connect.php';

$action = $_GET['action'] ?? $_POST['action'] ?? '';

if ($action === 'get_camp_reviews') {
    $camp_id = intval($_GET['camp_id'] ?? 0);
    if ($camp_id <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid camp ID']);
        exit;
    }

    $reviews = MongoDataService::getCampReviews($camp_id);
    
    // Calculate average rating
    $avg_rating = 0;
    if (!empty($reviews)) {
        $total_stars = 0;
        foreach ($reviews as $rev) {
            $total_stars += intval($rev['rating'] ?? 5);
        }
        $avg_rating = round($total_stars / count($reviews), 1);
    }

    echo json_encode([
        'status' => 'success',
        'camp_id' => $camp_id,
        'count' => count($reviews),
        'avg_rating' => $avg_rating,
        'reviews' => $reviews,
        'mongo_active' => MongoDataService::isMongoDBActive()
    ]);
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
?>
