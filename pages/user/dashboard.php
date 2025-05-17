<?php
session_start();

require_once __DIR__ . '/../../config/config.php'; // Ensure this file sets up the $connection variable

$email = $date = $greeting = "N/A";
$total_products_sold = $total_sales = $total_cost = "0.00";



// Default to monthly data
$range = $_GET['range'] ?? 'month'; // Can be 'year', 'month', or 'week'

try {
    // Query for different ranges
    if ($range === 'year') {
        $sql = "
            SELECT
                IFNULL(SUM(s.sales_qty * p.price), 0) AS total_revenue
            FROM sales s
            JOIN products p ON s.product_id = p.id
            WHERE YEAR(s.sale_date) = YEAR(CURDATE())
        ";
    } elseif ($range === 'week') {
        $sql = "
            SELECT
                IFNULL(SUM(s.sales_qty * p.price), 0) AS total_revenue
            FROM sales s
            JOIN products p ON s.product_id = p.id
            WHERE WEEK(s.sale_date) = WEEK(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())
        ";
    } else {
        // Default to month
        $sql = "
            SELECT
                IFNULL(SUM(s.sales_qty * p.price), 0) AS total_revenue
            FROM sales s
            JOIN products p ON s.product_id = p.id
            WHERE MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())
        ";
    }
    
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);



} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
}

if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
    $username = htmlspecialchars($_SESSION["username"]);
    
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
    
            // Determine the time of day for personalized greeting
            $current_hour = (int)date('H');
            if ($current_hour < 12) {
                $time_of_day = "Morning";
            } elseif ($current_hour < 18) {
                $time_of_day = "Afternoon";
            } else {
                $time_of_day = "Evening";
            }
    
            // Personalized greeting
            $greeting = "Hi " . $username . ", Good " . $time_of_day;
        } else {
            // If no user data, fallback to guest greeting and default image
            $greeting = "Hello, Guest";
            $image_to_display = 'uploads/user/default.png';
        }
    } catch (PDOException $e) {
        // Handle database errors
        exit("Database error: " . $e->getMessage());
    } catch (Exception $e) {
        // Handle user not found or other exceptions
        exit("Error: " . $e->getMessage());
    }
    

    
}


try {
    // Calculate total revenue
    $sql = "
    SELECT
        IFNULL(SUM(s.sales_qty * p.price), 0) AS total_revenue
    FROM sales s
    JOIN products p ON s.product_id = p.id
    ";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_revenue = $result["total_revenue"];

    // Calculate total cost (cost of products sold)
    $sql = "
    SELECT
        IFNULL(SUM(s.sales_qty * p.cost), 0) AS total_cost
    FROM sales s
    JOIN products p ON s.product_id = p.id
    ";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_cost = $result["total_cost"];

    // Fetch total expenses from the expenses table
    $sql = "
    SELECT
        IFNULL(SUM(amount), 0) AS total_expenses
    FROM expenses
    ";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $total_expenses = $result["total_expenses"];

    // Calculate total expenses (product cost + other expenses)
    $total_expenses_combined = $total_cost + $total_expenses;

    // Calculate profit
    $total_profit = $total_revenue - $total_expenses_combined;

    // Calculate the percentage of total expenses combined compared to revenue
    $percentage_expenses_to_revenue = 0;  // Default value
    if ($total_revenue > 0) {
        // Total expenses combined divided by total revenue * 100
        $percentage_expenses_to_revenue = ($total_expenses_combined / $total_revenue) * 100;
    }

    // Calculate the percentage of total profit combined compared to revenue
    $percentage_profit_to_revenue = 0;  // Default value
    if ($total_revenue > 0) {
        // Total profit combined divided by total revenue * 100
        $percentage_profit_to_revenue = ($total_profit / $total_revenue) * 100;
    }

    // Format the final outputs for display
    $total_revenue = number_format($total_revenue, 2);
    $total_expenses_combined = number_format($total_expenses_combined, 2);
    $total_expenses = number_format($total_expenses, 2);
    $total_cost = number_format($total_cost, 2);
    $total_profit = number_format($total_profit, 2);
    $percentage_expenses_to_revenue = number_format($percentage_expenses_to_revenue,);
    $percentage_profit_to_revenue = number_format($percentage_profit_to_revenue,);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
    $total_profit = "0.00";
    $percentage_expenses_to_revenue = "0.00";
    $percentage_profit_to_revenue = "0.00";
}



$top_products = [];

try {
    $sql = "
    SELECT
        p.id,
        p.name,
        p.image_path,
        IFNULL(SUM(s.sales_qty), 0) AS total_sold
    FROM sales s
    JOIN products p ON s.product_id = p.id
    GROUP BY p.id, p.name, p.image_path
    ORDER BY total_sold DESC
    LIMIT 5
    ";
    $stmt = $connection->prepare($sql);
    $stmt->execute();
    $top_products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
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
    // Handle any errors during database queries
    echo "Error: " . $e->getMessage();
}

$connection = null;
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

      <title>Dashboard</title>
      
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
     <div class="container-fluid">
        <div class="row">
            <div class="col-lg-4">
                <div class="card card-transparent card-block card-stretch card-height border-none">
                    <div class="card-body p-0 mt-lg-2 mt-0">
                        <h3 class="mb-3"><?php echo $greeting; ?></h3>
                        <p class="mb-0 mr-4">Your dashboard delivers KPI for sales and inventory management, highlighting sales & income, net profit, and expenditures, which comprises of costs and expenses.</p>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="row">
                    <div class="col-lg-4 col-md-4">
                        <div class="card card-block card-stretch card-height">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-4 card-total-sale">
                                    <div class="icon iq-icon-box-2 bg-info-light">
                                        <img src="http://localhost:8000/assets/images/product/1.png" class="img-fluid" alt="image">
                                    </div>
                                    <div>
                                    <p class="mb-2">Revenue</p>
                                    <h4>$<?php echo $total_revenue; ?></h4>
                                    </div>
                                </div>                                
                                <div class="iq-progress-bar mt-2">
                                    <span class="bg-info iq-progress progress-1" data-percent="85">
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <div class="card card-block card-stretch card-height">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-4 card-total-sale">
                                    <div class="icon iq-icon-box-2 bg-danger-light">
                                        <img src="http://localhost:8000/assets/images/product/2.png" class="img-fluid" alt="image">
                                    </div>
                                    <div>
                                    <p class="mb-2">Total Cost</p>
                                    <h4>$<?php echo $total_cost; ?></h4>
                                    </div>
                                </div>
                                <div class="iq-progress-bar mt-2">
                                    <span class="bg-danger iq-progress progress-1" data-percent="70">
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-4">
                        <div class="card card-block card-stretch card-height">
                            <div class="card-body">
                                <div class="d-flex align-items-center mb-4 card-total-sale">
                                    <div class="icon iq-icon-box-2 bg-success-light">
                                        <img src="http://localhost:8000/assets/images/product/3.png" class="img-fluid" alt="image">
                                    </div>
                                    <div>
                                    <p class="mb-2">Profit</p>
                                    <h4><?php echo $total_profit; ?></h4>
                                    </div>
                                </div>
                                <div class="iq-progress-bar mt-2">
                                    <span class="bg-success iq-progress progress-1" data-percent="75">
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-block card-stretch card-height">
                    <div class="card-header d-flex justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">Sales</h4>
                           
                        </div>                        
                        <div class="card-header-toolbar d-flex align-items-center">
    
</div>

                    </div>                    
                    <div class="card-body">
                    <h4>Top Earners</h4>
                        <div id="am-layeredcolumn-chart" style="height: 400px;"></div>
                    </div> 
                </div>
            </div>
            <div class="col-lg-6">
                <div class="card card-block card-stretch card-height">
                    <div class="card-header d-flex align-items-center justify-content-between">
                        <div class="header-title">
                            <h4 class="card-title">Income</h4>
                        </div>
                        <div class="card-header-toolbar d-flex align-items-center">
                            
                        </div>
                    </div>
                    <div class="card-body">
                    <h4>Revenue vs Profit</h4>
                        <div id="am-columnlinr-chart" style="min-height: 400px;"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
            <div class="card card-block card-stretch card-height">
                <div class="card-header d-flex align-items-center justify-content-between">
                    <div class="header-title">
                        <h4 class="card-title">Top Products</h4>
                    </div>
                    <div class="card-header-toolbar d-flex align-items-center">
                        
                    </div>
                </div>
                <div class="card-body">
                <ul class="list-unstyled row top-product mb-0">
                    <?php if (!empty($top_products)): ?>
                        <?php foreach ($top_products as $sales): ?>
                            <li class="col-lg-3">
                                <div class="card card-block card-stretch card-height mb-0">
                                    <div class="card-body">
                                        <img src="<?php echo htmlspecialchars($sales['image_path']); ?>" class="style-img img-fluid m-auto p-3" alt="Product Image">
                                        <div class="style-text text-left mt-3">
                                            <h5 class="mb-1"><?php echo htmlspecialchars($sales['name']); ?></h5>
                                            <p class="mb-0"><?php echo number_format($sales['total_sold']) . ' Item'; ?></p>
                                        </div>
                                    </div>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    <?php else: ?>
            <li class="col-lg-12">
                <div class="card card-block card-stretch card-height mb-0">
                    <div class="card-body text-center">
                        <p>No products available.</p>
                    </div>
                </div>
            </li>
        <?php endif; ?>
    </ul>
</div>

            </div>
        </div>
        <div class="col-lg-4">  
    <div class="card card-transparent card-block card-stretch mb-4">
        <div class="card-header d-flex align-items-center justify-content-between p-0">
            <div class="header-title">
                <h4 class="card-title mb-0">Best Item All Time</h4>
            </div>
            <div class="card-header-toolbar d-flex align-items-center">
                <div><a href="http://localhost:8000/pages/sales/page-list-sale.php" class="btn btn-primary view-btn font-size-14">View All</a></div>
            </div>
        </div>
    </div>
    <?php foreach ($top_products as $item) { ?>
    <div class="card card-block card-stretch card-height-helf">
        <div class="card-body card-item-right">
            <div class="d-flex align-items-top">
                <div class="bg-warning-light rounded">
                    <img src="<?php echo $item['image_path']; ?>" class="style-img img-fluid m-auto" alt="image">
                </div>
                <div class="style-text text-left">
                    <h5 class="mb-2"><?php echo $item['name']; ?></h5>
                    <p class="mb-2">Total Sold : <?php echo number_format($item['total_sold']); ?></p>
                    <!-- Assuming you have a column for total_earned, otherwise you can calculate or remove this part -->
                    <!-- <p class="mb-0">Total Earned : $<?php echo number_format($item['total_earned'], 2); ?> M</p> -->
                </div>
            </div>
        </div>
    </div>
    <?php } ?>
</div>
<div class="col-lg-4">  
    <div class="card card-block card-stretch card-height-helf">
        <div class="card-body">
            <div class="d-flex align-items-top justify-content-between">
                <div class="">
                    <p class="mb-0">Net Profit</p>
                    <h5>$<?php echo $total_profit; ?></h5>
                </div>
                <div class="card-header-toolbar d-flex align-items-center">
                    
                </div>
            </div>
            <div id="layout1-chart-3" class="layout-chart-1"></div>
        </div>
    </div>
    <div class="card card-block card-stretch card-height-helf">
        <div class="card-body">
            <div class="d-flex align-items-top justify-content-between">
                <div class="">
                    <p class="mb-0">Expenses</p>
                    <h5>$<?php echo $total_expenses; ?></h5>
                </div>
                <div class="card-header-toolbar d-flex align-items-center">
                    
                </div>
            </div>
            <div id="layout1-chart-4" class="layout-chart-2"></div>
        </div>
    </div>
</div>
<div class="col-lg-8">  
    <div class="card card-block card-stretch card-height">
        <div class="card-header d-flex justify-content-between">
            <div class="header-title">
                <h4 class="card-title">Net Profit vs Expenditure</h4>
            </div>                        
            <div class="card-header-toolbar d-flex align-items-center">
                
            </div>
        </div> 
        <div class="card-body">
            <div class="d-flex flex-wrap align-items-center mt-2">
                <div class="d-flex align-items-center progress-order-left">
                    <div class="progress progress-round m-0 primary conversation-bar" data-percent="46">
                        <span class="progress-left">
                            <span class="progress-bar"></span>
                        </span>
                        <span class="progress-right">
                            <span class="progress-bar"></span>
                        </span>
                        <div class="progress-value text-primary">
                            <?php echo $percentage_expenses_to_revenue; ?>%
                        </div>
                    </div>
                    <div class="progress-value ml-3 pr-5 border-right">
                        <h5>$<?php echo $total_expenses_combined; ?></h5>
                        <p class="mb-0">Expenditure</p>
                    </div>
                </div>
                <div class="d-flex align-items-center ml-5 progress-order-right">
                    <div class="progress progress-round m-0 orange conversation-bar" data-percent="46">
                        <span class="progress-left">
                            <span class="progress-bar"></span>
                        </span>
                        <span class="progress-right">
                            <span class="progress-bar"></span>
                        </span>
                        <div class="progress-value text-secondary">
                            <?php echo $percentage_profit_to_revenue; ?>%
                        </div>
                    </div>
                    <div class="progress-value ml-3">
                        <h5>$<?php echo $total_profit; ?></h5>
                        <p class="mb-0">Net Profit</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body pt-0">
            <div id="layout1-chart-5"></div>
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
    <script async src="http://localhost:8000/assets/js/chart-custom1.js"></script>
    
    <!-- app JavaScript -->
    <script src="http://localhost:8000/assets/js/app.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
   
    <script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'invoice-form.php';
});
</script>
    
</body>
</html>