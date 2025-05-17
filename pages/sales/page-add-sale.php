<?php
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection

// Check if username is set in session
if (!isset($_SESSION["username"])) {
    die("No username found in session.");
}

$username = htmlspecialchars($_SESSION["username"]);

// Retrieve user information from the users table
$user_query = "SELECT id, username, email, date FROM users WHERE username = :username";
$stmt = $connection->prepare($user_query);
$stmt->bindParam(':username', $username);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user_info) {
    die("User not found.");
}

$email = htmlspecialchars($user_info['email']);
$date = htmlspecialchars($user_info['date']);
$user_id = $user_info['id'];

// Check if the user is logged in
if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        // Log session data for debugging
        error_log("Session ID: " . session_id());
        error_log("Session variables: " . print_r($_SESSION, true));

        // Sanitize and validate form inputs
        $name = htmlspecialchars(trim($_POST['name']));
        $sale_status = htmlspecialchars(trim($_POST['sale_status']));
        $sales_price = filter_var($_POST['sales_price'], FILTER_VALIDATE_FLOAT);
        $total_price = filter_var($_POST['total_price'], FILTER_VALIDATE_FLOAT);
        $sales_qty = filter_var($_POST['sales_qty'], FILTER_VALIDATE_INT);
        $payment_status = htmlspecialchars(trim($_POST['payment_status']));
        $sale_note = htmlspecialchars(trim($_POST['sale_note']));
        $staff_name = htmlspecialchars(trim($_POST['staff_name']));
        $customer_name = htmlspecialchars(trim($_POST['customer_name']));

        // Validate required fields
        if (empty($name) || empty($sale_status) || empty($staff_name) || empty($customer_name)) {
            die("Required fields are missing.");
        }

        

        try {
            $connection->beginTransaction();

            // Retrieve product_id from the products table
            $check_product_query = "SELECT id FROM products WHERE name = :name";
            $stmt = $connection->prepare($check_product_query);
            $stmt->bindParam(':name', $name);
            $stmt->execute();
            $product_id = $stmt->fetchColumn();

            if (!$product_id) {
                throw new Exception("Product not found.");
            }

            // Retrieve staff_id from the staffs table
            $check_staff_query = "SELECT staff_id FROM staffs WHERE staff_name = :staff_name";
            $stmt = $connection->prepare($check_staff_query);
            $stmt->bindParam(':staff_name', $staff_name);
            $stmt->execute();
            $staff_id = $stmt->fetchColumn();

            if (!$staff_id) {
                // Staff does not exist, so insert the new staff member
                $insert_staff_query = "INSERT INTO staffs (staff_name) VALUES (:staff_name)";
                $stmt = $connection->prepare($insert_staff_query);
                $stmt->bindParam(':staff_name', $staff_name);
                if ($stmt->execute()) {
                    // Get the last inserted staff_id
                    $staff_id = $connection->lastInsertId();
                } else {
                    throw new Exception("Failed to add new staff member.");
                }  
            };


            // Retrieve customer_id from the customers table
            $check_customer_query = "SELECT customer_id FROM customers WHERE customer_name = :customer_name";
            $stmt = $connection->prepare($check_customer_query);
            $stmt->bindParam(':customer_name', $customer_name);
            $stmt->execute();
            $customer_id = $stmt->fetchColumn();

            if (!$customer_id) {
                // Customer does not exist, so insert the new customer
                $insert_customer_query = "INSERT INTO customers (customer_name) VALUES (:customer_name)";
                $stmt = $connection->prepare($insert_customer_query);
                $stmt->bindParam(':customer_name', $customer_name);
                if ($stmt->execute()) {
                    // Get the last inserted customer_id
                    $customer_id = $connection->lastInsertId();
                } else {
                    throw new Exception("Failed to add new customer.");
                }
            }

            // SQL query for inserting into sales table
            $insert_sale_query = "INSERT INTO sales (product_id, name, staff_id, customer_id, total_price, sales_price, sales_qty, sale_note, sale_status, payment_status, user_id)
                                  VALUES (:product_id, :name, :staff_id, :customer_id, :total_price, :sales_price, :sales_qty, :sale_note, :sale_status, :payment_status, :user_id)";
            $stmt = $connection->prepare($insert_sale_query);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':staff_id', $staff_id);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':total_price', $total_price);
            $stmt->bindParam(':sales_price', $sales_price);
            $stmt->bindParam(':sales_qty', $sales_qty);
            $stmt->bindParam(':sale_note', $sale_note);
            $stmt->bindParam(':sale_status', $sale_status);
            $stmt->bindParam(':payment_status', $payment_status);
            $stmt->bindParam(':user_id', $user_id);

            // Execute and commit transaction
            if ($stmt->execute()) {
                $connection->commit();
                header('Location: page-list-sale.php');
                exit();
            } else {
                $connection->rollBack();
                die("Sale insertion failed.");
            }
        } catch (Exception $e) {
            $connection->rollBack();
            error_log("Error: " . $e->getMessage());
            die("Error: " . $e->getMessage());
        }
    }
} else {
    echo "Error: User not logged in.";
}

try {
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
    $inventoryNotifications = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch reports notifications with product images
    $reportsQuery = $connection->prepare("
        SELECT JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_name')) AS product_name, 
               JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) AS revenue,
               p.image_path
        FROM reports r
        JOIN products p ON JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_name')) = p.name
        WHERE JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) < :low_revenue OR 
              JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) > :high_revenue
        ORDER BY r.report_date DESC
    ");
    $reportsQuery->execute([
        ':low_revenue' => 1000,
        ':high_revenue' => 5000,
    ]);
    $reportsNotifications = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
    echo "Error: " . $e->getMessage();
    exit();
}

try {
    // Prepare and execute the query to fetch user information from the users table
    $user_query = "SELECT id, username, date, email, phone, location, is_active, role, user_image FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    // Fetch user data
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        // Retrieve user details and sanitize output
        $email = htmlspecialchars($user_info['email']);
        $date = date('d F, Y', strtotime($user_info['date']));
        $location = htmlspecialchars($user_info['location']);
        $user_id = htmlspecialchars($user_info['id']);
        
        // Check if a user image exists, use default if not
        $existing_image = htmlspecialchars($user_info['user_image']);
        $image_to_display = !empty($existing_image) ? $existing_image : 'uploads/user/default.png';

    }
} catch (PDOException $e) {
    // Handle database errors
    exit("Database error: " . $e->getMessage());
} catch (Exception $e) {
    // Handle user not found or other exceptions
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

      <title>Add Sales</title>
      
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
                            <h4 class="card-title">Add Sale</h4>
                        </div>
                    </div>
                    <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST" enctype="multipart/form-data" data-toggle="validator">
    
                    <div class="row">
                    <!-- Product Name -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="name">Product Name *</label>
                            <input type="text" id="name" name="name" class="form-control" placeholder="Enter Product Name" required>
                            <div class="help-block with-errors"></div>
                        </div>
                    </div>

                    <!-- Price -->
                    <div class="col-md-6">
                      <div class="form-group">
                          <label for="sales_price">Sales Price *</label>
                          <input type="number" id="sales_price" name="sales_price" class="form-control" placeholder="Enter Unit Price" required step="0.01" min="0">
                          <div class="help-block with-errors"></div>
                      </div>
                  </div>

                    <!-- Customer Name -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="customer_name">Customer *</label>
                            <input type="text" id="customer_name" name="customer_name" class="form-control" placeholder="Enter Customer Name" required>
                            <div class="help-block with-errors"></div>
                        </div>
                    </div>

                    <!-- Staff Name -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="staff_name">Staff *</label>
                            <input type="text" id="staff_name" name="staff_name" class="form-control" placeholder="Enter Staff Name" required>
                        </div>
                    </div>

                    <!-- Sales Quantity -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sales_qty">Sales Qty</label>
                            <input type="number" id="sales_qty" name="sales_qty" class="form-control" placeholder="Sales Qty" min="0" required>
                        </div>
                    </div>

                    <!-- Total Price -->
                    <div class="col-md-6">
                    <div class="form-group">
                        <label for="total_price">Total Price</label>
                        <input type="number" id="total_price" name="total_price" class="form-control" placeholder="Total Price" min="0" required readonly>
                    </div>
                </div>

                    

                    <!-- Sale Status -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="sale_status">Sale Status *</label>
                            <select id="sale_status" name="sale_status" class="form-control" required>
                                <option value="Completed">Completed</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                    </div>

                    <!-- Payment Status -->
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="payment_status">Payment Status *</label>
                            <select id="payment_status" name="payment_status" class="form-control" required>
                                <option value="Pending">Pending</option>
                                <option value="Due">Due</option>
                                <option value="Paid">Paid</option>
                            </select>
                        </div>
                    </div>

                    <!-- Sale Note -->
                    <div class="col-md-12">
                        <div class="form-group">
                            <label for="sale_note">Sale Note</label>
                            <textarea id="sale_note" name="sale_note" class="form-control" placeholder="Additional sale notes (optional)" rows="2"></textarea>
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn btn-primary mr-2">Add Sale</button>
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
    window.location.href = 'invoice-form.php';
});
</script>
<script>
    document.getElementById('sales_qty').addEventListener('input', calculateTotalPrice);
    document.getElementById('sales_price').addEventListener('input', calculateTotalPrice);

    function calculateTotalPrice() {
        const qty = parseFloat(document.getElementById('sales_qty').value) || 0;
        const price = parseFloat(document.getElementById('sales_price').value) || 0;
        const total = qty * price;
        document.getElementById('total_price').value = total.toFixed(2); // Formats to 2 decimal places
    }
</script>
  </body>
</html>