<?php 

session_start([]);

// Include database connection
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


// Simulated exchange rates
$exchangeRates = [
    'USD' => 1,
    'KES' => 130,
    'NGN' => 1500,
];

// Fetch exchange rates
function getExchangeRates($baseCurrency = 'USD') {
    $apiKey = 'cc688057dc86274ff7958e5e'; // Replace with your API key
    $url = "https://v6.exchangerate-api.com/v6/{$apiKey}/latest/{$baseCurrency}";

    $response = file_get_contents($url);
    if ($response === FALSE) {
        return null;
    }

    $data = json_decode($response, true);
    return $data['result'] === 'success' ? $data['conversion_rates'] : null;
}

$conversionRates = getExchangeRates('USD') ?: $exchangeRates;

// Base prices and details in USD for each plan
$basePricingPlans = [
    'starter' => [
        'amount' => 5,
        'currency' => 'USD',
        'name' => 'Starter Plan',
        'details' => [
            'description' => 'Perfect for individuals or small startups just getting started.',
            'features' => [
                'Inventory Management',
                'Sales Management',
                'Invoices & Expenses',
                'Analytics & Reports',
                '24/7 Customer Service Support'
            ]
        ],
    ],
    'medium' => [
        'amount' => 15,
        'currency' => 'USD',
        'name' => 'Buisness Plan',
        'details' => [
            'description' => 'Great for small to medium-sized businesses looking to grow.',
            'features' => [
                'Inventory Management',
                'Sales Management',
                'Invoices & Expenses',
                'Analytics & Reports',
                'Customers, Staffs, Suppliers - Records',
                '24/7 Customer Service Support'
                
            ]
        ],
    ],
    'enterprise' => [
        'amount' => 25,
        'currency' => 'USD',
        'name' => 'Enterprise Plan',
        'details' => [
            'description' => 'Comprehensive solution for large businesses with advanced needs.',
            'features' => [
                'Inventory Management',
                'Sales Management',
                'Invoices & Expenses',
                'Analytics & Reports',
                'Customers, Staffs, Suppliers - Records',
                'Custom Integrations and Dedicated Support'
            ]
        ],
    ],
];

// Calculate prices in KES and NGN for each plan
$pricingPlans = [];
foreach ($basePricingPlans as $key => $plan) {
    $pricingPlans[$key] = [
        'name' => $plan['name'],
        'amount_USD' => $plan['amount'],
        'amount_KES' => round($plan['amount'] * $conversionRates['KES'], 2),
        'amount_NGN' => round($plan['amount'] * $conversionRates['NGN'], 2),
        'details' => $plan['details'],
    ];
}

// Set your PayPal client ID and secret
$clientId = 'Abq0Z652p0xd7LntfVIW3gTpX4buCF9UQUSnOH_EBcQzo0B2vrCRV_htZvOt-QCxb6kItlgT38pr1xPt';
$clientSecret = 'EFJotT-21CyvIuDvGfPKzsCk6g0iThtMfiaZmqnaW-FoPXTSBGpW1qm7t4iJX0yfhPFbEMBPMjKjAd_V';

// PayPal's webhook verification URL
$paypalWebhookUrl = 'https://api.paypal.com/v1/notifications/verify-webhook-signature';

// Webhook request body
$bodyReceived = file_get_contents('php://input');
$headers = getallheaders();

// PayPal sends headers for webhook verification
$authAlgo = $headers['paypal-auth-algo'];
$certUrl = $headers['paypal-cert-url'];
$transmissionId = $headers['paypal-transmission-id'];
$transmissionSig = $headers['paypal-transmission-sig'];
$timestamp = $headers['paypal-transmission-time'];

// Prepare the verification request payload
$verificationData = [
    'auth_algo' => $authAlgo,
    'cert_url' => $certUrl,
    'transmission_id' => $transmissionId,
    'transmission_sig' => $transmissionSig,
    'transmission_time' => $timestamp,
    'webhook_id' => 'YOUR_WEBHOOK_ID',
    'webhook_event' => json_decode($bodyReceived),
];

// Send the verification request to PayPal API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $paypalWebhookUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verificationData));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Authorization: Basic ' . base64_encode($clientId . ':' . $clientSecret),
]);

$response = curl_exec($ch);
curl_close($ch);

$verificationResponse = json_decode($response, true);

// If PayPal verifies the webhook, process the event
if ($verificationResponse['verification_status'] === 'SUCCESS') {
    $event = json_decode($bodyReceived, true);

    // Check the event type
    if ($event['event_type'] === 'PAYMENT.SALE.COMPLETED') {
        // Handle the payment confirmation
        $subscriptionId = $event['resource']['billing_agreement_id'];
        $payerId = $event['resource']['payer']['payer_info']['payer_id'];

        // Activate the subscription and send an email
        activateSubscription($connection, $subscriptionId, $payerId);
    }

    if ($event['event_type'] === 'BILLING.SUBSCRIPTION.CREATED') {
        // Handle the subscription creation
        $subscriptionId = $event['resource']['id'];
        $subscriberEmail = $event['resource']['subscriber']['email_address'];

        // Activate the subscription and send an email
        activateSubscription($connection, $subscriptionId, $subscriberEmail);
    }
} else {
    // Verification failed
    error_log('Webhook verification failed');
}

// Function to activate the subscription and send an email
function activateSubscription($connection, $subscriptionId, $payerId) {
    try {
        // Check if the subscription already exists
        $stmt = $connection->prepare("SELECT * FROM subscriptions WHERE subscription_id = :subscriptionId");
        $stmt->bindParam(':subscriptionId', $subscriptionId);
        $stmt->execute();

        // Email setup
        $subject = "Subscription Activated";
        $message = "Dear Subscriber,\n\nYour subscription (ID: $subscriptionId) has been activated successfully.\n\nThank you for your support!\n\nBest regards,\nYour Company";
        $headers = "From: no-reply@yourcompany.com";

        if ($stmt->rowCount() === 0) {
            // Insert new subscription
            $stmt = $connection->prepare(
                "INSERT INTO subscriptions (subscription_id, payer_id, status) 
                 VALUES (:subscriptionId, :payerId, 'active')"
            );
            $stmt->bindParam(':subscriptionId', $subscriptionId);
            $stmt->bindParam(':payerId', $payerId);
            $stmt->execute();

            // Send email
            mail($payerId, $subject, $message, $headers);
        } else {
            // Update existing subscription
            $stmt = $connection->prepare(
                "UPDATE subscriptions 
                 SET status = 'active' 
                 WHERE subscription_id = :subscriptionId"
            );
            $stmt->bindParam(':subscriptionId', $subscriptionId);
            $stmt->execute();

            // Send email
            mail($payerId, $subject, $message, $headers);
        }
    } catch (PDOException $e) {
        error_log('Database error: ' . $e->getMessage());
    }
}

?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SalesPilot - Pricing Plans</title>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <!-- Favicon -->
    <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon-blue.ico" />
    <style>
        body { font-family: Arial, sans-serif; background-color: #f4f7fa; color: #333; }
        .header { text-align: center; margin: 30px 0; }
        .header img { max-width: 100px; }
        .pricing-plan { background: #fff; border-radius: 8px; padding: 20px; box-shadow: 0 4px 8px rgba(0,0,0,0.1); transition: 0.3s; }
        .pricing-plan:hover { box-shadow: 0 8px 16px rgba(0,0,0,0.2); }
        .pricing-plan h3 { color: #007bff; font-weight: bold; }
        .pricing-plan .price { font-size: 24px; margin: 10px 0; }
        .pricing-plan ul { list-style: none; padding: 0; }
        .pricing-plan ul li { padding: 5px 0; }
        .btn-primary { background-color: #007bff; border: none; }
        .btn-warning { background-color: #ff9800; border: none; }
        .btn-success { background-color: #28a745; border: none; }
        .btn-info { background-color: #17a2b8; border: none; }
        .trial-container {
            text-align: center;
            margin-top: 50px;
        }
        .trial-button {
            padding: 10px 20px;
            font-size: 16px;
            background-color: #4CAF50;
            color: white;
            border: none;
            cursor: pointer;
            border-radius: 5px;
        }
        .trial-button:hover {
            background-color: #45a049;
        }
        .response-message {
            margin-top: 20px;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="header">
        <img src="http://localhost:8000/logo/logonew1.jpg" alt="Logo">
        <h1>Sales Pilot - Price and Plans</h1>
        <p>Select a plan that suits your needs and get started today!</p>
    </div>

    <div class="trial-container">
    <h2>Start Your Free 3-Month Trial!</h2>
    <button class="trial-button" onclick="activateTrial()">Activate Free Trial</button>
    <div id="responseMessage" class="response-message"></div>
    <a class="trial-button" href="http://localhost:8000/pages/dashboard.php">Back to Dashboard</a>
    </div>

    <div class="row">
    <?php foreach ($pricingPlans as $planKey => $plan): ?>
        <div class="col-md-4 mb-4">
            <div class="pricing-plan text-center">
                <h3><?= $plan['name'] ?></h3>
                <p class="price">USD: $<?= number_format($plan['amount_USD'], 2) ?></p>
                <p><strong>KES:</strong> <?= number_format($plan['amount_KES'], 2) ?></p>
                <p><strong>NGN:</strong> <?= number_format($plan['amount_NGN'], 2) ?></p>

                <p><?= $plan['details']['description'] ?></p>
                <ul>
                    <?php foreach ($plan['details']['features'] as $feature): ?>
                        <li><?= $feature ?></li>
                    <?php endforeach; ?>
                </ul>

                <!-- PayPal Button Container for each plan -->
                <div id="paypal-button-container-<?= $planKey ?>"></div>

                <!-- Payment Buttons for other options -->
                <button class="btn btn-warning" data-toggle="modal" data-target="#binanceModal" 
                        data-currency="USD" data-amount="<?= $plan['amount_USD'] ?>" 
                        data-payment="Binance Pay" data-info="Pay with Binance">
                    Binance Pay (USDT)
                </button>
                <button class="btn btn-success" data-toggle="modal" data-target="#mpesaModal" 
                        data-currency="KES" data-amount="<?= $plan['amount_KES'] ?>" 
                        data-payment="M-Pesa" data-info="Pay via M-Pesa">
                    M-Pesa (KES)
                </button>
                <button class="btn btn-info" data-toggle="modal" data-target="#ngnModal" 
                        data-currency="NGN" data-amount="<?= $plan['amount_NGN'] ?>" 
                        data-payment="Bank Transfer (NGN)" data-info="Bank Transfer (NGN)">
                    Bank Transfer (NGN)
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- PayPal SDK Script -->
<script src="https://www.paypal.com/sdk/js?client-id=AZYvY1lNRIJ-1uKK0buXQvvblKWefjilgca9HAG6YHTYkfFvriP-OHcrUZsv2RCohiWCl59FyvFUST-W&vault=true&intent=subscription" data-sdk-integration-source="button-factory"></script>

<script>
  // Function to render the PayPal button based on the selected plan ID
  function renderPaypalButton(planKey, planId) {
    // Destroy the previous PayPal button (if any)
    if (paypal.Buttons !== undefined) {
      paypal.Buttons().close();
    }

    // Render the PayPal button with the selected plan ID
    paypal.Buttons({
        style: {
            shape: 'rect',
            color: 'gold',
            layout: 'vertical',
            label: 'subscribe'
        },
        createSubscription: function(data, actions) {
            return actions.subscription.create({
                plan_id: planId  // Use the selected plan ID
            });
        },
        onApprove: function(data, actions) {
            alert('Subscription successful! Subscription ID: ' + data.subscriptionID);
        }
    }).render('#paypal-button-container-' + planKey);  // Renders the PayPal button inside the specified container
  }

  // Initial render for all plans with hardcoded plan IDs
  <?php foreach ($pricingPlans as $planKey => $plan): ?>
      // Render the PayPal button for each plan using the respective plan ID
      renderPaypalButton('<?= $planKey ?>', 'P-7E210255TM029860GM5HYC4A'); // Replace with actual plan ID for each plan
  <?php endforeach; ?>
</script>


<!-- Payment Modals -->
<div class="modal fade" id="binanceModal" tabindex="-1" aria-labelledby="binanceModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="binanceModalLabel">Binance Payment Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="payment-info"></p>
                <p><strong>Amount:</strong> <span id="payment-amount"></span> <span id="payment-currency"></span></p>
                <p><strong>BINANCE PAY ID:</strong> 128 320 436 </p>
                <p><strong>BINANCE USERNAME:</strong> Phemcode</p>
                <p><strong>Email:</strong> sales@cybertrendhub.store</p>
                
                <!-- Payment Proof Upload Section -->
                <form id="paymentProofForm" action="upload-payment-proof.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="payment-proof">Upload Payment Proof</label>
                        <input type="file" class="form-control" id="payment-proof" name="payment_proof" accept="image/*, .pdf" required>
                        <small class="form-text text-muted">Upload a screenshot or PDF of your payment proof.</small>
                    </div>
                    <input type="hidden" name="payment-method" value="binance">
                    <input type="hidden" name="amount" id="hidden-amount">
                    <input type="hidden" name="currency" id="hidden-currency">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" form="paymentProofForm" class="btn btn-primary">Confirm Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- M-Pesa Modal -->
<div class="modal fade" id="mpesaModal" tabindex="-1" aria-labelledby="mpesaModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="mpesaModalLabel">M-Pesa Payment Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="payment-info"></p>
                <p><strong>Amount:</strong> <span id="payment-amount"></span> <span id="payment-currency"></span></p>
                <script>
                // Example amount in a variable, replace this with your actual value
                const amount = 1234567.89;
                const currency = "KES";

                // Format the amount with commas and set the values in the HTML
                document.getElementById("payment-amount").textContent = amount.toLocaleString();
                document.getElementById("payment-currency").textContent = currency;
                </script>
                <p><strong>Mpesa name:</strong> OLUWAFEMI JEGEDE</p>
                <p><strong>Mpesa Number:</strong> +254 111 826 872</p>
                <p><strong>Email:</strong>sales@cybertrendhub.store</p>

                <!-- Payment Proof Upload Section -->
                <form id="mpesaPaymentProofForm" action="upload-payment-proof.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="mpesa-payment-proof">Upload Payment Proof</label>
                        <input type="file" class="form-control" id="mpesa-payment-proof" name="payment_proof" accept="image/*, .pdf" required>
                        <small class="form-text text-muted">Upload a screenshot or PDF of your payment proof.</small>
                    </div>
                    <input type="hidden" name="payment-method" value="mpesa">
                    <input type="hidden" name="amount" id="mpesa-hidden-amount">
                    <input type="hidden" name="currency" id="mpesa-hidden-currency">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" form="mpesaPaymentProofForm" class="btn btn-primary">Confirm Payment</button>
            </div>
        </div>
    </div>
</div>

<!-- NGN Bank Transfer Modal -->
<div class="modal fade" id="ngnModal" tabindex="-1" aria-labelledby="ngnModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="ngnModalLabel">Bank Transfer (NGN) Payment Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="payment-info"></p>
                <p><strong>Amount:</strong> <span id="payment-amount"></span> <span id="payment-currency"></span></p>
                <script>
                // Example amount in a variable, replace this with your actual value
                const amount1 = 1234567.89;
                const currency1 = "NGN";

                // Format the amount with commas and set the values in the HTML
                document.getElementById("payment-amount").textContent = amount.toLocaleString();
                document.getElementById("payment-currency").textContent = currency;
                </script>
                <p><strong>Bank Name:</strong> STANBIC IBTC Nigeria</p>
                <p><strong>Bank Account Number:</strong> 0141980198</p>
                <p><strong>Bank Account Name:</strong> JEGEDE OLUWAFEMI ADEGBOYE</p>
                <p><strong>Phone Number:</strong> +2348131365814</p>
                <p><strong>Email:</strong> sales@cybertrendhub.store</p>

                <!-- Payment Proof Upload Section -->
                <form id="ngnPaymentProofForm" action="upload-payment-proof.php" method="POST" enctype="multipart/form-data">
                    <div class="form-group">
                        <label for="ngn-payment-proof">Upload Payment Proof</label>
                        <input type="file" class="form-control" id="ngn-payment-proof" name="payment_proof" accept="image/*, .pdf" required>
                        <small class="form-text text-muted">Upload a screenshot or PDF of your payment proof.</small>
                    </div>
                    <input type="hidden" name="payment-method" value="bank-transfer-ngn">
                    <input type="hidden" name="amount" id="ngn-hidden-amount">
                    <input type="hidden" name="currency" id="ngn-hidden-currency">
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
                <button type="submit" form="ngnPaymentProofForm" class="btn btn-primary">Confirm Payment</button>
            </div>
        </div>
    </div>
</div>
<?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/footer.php' ; ?>

<script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $('#binanceModal, #mpesaModal, #ngnModal').on('show.bs.modal', function (event) {
        var button = $(event.relatedTarget);
        var currency = button.data('currency');
        var amount = button.data('amount');
        var paymentMethod = button.data('payment');
        var info = button.data('info');
        var modal = $(this);
        modal.find('.modal-title').text(paymentMethod);
        modal.find('.modal-body #payment-info').text(info);
        modal.find('.modal-body #payment-amount').text(amount);
        modal.find('.modal-body #payment-currency').text(currency);
    });
</script>
<script>
    function activateTrial() {
        // Show loading message while waiting for response
        document.getElementById("responseMessage").innerHTML = "Activating your free trial...";

        // Send a request to activate the free trial
        fetch('activate_trial.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: 'action=activate_trial'  // Send the action parameter
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                document.getElementById("responseMessage").innerHTML = "Your free trial has been activated successfully!";
            } else {
                document.getElementById("responseMessage").innerHTML = "Error activating trial: " + data.message;
            }
        })
        .catch(error => {
            document.getElementById("responseMessage").innerHTML = "An error occurred: " + error.message;
        });
    }
</script>

</body>
</html>
