<?php
// ==============================================================================
// 🍃 LifeLineConnect - MongoDB NoSQL Data Service Layer
// Direct Cloud Connection to MongoDB Atlas Cluster (Database: lifeline_connect)
// Handles: 1. Live Chat Messaging (chats collection)
//          2. Community Camp Reviews & Ratings (reviews collection)
// ==============================================================================

class MongoDataService {
    // 🌐 MongoDB Atlas Cloud Connection URI
    private static $connectionString = "mongodb+srv://kavindakavinda1017_db_user:s18BIH4IkseP4vz2@lifeline.vjevozc.mongodb.net/?retryWrites=true&w=majority&appName=Lifeline";
    
    private static $mongoManager = null;
    private static $databaseName = "lifeline_connect";
    private static $isMongoAvailable = false;
    private static $errorMessage = null;

    /**
     * 🔌 Initialize and verify connection to MongoDB Atlas Cloud Cluster
     */
    public static function init() {
        if (self::$mongoManager !== null) {
            return; // Already initialized
        }

        // Check if the native PHP MongoDB driver extension is loaded
        if (!class_exists('MongoDB\Driver\Manager')) {
            self::$isMongoAvailable = false;
            self::$errorMessage = "PHP MongoDB extension is not enabled.";
            return;
        }

        try {
            // Establish connection with 3000ms timeout
            self::$mongoManager = new MongoDB\Driver\Manager(self::$connectionString, [], ["connectTimeoutMS" => 3000]);
            
            // Execute Ping command to verify active cluster heartbeat
            $command = new MongoDB\Driver\Command(["ping" => 1]);
            self::$mongoManager->executeCommand(self::$databaseName, $command);
            self::$isMongoAvailable = true;
            self::$errorMessage = null;
        } catch (Exception $e) {
            self::$isMongoAvailable = false;
            self::$errorMessage = $e->getMessage();
        }
    }

    /**
     * 🟢 Check if MongoDB Atlas connection is currently active and healthy
     */
    public static function isMongoDBActive() {
        self::init();
        return self::$isMongoAvailable;
    }

    /**
     * ⚠️ Get connection error message if MongoDB fails
     */
    public static function getErrorMessage() {
        self::init();
        return self::$errorMessage;
    }


    // ==============================================================================
    // 💬 1. LIVE CHAT OPERATIONS ('chats' Collection)
    // ==============================================================================

    /**
     * 📥 Fetch chat message history for a specific donor
     * @param int $donorId
     * @return array
     */
    public static function getChatMessages($donorId) {
        self::init();
        
        if (!self::$isMongoAvailable || !self::$mongoManager) {
            return []; // Return empty array safely if database connection fails
        }

        try {
            // Filter by Donor ID and sort chronologically (oldest to newest)
            $filter = ['donor_id' => (int)$donorId];
            $options = ['sort' => ['created_at' => 1]];
            
            $query = new MongoDB\Driver\Query($filter, $options);
            $cursor = self::$mongoManager->executeQuery(self::$databaseName . ".chats", $query);
            
            $results = [];
            foreach ($cursor as $doc) {
                $results[] = (array)$doc;
            }
            return $results;
        } catch (Exception $e) {
            self::$errorMessage = $e->getMessage();
            return [];
        }
    }

    /**
     * 📤 Insert a new chat message into MongoDB 'chats' collection
     * @param int $donorId
     * @param string $donorName
     * @param string $senderRole ('Donor' or 'Manager')
     * @param string $message
     * @return bool
     */
    public static function saveChatMessage($donorId, $donorName, $senderRole, $message) {
        self::init();

        if (!self::$isMongoAvailable || !self::$mongoManager) {
            return false;
        }

        try {
            // Structured BSON Document
            $record = [
                'donor_id'    => (int)$donorId,
                'donor_name'  => $donorName,
                'sender_role' => $senderRole,
                'message'     => htmlspecialchars(trim($message)),
                'timestamp'   => date('Y-m-d H:i:s'),
                'created_at'  => time()
            ];

            $bulk = new MongoDB\Driver\BulkWrite();
            $bulk->insert($record);
            self::$mongoManager->executeBulkWrite(self::$databaseName . ".chats", $bulk);
            return true;
        } catch (Exception $e) {
            self::$errorMessage = $e->getMessage();
            return false;
        }
    }


    // ==============================================================================
    // ⭐ 2. CAMP REVIEWS OPERATIONS ('reviews' Collection)
    // ==============================================================================

    /**
     * 📥 Fetch all community reviews or reviews for a specific camp
     * @param int|null $campId
     * @return array
     */
    public static function getCampReviews($campId = null) {
        self::init();

        if (!self::$isMongoAvailable || !self::$mongoManager) {
            return []; // Return empty array safely if database connection fails
        }

        try {
            // Optional filter by camp_id; sort newest reviews first
            $filter = $campId ? ['camp_id' => (int)$campId] : [];
            $options = ['sort' => ['created_at' => -1]];
            
            $query = new MongoDB\Driver\Query($filter, $options);
            $cursor = self::$mongoManager->executeQuery(self::$databaseName . ".reviews", $query);
            
            $results = [];
            foreach ($cursor as $doc) {
                $results[] = (array)$doc;
            }
            return $results;
        } catch (Exception $e) {
            self::$errorMessage = $e->getMessage();
            return [];
        }
    }

    /**
     * 📤 Insert a new camp review into MongoDB 'reviews' collection
     * @param int $donorId
     * @param string $donorName
     * @param int $campId
     * @param string $campName
     * @param int $rating (1-5)
     * @param string $feedback
     * @return bool
     */
    public static function saveCampReview($donorId, $donorName, $campId, $campName, $rating, $feedback) {
        self::init();

        if (!self::$isMongoAvailable || !self::$mongoManager) {
            return false;
        }

        try {
            // Structured Review BSON Document
            $record = [
                'id'          => uniqid('rev_'),
                'donor_id'    => (int)$donorId,
                'donor_name'  => $donorName,
                'camp_id'     => (int)$campId,
                'camp_name'   => $campName,
                'rating'      => (int)$rating,
                'feedback'    => htmlspecialchars(trim($feedback)),
                'timestamp'   => date('Y-m-d H:i:s'),
                'created_at'  => time()
            ];

            $bulk = new MongoDB\Driver\BulkWrite();
            $bulk->insert($record);
            self::$mongoManager->executeBulkWrite(self::$databaseName . ".reviews", $bulk);
            return true;
        } catch (Exception $e) {
            self::$errorMessage = $e->getMessage();
            return false;
        }
    }
}
?>
