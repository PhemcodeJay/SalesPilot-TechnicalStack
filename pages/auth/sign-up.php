<?php
session_start();
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// Include the Composer autoloader
require_once __DIR__ . '/../../vendor/autoload.php';


use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

// Include the database connection settings
require_once __DIR__ . '/../../config/config.php';

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['signup'])) {
    // Initialize variables with default values
    $username = $password = $email = $confirmpassword = "";

    // Check if the keys exist in the $_POST array before accessing them
    if (isset($_POST["Username"])) {
        $username = htmlspecialchars($_POST["Username"]);
    }

    if (isset($_POST["Password"])) {
        $password = htmlspecialchars($_POST["Password"]);
    }

    if (isset($_POST["Email"])) {
        $email = htmlspecialchars($_POST["Email"]);
    }

    if (isset($_POST["confirmpassword"])) {
        $confirmpassword = htmlspecialchars($_POST["confirmpassword"]);
    }

    if (isset($_SESSION['id_user']) && !empty($_SESSION['id_user'])) {
        header("Location: reg-success.html");
        exit(); // Add exit to stop the script execution
    }

    // Create an instance of PHPMailer
    $mail = new PHPMailer(true);

    // Call the function to handle form submission
    handleFormSubmission($username, $password, $email, $confirmpassword, $connection, $mail);
}

// Function to handle form submission and insert data in the database
function handleFormSubmission($username, $password, $email, $confirmpassword, $connection, $mail)
{
    // Validate form data
    if (empty($username) || empty($password) || empty($email) || empty($confirmpassword)) {
        echo 'All fields are required!';
        return;
    }
    
    if (strlen($password) > 20 || strlen($password) < 5) {
        echo 'Password must be between 5 and 20 characters!';
        return;
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo 'Invalid email format!';
        return;
    }
    
    if (preg_match('/^[a-zA-Z0-9]+$/', $username) == 0) {
        echo 'Username can only contain letters and numbers!';
        return;
    }

    if ($password !== $confirmpassword) {
        echo 'Passwords do not match!';
        return;
    }

    // Check if Username or Email already exists
    $stmt = $connection->prepare('SELECT id FROM users WHERE username = ? OR email = ?');
    $stmt->execute([$username, $email]);

    if ($stmt->rowCount() > 0) {
        echo 'Username or Email already exists, please choose another!';
    } else {
        // Insert new user record
        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
        $activationCode = uniqid();

        $insertStmt = $connection->prepare('INSERT INTO users (username, password, email, confirmpassword) VALUES (?, ?, ?, ?)');
        if ($insertStmt->execute([$username, $passwordHash, $email, $confirmpassword])) {
            $userId = $connection->lastInsertId();

            // Insert activation code
            $expiresAt = date('Y-m-d H:i:s', strtotime('+1 day'));
            $activationStmt = $connection->prepare('INSERT INTO activation_codes (user_id, activation_code, expires_at) VALUES (?, ?, ?)');
            if ($activationStmt->execute([$userId, $activationCode, $expiresAt])) {
                // Send activation email
                try {
                    $mail->isSMTP();
                    $mail->Host = 'smtp.gmail.com';
                    $mail->Port = 465;
                    $mail->SMTPAuth = true;
                    $mail->Username = 'olphemie@gmail.com'; // Replace with your Gmail email
                    $mail->Password = 'kokochulo@1987#'; // Replace with your app password
                    $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;

                    $mail->setFrom('olphemie@gmail.com', 'SalesPilot');
                    $mail->addAddress($email);
                    $mail->Subject = 'Activate Your Account';
                    $mail->Body = 'Hello,<br>Click the link below to activate your account:<br><a href="http://localhost:8000/activate.php?token=' . $activationCode . '">Activate Account</a>';

                    if ($mail->send()) {
                        header("Location: reg-success.html"); // Redirect after sending activation email
                        exit(); // Add exit to stop the script execution
                    } else {
                        echo 'Error sending activation email: ' . $mail->ErrorInfo;
                    }
                } catch (Exception $e) {
                    echo 'Mailer Error: ' . $e->getMessage();
                }
            } else {
                echo 'Error inserting activation code into the database: ' . $activationStmt->errorInfo()[2];
            }
        } else {
            echo 'Error inserting user record into the database: ' . $insertStmt->errorInfo()[2];
        }
    }
}
?>




<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <title>Register</title>
      
      <!-- Favicon -->
      <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon.ico" />
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend-plugin.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend.css?v=1.0.0">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/remixicon/fonts/remixicon.css">  </head>
  <body class=" ">
    <!-- loader Start -->
    <div id="loading">
          <div id="loading-center">
          </div>
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
                                 <h2 class="mb-2">Sign Up</h2>
                                 <p>Create your SalesPilot account.</p>
                                 <form role="form" id="registrationForm" method="POST" action="sign-up.php" autocomplete="off">
                                    <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                                    <div class="input-group input-group-outline mb-3">
                                      <input type="text" class="form-control" id="Username" name="Username" placeholder="Enter your username" required>
                                    </div>
                                    <div class="input-group input-group-outline mb-3">
                                      <input type="password" class="form-control" id="Password" name="Password" placeholder="Enter your password" required>
                                    </div>
                                    <div class="input-group input-group-outline mb-3">
                                      <input type="password" class="form-control" id="confirmpassword" name="confirmpassword" placeholder="Confirm your password" required>
                                    </div>
                                    <div class="input-group input-group-outline mb-3">
                                      <input type="email" class="form-control" id="Email" name="Email" placeholder="Enter your email" required>
                                    </div>
                                    <div class="form-check form-check-info text-start ps-0">
                                      <input class="form-check-input" type="checkbox" value="" id="flexCheckDefault" required>
                                      <label class="form-check-label" for="flexCheckDefault">
                                        I agree to the <a href="javascript:;" class="text-dark font-weight-bolder">Terms and Conditions</a>
                                      </label>
                                    </div>
                                    <div class="text-center">
                                      <button type="submit" name="signup" class="btn btn-lg bg-gradient-primary btn-lg w-100 mt-4 mb-0">Sign Up</button>
                                    </div>
                                  </form>
                                  <div class="text-center">
                                  <button type="button" id="resetButton" class="btn bg-gradient-primary w-100 my-4 mb-2" onclick="window.location.href='http://localhost:8000/loginpage.php'">Login</button>

                                    </div>
                              </div>
                           </div>
                           <div class="col-lg-5 content-right">
                              <img src="http://localhost:8000/assets/images/login/01.png" class="img-fluid image-right" alt="">
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
    <script src="http://localhost:8000/assets/js/backend-bundle.min.js"></script>
    
    <!-- Table Treeview JavaScript -->
    <script src="http://localhost:8000/assets/js/table-treeview.js"></script>
    
    <!-- app JavaScript -->
    <script src="http://localhost:8000/assets/js/app.js"></script>
  </body>
</html>