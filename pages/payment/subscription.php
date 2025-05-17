<?php
session_start([]);

// Include database connection and PayPal SDK
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');

// Check if user is logged in
if (!isset($_SESSION["username"])) {
    header("Location: loginpage.php");
    exit;
}

$username = htmlspecialchars($_SESSION["username"]);

// Fetch user data
$user_query = "SELECT username, email, date, phone, location, user_image FROM users WHERE username = :username";
$stmt = $connection->prepare($user_query);
$stmt->bindParam(':username', $username);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    throw new Exception("User not found.");
}

$email = htmlspecialchars($user_info['email']);
$date = date('d F, Y', strtotime($user_info['date']));
$location = htmlspecialchars($user_info['location']);
$existing_image = htmlspecialchars($user_info['user_image']);
$image_to_display = !empty($existing_image) ? $existing_image : 'uploads/user/default.png';

// PayPal configuration
define('PAYPAL_CLIENT_ID', 'your_client_id_here');
define('PAYPAL_SECRET', 'your_secret_key_here');
define('PAYPAL_SANDBOX', true); // Set to false for production

// Set up PayPal API context
$apiContext = new \PayPal\Rest\ApiContext(
    new \PayPal\Auth\OAuthTokenCredential(
        PAYPAL_CLIENT_ID,
        PAYPAL_SECRET
    )
);

// Handle the subscription flow
if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'create_plan':
            createBillingPlan($apiContext);
            break;
        case 'create_agreement':
            createBillingAgreement($apiContext);
            break;
        case 'execute_subscription':
            executeSubscription($apiContext);
            break;
        default:
            echo "Invalid action.";
            break;
    }
} else {
    echo "<p>Welcome to the PayPal Subscription System. Please choose an action.</p>";
    echo "<a href='?action=create_plan'>Create Billing Plan</a><br/>";
    echo "<a href='?action=create_agreement'>Create Billing Agreement</a>";
}

// Create a Billing Plan for Starter, Business, and Enterprise plans
function createBillingPlan($apiContext) {
    // Define different pricing plans
    $plans = [
        'starter' => [
            'name' => 'Starter Subscription Plan',
            'description' => 'Basic monthly subscription with limited features.',
            'amount' => 10.00
        ],
        'business' => [
            'name' => 'Business Subscription Plan',
            'description' => 'Advanced features for small to medium businesses.',
            'amount' => 30.00
        ],
        'enterprise' => [
            'name' => 'Enterprise Subscription Plan',
            'description' => 'Full suite of enterprise-level features.',
            'amount' => 60.00
        ]
    ];

    // Loop through the plans to create them
    foreach ($plans as $planKey => $planData) {
        $plan = new \PayPal\Api\Plan();
        $plan->setName($planData['name'])
             ->setDescription($planData['description'])
             ->setType('INFINITE'); // INFINITE means no end date

        $paymentDefinition = new \PayPal\Api\PaymentDefinition();
        $paymentDefinition->setName('Monthly Payment')
                          ->setType('REGULAR')
                          ->setFrequency('Month')
                          ->setFrequencyInterval('1')
                          ->setAmount(new \PayPal\Api\Currency(array('value' => $planData['amount'], 'currency' => 'USD')))
                          ->setCycles('0'); // 0 means infinite payments

        $merchantPreferences = new \PayPal\Api\MerchantPreferences();
        $merchantPreferences->setReturnUrl("http://localhost/your-project/paypal_subscription.php?action=execute_subscription&success=true")
                            ->setCancelUrl("http://localhost/your-project/paypal_subscription.php?action=execute_subscription&success=false")
                            ->setAutoBillAmount('YES')
                            ->setInitialFailAmountAction('CONTINUE')
                            ->setMaxFailAttempts('0');

        $plan->setPaymentDefinitions(array($paymentDefinition))
             ->setMerchantPreferences($merchantPreferences);

        try {
            // Create the plan
            $plan->create($apiContext);
            echo "{$planData['name']} created successfully!<br>";
            echo "<a href='?action=create_agreement'>Create Billing Agreement for {$planData['name']}</a><br/>";
        } catch (Exception $ex) {
            logError("Error creating {$planData['name']}: " . $ex->getMessage());
            die("Error creating plan. Please try again later.");
        }
    }
}

// Create a Billing Agreement for the selected plan
function createBillingAgreement($apiContext) {
    $payer = new \PayPal\Api\Payer();
    $payer->setPaymentMethod('paypal');

    // Fetch the plan ID based on user selection (you can store this dynamically based on user choice)
    $plan = new \PayPal\Api\Plan();
    $plan->setId('P-0WL28690XY174544KPUF5RB4'); // This ID should be dynamically fetched for the selected plan

    $agreement = new \PayPal\Api\Agreement();
    $agreement->setName('Premium Subscription Agreement')
              ->setDescription('Agreement for Monthly Premium Subscription')
              ->setStartDate(gmdate("Y-m-d\TH:i:s\Z", time() + 60)); // Start in 1 minute

    $agreement->setPayer($payer);
    $agreement->setPlan($plan);

    try {
        // Create the agreement
        $agreement = $agreement->create($apiContext);
        // Redirect user to PayPal to approve the agreement
        $approvalUrl = $agreement->getApprovalLink();
        header("Location: $approvalUrl");
        exit;
    } catch (Exception $ex) {
        logError("Error creating agreement: " . $ex->getMessage());
        die("Error creating agreement. Please try again later.");
    }
}

// Execute Subscription after Approval
function executeSubscription($apiContext) {
    if (isset($_GET['success']) && $_GET['success'] == 'true' && isset($_GET['agreement_id'])) {
        $agreementId = $_GET['agreement_id'];

        // Get the agreement from PayPal
        try {
            $agreement = \PayPal\Api\Agreement::get($agreementId, $apiContext);
            $agreementExecution = $agreement->execute($apiContext);

            // Subscription activated logic: Confirm payment and activate subscription
            $userId = $_SESSION["user_id"]; // Assuming user_id is stored in session
            activateSubscription($userId);

            echo "Subscription activated successfully!<br>";
            echo "Agreement ID: " . $agreementExecution->getId();
        } catch (Exception $ex) {
            logError("Error executing subscription: " . $ex->getMessage());
            die("Error executing subscription. Please try again later.");
        }
    } else {
        echo "Subscription failed or was canceled.";
    }
}

// Activate the subscription in the database
function activateSubscription($userId) {
    global $connection;

    try {
        $query = "INSERT INTO subscriptions (user_id, subscription_plan, status) VALUES (?, ?, 'active')";
        $stmt = $connection->prepare($query);
        $stmt->bindParam(1, $userId, PDO::PARAM_INT);
        $stmt->bindParam(2, $planName, PDO::PARAM_STR);

        if ($stmt->execute()) {
            // Log subscription activation
            file_put_contents('logs/webhook_log.txt', "Subscription activated for User ID = $userId\n", FILE_APPEND);

            // Send email notification
            $userEmail = getUserEmailById($userId);
            $subject = "Subscription Activated";
            $message = "Dear User,\n\nYour subscription has been activated successfully.\n\nBest regards,\nYour Company";
            mail($userEmail, $subject, $message);
        } else {
            logError("Subscription insert failed.");
        }
    } catch (Exception $e) {
        logError("Error activating subscription: " . $e->getMessage());
    }
}

// Log error to log file
function logError($errorMessage) {
    file_put_contents('logs/error_log.txt', date("Y-m-d H:i:s") . " - " . $errorMessage . "\n", FILE_APPEND);
}
?>


<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <title>Subscriptions</title>
      
      <!-- Favicon -->
      <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon-blue.ico" />
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend-plugin.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend.css?v=1.0.0">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/remixicon/fonts/remixicon.css">  </head>
      <style>
        body {
            font-family: Arial, sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
            color: #333;
        }
        .container {
            width: 80%;
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: #fff;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
            border-radius: 8px;
        }
        h1 {
            text-align: center;
            color: #007BFF;
        }
        label {
            display: block;
            margin: 0.5rem 0 0.2rem;
            color: #555;
        }
        input, select {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }

        /* Modal styling */
  .modal-content {
      background-color: #fff;
      padding: 20px;
      border: 1px solid #888;
      width: 50%;
      margin: auto;
  }
        button {
            width: 100%;
            padding: 10px;
            background-color: #007BFF;
            color: white;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            cursor: pointer;
        }
        button:hover {
            background-color: #0056b3;
        }
        .form-group {
            margin-bottom: 1.5rem;
        }
        .form-group input, .form-group select {
            width: 100%;
        }
    </style>
    
      <body class="  ">
    
    <!-- Wrapper Start -->
    <div class="wrapper">
      
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/sidebar.php' ; ?>

   <?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/navbar.php' ; ?>

    <div class="modal fade" id="new-order" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-body">
                <div class="popup text-left">
                    <h4 class="mb-3">New Invoice</h4>
                    <div class="content create-workform bg-body">
                        <div class="pb-3">
                            <label class="mb-2">Name</label>
                            <input type="text" class="form-control" id="customerName" placeholder="Enter Customer Name">
                        </div>
                        <div class="col-lg-12 mt-4">
                            <div class="d-flex flex-wrap align-items-center justify-content-center">
                                <div class="btn btn-primary mr-4" data-dismiss="modal">Cancel</div>
                                <div class="btn btn-outline-primary" id="createButton">Create</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="container">
    <h1>Subscription</h1>
    <form id="paymentForm" method="post" action="">
    <div class="form-group">
        <label for="method">Payment Method:</label>
        <select id="method" name="method" required>
            <option value="PayPal">PayPal</option>
        </select>
    </div>

    <!-- Plan Selection -->
    <div class="form-group" id="planSelection">
        <label for="planSelect">Choose Your Plan:</label>
        <select id="planSelect" name="planSelect" required>
            <option value="P-7E210255TM029860GM5HYC4A">Enterprise</option>
            <option value="P-6TP94103DT2394623M5HYFKY">Business</option>
            <option value="P-92V01000GH171635WM5HYGRQ">Starter</option>
        </select>
    </div>

    <div id="paypal-button-container"></div>
</form>

<script src="https://www.paypal.com/sdk/js?client-id=AZYvY1lNRIJ-1uKK0buXQvvblKWefjilgca9HAG6YHTYkfFvriP-OHcrUZsv2RCohiWCl59FyvFUST-W&vault=true&intent=subscription"></script>
<script>
    // Function to dynamically render PayPal button based on selected plan
    function renderPayPalButton(planId) {
        paypal.Buttons({
            style: {
                shape: 'pill',
                color: 'gold',
                layout: 'vertical',
                label: 'subscribe'
            },
            createSubscription: function(data, actions) {
                return actions.subscription.create({
                    plan_id: planId // Use the selected plan ID
                });
            },
            onApprove: function(data, actions) {
                alert(`Subscription successful! ID: ${data.subscriptionID}`);
            }
        }).render('#paypal-button-container'); // Render PayPal button in this container
    }

    // Initial render for the default selected plan
    const planSelect = document.getElementById('planSelect');
    renderPayPalButton(planSelect.value);

    // Re-render PayPal button when the plan changes
    planSelect.addEventListener('change', function() {
        document.getElementById('paypal-button-container').innerHTML = ''; // Clear the previous button
        renderPayPalButton(this.value); // Render button for the newly selected plan
    });
</script>


    </div>
      </div>
    </div>
    <!-- Wrapper End-->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/sidebar.php' ; ?>
    
    <!-- Backend Bundle JavaScript -->
    <script src="http://localhost:8000/assets/js/backend-bundle.min.js"></script>
    
    <!-- Table Treeview JavaScript -->
    <script src="http://localhost:8000/assets/js/table-treeview.js"></script>
    
    <!-- app JavaScript -->
    <script src="http://localhost:8000/assets/js/app.js"></script>
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'http://localhost:8000/pages/invoices/invoice-form.php';
});

</script>
</body>
</html>
