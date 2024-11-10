<?php

// Include the configuration file
require_once 'config.php';

/**
 * Update expired subscriptions based on the end date.
 *
 * @param PDO $db Database connection
 */
function updateExpiredSubscriptions($db) {
    $currentDate = date("Y-m-d");

    $query = "UPDATE subscriptions SET status = 'expired' WHERE end_date < :currentDate AND status = 'active'";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':currentDate', $currentDate);
    $stmt->execute();
}

/**
 * Check if a user's subscription is active.
 *
 * @param int $userId User ID
 * @param PDO $db Database connection
 * @return bool True if the subscription is active, false if expired
 */
function isSubscriptionActive($userId, $db) {
    $query = "SELECT status FROM subscriptions WHERE user_id = :userId";
    $stmt = $db->prepare($query);
    $stmt->bindParam(':userId', $userId, PDO::PARAM_INT);
    $stmt->execute();
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);

    return ($subscription && $subscription['status'] === 'active');
}

/**
 * Control user access based on subscription status.
 *
 * @param int $userId User ID
 * @param PDO $db Database connection
 */
function handleSubscriptionCheck($userId, $db) {
    if (!isSubscriptionActive($userId, $db)) {
        // Redirect to subscription renewal page if expired
        header("Location: /subscription.php");
        exit();
    } else {
        echo "Access granted";
    }
}

// Run the daily update to mark expired subscriptions
updateExpiredSubscriptions($db);

// Example usage for a specific user (replace $userId with actual user ID)
$userId = 1;
handleSubscriptionCheck($userId, $db);

?>