<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');// Includes the updated config.php with the $connection variable



try {
    // Check if username is set in session
    if (!isset($_SESSION["username"])) {
        throw new Exception("No username found in session.");
    }

    $username = htmlspecialchars($_SESSION["username"]);

    // Retrieve user information from the users table
    $user_query = "SELECT username, email, date FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        throw new Exception("User not found.");
    }

    // Retrieve user email and registration date
    $email = htmlspecialchars($user_info['email']);
    $date = htmlspecialchars($user_info['date']);
} catch (Exception $e) {
    // Handle user not logged in or user not found
    exit("Error: " . $e->getMessage());
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
} catch (PDOException $e) {
    // Handle database query errors
    error_log("Database Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
}

try {
    // Prepare and execute the query to fetch detailed user information
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
    // Handle other exceptions
    exit("Error: " . $e->getMessage());
}
?>



<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sales Analytics</title>
    <link rel="stylesheet" href="analysis.css">
    <!-- Favicon -->
    <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon-blue.ico" />
    <link rel="stylesheet" href="http://localhost:8000/assets/css/backend-plugin.min.css">
    <link rel="stylesheet" href="http://localhost:8000/assets/css/backend.css?v=1.0.0">
    <link rel="stylesheet" href="http://localhost:8000/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="http://localhost:8000/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="http://localhost:8000/assets/vendor/remixicon/fonts/remixicon.css"> 
    <script src="http://localhost:8000/asset/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    
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
<div class="dashboard">
    <!-- Control Panel -->
    <div class="control-panel">
        <h1 style="font-weight: bold; text-decoration: underline;">Analytics</h1>
    </div>
    <a href="analytics-report.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Reports</a>
    <a href="pdf_generate.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>PDF</a>
    <!-- Charts Grid -->
    <div class="charts-grid">

        <div class="card">
                  <div class="card-header d-flex justify-content-between">
                     <div class="header-title">
                        <h4 class="card-title">Product Metrics</h4>
                     </div>
                  </div>
                  <div class="card-header-toolbar d-flex align-items-center">
                            
                        </div>

                   <div class="card-body">
                     <div id="apex-basic" style="height: 400px;"></div>
                  </div>
               </div>
        
        <div class="card">
                  <div class="card-header d-flex justify-content-between">
                     <div class="header-title">
                        <h4 class="card-title">Inventory Metrics</h4>
                     </div>
                  </div>
                  <div class="card-header-toolbar d-flex align-items-center">
                            
                        </div>
                  <div class="card-body">
                     <div id="apex-line-area" style="height: 400px;"></div>
                  </div>
               </div> 


        
        <div class="card">
                  <div class="card-header d-flex justify-content-between">
                     <div class="header-title">
                        <h4 class="card-title">Revenue by Product</h4>
                     </div>
                  </div>
                  <div class="card-header-toolbar d-flex align-items-center">
                            
                        </div>
                  <div class="card-body">
                     <div id="am-3dpie-chart" style="height: 400px;"></div>
                  </div>
               </div>
               <div class="card">
                  <div class="card-header d-flex justify-content-between">
                     <div class="header-title">
                        <h4 class="card-title">Expenditure</h4>
                     </div>
                  </div>
                  <div class="card-header-toolbar d-flex align-items-center">
                            
                        </div>
                  <div class="card-body">
                     <div id="apex-column" style="height: 400px;"></div>
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


<!-- Chart Custom JavaScript -->
<script src="http://localhost:8000/assets/js/customizer.js"></script>
   
    <!-- Chart Custom JavaScript -->
    <script async src="http://localhost:8000/assets/js/chart-custom2.js"></script>
    

<!-- app JavaScript -->
<script src="http://localhost:8000/assets/js/app.js"></script>

<script src="http://localhost:8000/assets/js/apexcharts.js"></script>
<!-- Include AmCharts 4 core and charts -->
<script src="https://cdn.amcharts.com/lib/4/core.js"></script>
<script src="https://cdn.amcharts.com/lib/4/charts.js"></script>
<script src="https://cdn.amcharts.com/lib/4/themes/animated.js"></script>
<script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'invoice-form.php';
});
</script>

</body>
</html>