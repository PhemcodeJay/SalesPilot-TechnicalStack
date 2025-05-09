<?php
// Start a session to handle CSRF token
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

// Initialize variables and error array
$name = $email = $phone = $message = '';
$errors = [];

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] === "POST") {
    // Verify CSRF token
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        echo "<div class='text-danger'>Invalid CSRF token.</div>";
        exit;
    }

    // Sanitize and assign input values
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    $phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
    $message = htmlspecialchars(trim($_POST['message'] ?? ''));

    // Validate inputs
    if (empty($name)) $errors[] = 'Name is required.';
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Valid email is required.';
    if (empty($phone)) $errors[] = 'Phone number is required.';
    if (empty($message)) $errors[] = 'Message is required.';

    // Process the form if no validation errors
    if (empty($errors)) {
        try {
            // Prepare the insert query with placeholders
            $stmt = $connection->prepare(
                "INSERT INTO contacts (name, email, phone, message) VALUES (:name, :email, :phone, :message)"
            );

            // Bind values to the placeholders
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':message', $message);

            // Execute the query and check for success
            if ($stmt->execute()) {
                // Set up PHPMailer for sending the email
                $mail = new PHPMailer(true);

                try {
                    // SMTP settings
                    $mail->isSMTP();
                    $mail->Host = 'smtp.ionos.com'; // Use your SMTP host
                    $mail->SMTPAuth = true;
                    $mail->Username = 'admin@cybertrendhub.store'; // SMTP username
                    $mail->Password = 'kokochulo@1987#'; // SMTP password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
                    $mail->Port = 587;

                    // Email settings
                    $mail->setFrom('admin@cybertrendhub.store', 'SalesPilot');
                    $mail->addAddress($email);
                    $mail->isHTML(true);
                    $mail->Subject = 'New Feedback Message';
                    $mail->Body = "
                        <p><strong>Name:</strong> $name</p>
                        <p><strong>Email:</strong> $email</p>
                        <p><strong>Phone:</strong> $phone</p>
                        <p><strong>Message:</strong></p>
                        <p>$message</p>
                    ";

                    // Send email
                    if ($mail->send()) {
                        echo "<div class='text-success'>Thank you for your message. We will get back to you shortly.</div>";
                    } else {
                        echo "<div class='text-danger'>Error: Could not send email. Please try again later.</div>";
                    }
                } catch (Exception $e) {
                    echo "<div class='text-danger'>Mailer error: {$e->getMessage()}</div>";
                }

                // Redirect after successful form submission
                echo "<script>setTimeout(() => { window.location.href = 'index.html'; }, 2000);</script>";
            } else {
                echo "<div class='text-danger'>Database error: Unable to save your message. Please try again later.</div>";
            }
        } catch (PDOException $e) {
            echo "<div class='text-danger'>Database error: " . htmlspecialchars($e->getMessage()) . "</div>";
        }
    } else {
        // Display validation errors
        foreach ($errors as $error) {
            echo "<div class='text-danger'>$error</div>";
        }
    }
}
?>

