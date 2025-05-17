<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');

try {
    // Check if username is set in session, redirect to login if not
    if (!isset($_SESSION["username"])) {
        header("Location: loginpage.php"); // Redirect to login page
        exit();
    }

    $username = htmlspecialchars($_SESSION["username"]);

    // Fetch inventory notifications with product images
    $inventoryQuery = $connection->prepare("
        SELECT i.product_name, i.available_stock, i.inventory_qty, i.sales_qty, p.image_path
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.available_stock < :low_stock OR i.available_stock > :high_stock
        ORDER BY i.last_updated DESC
    ");
    $inventoryQuery->execute([
        ':low_stock' => 10,
        ':high_stock' => 1000,
    ]);
    $inventoryNotifications = $inventoryQuery->fetchAll();

    // Fetch reports notifications with product images
    $reportsQuery = $connection->prepare("
        SELECT JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_name')) AS product_name, 
               JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) AS revenue,
               p.image_path
        FROM reports r
        JOIN products p ON JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_id')) = p.id
        WHERE JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) > :high_revenue 
           OR JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) < :low_revenue
        ORDER BY r.report_date DESC
    ");
    $reportsQuery->execute([
        ':high_revenue' => 10000,
        ':low_revenue' => 1000,
    ]);
    $reportsNotifications = $reportsQuery->fetchAll();

    // Retrieve user information from the users table
    $user_query = "SELECT id, username, email, date, phone, location, is_active, role, user_image FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        throw new Exception("User not found.");
    }

    // Retrieve and sanitize user details
    $email = htmlspecialchars($user_info['email']);
    $date = date('d F, Y', strtotime($user_info['date']));
    $location = htmlspecialchars($user_info['location']);
    $user_id = htmlspecialchars($user_info['id']);
    $existing_image = htmlspecialchars($user_info['user_image']);
    $image_to_display = !empty($existing_image) ? $existing_image : 'uploads/user/default.png';

    // Handle form submission for expenses (only on POST request)
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
            throw new Exception("User is not logged in.");
        }

        // Sanitize and validate form inputs
        $description = htmlspecialchars(trim($_POST['description']));
        $amount = floatval($_POST['amount']);
        $expense_date = htmlspecialchars(trim($_POST['expense_date']));
        $created_by = htmlspecialchars(trim($_POST['created_by']));

        if (empty($description) || empty($amount) || empty($expense_date) || empty($created_by)) {
            throw new Exception("All form fields are required.");
        }

        // Insert into expenses table
        $insert_expense_query = "INSERT INTO expenses (description, amount, expense_date, created_by) 
                                 VALUES (:description, :amount, :expense_date, :created_by)";
        $stmt = $connection->prepare($insert_expense_query);
        $stmt->bindParam(':description', $description);
        $stmt->bindParam(':amount', $amount);
        $stmt->bindParam(':expense_date', $expense_date);
        $stmt->bindParam(':created_by', $created_by);
        
        if ($stmt->execute()) {
            header('Location: page-list-expense.php');
            exit();
        } else {
            error_log("Expense insertion failed: " . implode(" | ", $stmt->errorInfo()));
            throw new Exception("Expense insertion failed.");
        }
    }
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    exit("Error: " . $e->getMessage());
}
?>


<!doctype html>
<html lang="en">
  <head>
    <!-- Google tag (gtag.js) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-TXR1WFJ4GP"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());

  gtag('config', 'G-TXR1WFJ4GP');
</script>

<meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

<meta content="" name="Boost your business efficiency with SalesPilot – the ultimate sales management app. Track leads, manage clients, and increase revenue effortlessly with our user-friendly platform.">
  <meta content="" name="Sales productivity tools, Sales and Client management, Business efficiency tools">

      <title>Add Expenses</title>
      
      <!-- Favicon -->
      <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon-blue.ico" />
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend-plugin.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend.css?v=1.0.0">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/remixicon/fonts/remixicon.css">  </head>
  <body class="  ">
    <!-- loader Start -->
    <div id="loading">
          <div id="loading-center">
          </div>
    </div>
    <!-- loader END -->
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
      </div>      <div class="content-page">
     <div class="container-fluid add-form-list">
        <div class="row">
            <div class="col-sm-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">Add Expense</h4>
                        </div>
                    </div>
                    <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" data-toggle="validator">
                        <div class="row">
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="expense_date">Expense Date *</label>
                                    <input type="date" class="form-control" id="expense_date" name="expense_date" required />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="description">Description *</label>
                                    <textarea class="form-control" id="description" name="description" rows="2" placeholder="Description" required></textarea>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="amount">Amount *</label>
                                    <input type="number" class="form-control" id="amount" name="amount" placeholder="Amount" required />
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="form-group">
                                    <label for="created_by">Created By *</label>
                                    <input type="text" class="form-control" id="created_by" name="created_by" placeholder="Created By" required />
                                </div>
                            </div>
                        </div>
                        <button type="submit" class="btn btn-primary mr-2">Add Expense</button>
                        <button type="reset" class="btn btn-danger">Reset</button>
                    </form>

                        
                    </div>
                </div>
            </div>
        </div>
        <!-- Page end  -->
    </div>
      </div>
    </div>
    <!-- Wrapper End-->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/footer.php' ; ?>

    <!-- Backend Bundle JavaScript -->
    <script src="http://localhost:8000/assets/js/backend-bundle.min.js"></script>
    
    <!-- Table Treeview JavaScript -->
    <script src="http://localhost:8000/assets/js/table-treeview.js"></script>
    
    <!-- app JavaScript -->
    <script src="http://localhost:8000/assets/js/app.js"></script>
    <script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'http://localhost:8000/pages/invoices/invoice-form.php';
});
</script>
  </body>
</html>