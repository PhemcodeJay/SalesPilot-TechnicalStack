<?php 
session_start(); // Ensure session is started

require_once __DIR__ . '/config.php'; // Ensure correct path to config.php

// Check if username is set in session
if (!isset($_SESSION["username"])) {
    exit("No username found in session. Please log in.");
}

$username = htmlspecialchars($_SESSION["username"]);

try {
    // Step 1: Fetch the user's ID based on the username
    $query = "SELECT id FROM users WHERE username = ? LIMIT 1";
    $stmt = $connection->prepare($query);
    $stmt->execute([$username]);
    $user_data = $stmt->fetch();

    if (!$user_data) {
        exit("User not found in the database.");
    }

    $user_id = $user_data['id'];

    // Step 2: Fetch the user's subscription plan and trial end date
    $query = "SELECT subscription_plan, start_date, end_date 
              FROM subscriptions 
              WHERE user_id = ? AND status = 'active' LIMIT 1";
    $stmt = $connection->prepare($query);
    $stmt->execute([$user_id]);
    $user_subscription = $stmt->fetch();

    // Check if the user has a valid subscription
    if (!$user_subscription) {
        header("Location: dashboard.php"); // Redirect if no active subscription
        exit();
    }

    // Step 3: Check if the user is in their trial period
    $current_date = new DateTime();
    $trial_end_date = new DateTime($user_subscription['end_date']);

    if ($user_subscription['subscription_plan'] === 'trial' && $current_date <= $trial_end_date) {
        $user_subscription['subscription_plan'] = 'enterprise'; // Grant enterprise access during the trial period
    }

    // Step 4: Get the required access level for the current page
    $page_name = basename($_SERVER['PHP_SELF']);
    $query = "SELECT required_access_level FROM page_access WHERE page = ?";
    $stmt = $connection->prepare($query);
    $stmt->execute([$page_name]);
    $required_access_level = $stmt->fetchColumn();

    // Check if the page has a defined access level
    if (!$required_access_level) {
        header("Location: dashboard.php"); // Redirect if page access level is not defined
        exit();
    }

    // Step 5: Define subscription levels for comparison
    $access_levels = [
        'trial' => 4,       // Trial users have the highest priority during the trial period
        'starter' => 1,
        'business' => 2,
        'enterprise' => 3,
    ];

    // Step 6: Check if user's subscription level meets or exceeds the required access level
    if ($access_levels[$user_subscription['subscription_plan']] < $access_levels[$required_access_level]) {
        header("Location: dashboard.php"); // Redirect if access is not allowed
        exit();
    }

    // If the user has the required access, allow them to proceed with the page content
} catch (PDOException $e) {
    exit("Database error: " . $e->getMessage());
} catch (Exception $e) {
    exit("Error: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Access Level</title>
</head>
<body>

<h1>Welcome to the Premium Content Page</h1>
<p>This content is only accessible to users with the correct subscription level.</p>

<?php
// Dynamically display content based on the subscription plan
switch ($user_subscription['subscription_plan']) {
    case 'starter':
        include 'starter_content.php';
        break;
    case 'business':
        include 'business_content.php';
        break;
    case 'enterprise':
        include 'enterprise_content.php';
        break;
    default:
        echo "<p>No content available for your subscription level.</p>";
        break;
}
?>

</body>
</html>
