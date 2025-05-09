<?php
session_start();

require_once __DIR__ . '/../../config/config.php'; // Ensure $connection is set up for the database

// Redirect logged-in users to the dashboard
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    header("Location: user-confirm.html");
    exit();
}

// Initialize error messages
$username_err = $password_err = $login_err = "";

// Handle form submission
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $username = isset($_POST["username"]) ? trim($_POST["username"]) : '';
    $password = isset($_POST["password"]) ? trim($_POST["password"]) : '';

    if (empty($username)) {
        $username_err = "Please enter your username.";
    }
    if (empty($password)) {
        $password_err = "Please enter your password.";
    }

    if (empty($username_err) && empty($password_err)) {
        try {
            $stmt = $connection->prepare('SELECT id, username, password FROM users WHERE username = ?');
            $stmt->execute([$username]);

            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                if (password_verify($password, $user['password'])) {
                    $_SESSION["loggedin"] = true;
                    $_SESSION["id_user"] = $user['id'];
                    $_SESSION["username"] = $user['username'];

                    header("Location: user-confirm.html");
                    exit();
                } else {
                    $login_err = "Invalid username or password.";
                }
            } else {
                $login_err = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            exit("Database error: " . $e->getMessage());
        }
    }
}

// Close database connection
$connection = null;
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login</title>
  <link rel="icon" type="image/png" href="http://localhost:8000/home_assets/img/favicon.png">
  <link href="https://fonts.googleapis.com/css?family=Roboto:300,400,500,700" rel="stylesheet">
  <link href="http://localhost:8000/home_assets/css/nucleo-icons.css" rel="stylesheet">
  <link href="http://localhost:8000/home_assets/css/material-dashboard.css?v=3.1.0" rel="stylesheet">
</head>

<body class="bg-gray-200">
  <div class="container position-sticky z-index-sticky top-0">
    <div class="row">
      <div class="col-12"></div>
    </div>
  </div>
  <main class="main-content mt-0">
    <div class="page-header align-items-start min-vh-100" style="background-image: url('https://images.unsplash.com/photo-1497294815431-9365093b7331?auto=format&fit=crop&w=1950&q=80');">
      <span class="mask bg-gradient-dark opacity-6"></span>
      <div class="container my-auto">
        <div class="row">
          <div class="col-lg-4 col-md-8 col-12 mx-auto">
            <div class="card z-index-0 fadeIn3 fadeInBottom">
              <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                <div class="bg-gradient-primary shadow-primary border-radius-lg py-3">
                  <h4 class="text-white text-center">Sign in</h4>
                </div>
              </div>
              <div class="card-body">
                <?php if (!empty($login_err)): ?>
                  <div class="alert alert-danger text-center"><?php echo $login_err; ?></div>
                <?php endif; ?>
                <form action="http://localhost:8000/pages/auth/loginpage.php" method="post" class="text-start">
                  <div class="input-group input-group-outline my-3">
                    <input type="text" name="username" class="form-control" placeholder="Username" value="<?php echo htmlspecialchars($username ?? '', ENT_QUOTES); ?>">
                    <small class="text-danger"><?php echo $username_err; ?></small>
                  </div>
                  <div class="input-group input-group-outline mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Password">
                    <small class="text-danger"><?php echo $password_err; ?></small>
                  </div>
                  <div class="form-check form-switch d-flex align-items-center mb-3">
                    <input class="form-check-input" type="checkbox" id="rememberMe">
                    <label class="form-check-label ms-3" for="rememberMe">Remember me</label>
                  </div>
                  <div class="text-center">
                    <button type="submit" name="login" class="btn bg-gradient-primary w-100">Sign in</button>
                    <button type="button" class="btn bg-gradient-primary w-100 mt-2" onclick="window.location.href='http://localhost:8000/recoverpwd.php'">Forgot Password</button>
                  </div>
                  <p class="mt-4 text-sm text-center">
                    Don't have an account?
                    <a href="http://localhost:8000/pages/auth/sign-up.php" class="text-primary font-weight-bold">Sign up</a>
                  </p>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
      <footer class="footer position-absolute bottom-2 py-2 w-100">
        <div class="container">
          <div class="row align-items-center justify-content-lg-between">
            <div class="col-12 col-md-6 text-center text-md-start">
              <p class="text-white mb-0">© <?php echo date('Y'); ?> Developed for <a href="https://phemcode.cybertrendhub.store/index.html" class="text-white">SalesPilot</a>.</p>
            </div>
          </div>
        </div>
      </footer>
    </div>
  </main>
  <script src="http://localhost:8000/home_assets/js/core/popper.min.js"></script>
  <script src="http://localhost:8000/home_assets/js/core/bootstrap.min.js"></script>
  <script src="http://localhost:8000/home_assets/js/plugins/smooth-scrollbar.min.js"></script>
</body>

</html>
