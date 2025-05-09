<?php
session_start();

// Generate CSRF token if not already set
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Include necessary files
require __DIR__ . '/vendor/autoload.php'; // Composer autoloader
require '../../PHPMailer/src/PHPMailer.php';
require '../../PHPMailer/src/SMTP.php';
require '../../PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

include('config.php');

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['request_reset'])) {
    // Validate CSRF token
    if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        echo 'CSRF token validation failed!';
        exit;
    }

    // Sanitize and validate email input
    $email = filter_var(trim($_POST["Email"]), FILTER_SANITIZE_EMAIL);

    if (empty($email)) {
        echo 'Email is required!';
        return;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'Invalid email format!';
        return;
    }

    // Check if the email exists in the database
    $stmt = $connection->prepare('SELECT id FROM users WHERE email = ?');
    $stmt->execute([$email]);

    if ($stmt->rowCount() === 0) {
        echo 'Email not found!';
        return;
    }

    $userId = $stmt->fetchColumn();
    $resetToken = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', strtotime('+1 hour'));

    // Insert reset token into the database
    $resetStmt = $connection->prepare('INSERT INTO password_resets (user_id, reset_code, expires_at) VALUES (?, ?, ?)');
    if ($resetStmt->execute([$userId, $resetToken, $expiresAt])) {
        try {
            // Send password reset email using PHPMailer
            $mail = new PHPMailer(true);
            $mail->isSMTP();
            $mail->Host = 'smtp.ionos.com';
            $mail->Port = 587;
            $mail->SMTPAuth = true;
            $mail->Username = 'admin@cybertrendhub.store'; // Ensure this is correct
            $mail->Password = 'kokochulo@1987#'; // Ensure this is correct
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            $mail->SMTPDebug = 2;  // Enable detailed debug output

            $mail->setFrom('admin@cybertrendhub.store', 'SalesPilot');
            $mail->addAddress($email);
            $mail->isHTML(true); // Enable HTML formatting
            $mail->Subject = 'Password Reset Request';
            $mail->Body = 'Click the link below to reset your password:<br><a href="https://salespilot.cybertrendhub.store/recoverpwd.php?token=' . $resetToken . '">Reset Password</a>';

            // Send email
            if ($mail->send()) {
                echo 'Password reset email sent!';
            } else {
                echo 'Failed to send email.';
            }
        } catch (Exception $e) {
            echo 'Mailer Error: ' . $e->getMessage(); // Display detailed error message
            error_log('Mailer Error: ' . $e->getMessage()); // Log errors for debugging
        }
    } else {
        echo 'Error saving reset token: ' . $resetStmt->errorInfo()[2];
    }
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Password Recovery</title>

    <!-- Favicon -->
    <link rel="shortcut icon" href="https://salespilot.cybertrendhub.store/assets/images/favicon.ico" />
    <link rel="stylesheet" href="https://salespilot.cybertrendhub.store/assets/css/backend-plugin.min.css">
    <link rel="stylesheet" href="https://salespilot.cybertrendhub.store/assets/css/backend.css?v=1.0.0">
    <link rel="stylesheet" href="https://salespilot.cybertrendhub.store/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="https://salespilot.cybertrendhub.store/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="https://salespilot.cybertrendhub.store/assets/vendor/remixicon/fonts/remixicon.css">
</head>
<body>
    <!-- loader Start -->
    <div id="loading">
        <div id="loading-center"></div>
    </div>
    <!-- loader END -->

    <div class="wrapper">
        <section class="login-content">
            <div class="container">
                <div class="row align-items-center justify-content-center height-self-center">
                    <div class="col-lg-8">
                        <div class="card auth-card">
                            <div class="card-body p-0">
                                <div class="d-flex align-items-center auth-content">
                                    <div class="col-lg-7 align-self-center">
                                        <div class="p-3">
                                            <h2 class="mb-2">Recover Password</h2>
                                            <p>Enter your email address and we'll send you an email with instructions to reset your password.</p>

                                            <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                                                <div class="row">
                                                    <div class="col-lg-12">
                                                        <div class="floating-label form-group">
                                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_token']); ?>">
                                                            <input class="floating-input form-control" type="email" name="Email" placeholder="Enter Your Email Address" required>
                                                            <label for="email">Email</label>
                                                        </div>
                                                    </div>
                                                </div>
                                                <button type="submit" class="btn btn-primary" name="request_reset">Reset</button>
                                            </form>
                                        </div>
                                    </div>
                                    <div class="col-lg-5 content-right">
                                        <img src="https://salespilot.cybertrendhub.store/assets/images/login/01.png" class="img-fluid image-right" alt="">
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>

    <!-- Backend Bundle JavaScript -->
    <script src="https://salespilot.cybertrendhub.store/assets/js/backend-bundle.min.js"></script>
    <script src="https://salespilot.cybertrendhub.store/assets/js/table-treeview.js"></script>
    <script src="https://salespilot.cybertrendhub.store/assets/js/app.js"></script>
</body>
</html>
