<?php
session_start([]);

require_once __DIR__ . '/../../config/config.php';  // Includes database connection



// Check if username is set in session
if (!isset($_SESSION["username"])) {
    exit("Error: No username found in session.");
}

$username = htmlspecialchars($_SESSION["username"]);

// Retrieve user information from the users table
$user_query = "SELECT username, email, date FROM users WHERE username = :username";
$stmt = $connection->prepare($user_query);
$stmt->bindParam(':username', $username);
$stmt->execute();
$user_info = $stmt->fetch(PDO::FETCH_ASSOC);



if (!$user_info) {
    exit("Error: User not found.");
}

// Retrieve user email and registration date
$email = htmlspecialchars($user_info['email']);
$date = htmlspecialchars($user_info['date']);

// Calculate metrics for each product category
$sales_category_query = "
    SELECT 
        categories.category_name AS category_name,
        COUNT(products.id) AS num_products,
        SUM(sales.sales_qty * products.price) AS total_sales,
        SUM(sales.sales_qty) AS total_quantity,
        SUM(sales.sales_qty * (products.price - products.cost)) AS total_profit,
        SUM(sales.sales_qty * products.cost) AS total_expenses,
        (SUM(products.price) / NULLIF(SUM(products.cost), 0)) * 100 AS sell_through_rate -- Adding sell-through rate
    FROM products
    INNER JOIN categories ON products.category_id = categories.category_id
    LEFT JOIN sales ON sales.product_id = products.id
    GROUP BY categories.category_name";
$stmt = $connection->query($sales_category_query);
$sales_category_data = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Initialize metrics for the entire report
$total_sales = 0;
$total_quantity = 0;
$total_profit = 0;

foreach ($sales_category_data as $category) {
    $total_sales += $category['total_sales'];
    $total_quantity += $category['total_quantity'];
    $total_profit += $category['total_profit'];
}


// Additional calculations for the report table
$revenue_by_category = json_encode($sales_category_data);
$gross_margin = $total_sales - $total_profit;
$net_margin = $total_profit;  // Assuming total profit represents net margin
$inventory_turnover_rate = ($total_quantity > 0) ? ($total_sales / $total_quantity) : 0;
$stock_to_sales_ratio = ($total_sales > 0) ? ($total_quantity / $total_sales) * 100 : 0;
$sell_through_rate = ($total_quantity > 0) ? ($total_sales / $total_quantity) / 100 : 0;

// Fetch previous year's revenue for year-over-year growth calculation
$previous_year_date = date('Y-m-d', strtotime($date . ' -1 year'));
$previous_year_revenue_query = "
    SELECT revenue FROM sales_analytics WHERE date = :previous_year_date";
$stmt = $connection->prepare($previous_year_revenue_query);
$stmt->bindParam(':previous_year_date', $previous_year_date);
$stmt->execute();
$previous_year_data = $stmt->fetch(PDO::FETCH_ASSOC);

$previous_year_revenue = $previous_year_data ? $previous_year_data['revenue'] : 0;

// Calculate Year-Over-Year Growth
$year_over_year_growth = ($previous_year_revenue > 0) ? 
    (($total_sales - $previous_year_revenue) / $previous_year_revenue) * 100 : 0;

// Check if a report for the current date already exists
$check_report_query = "SELECT id FROM sales_analytics WHERE date = :date";
$stmt = $connection->prepare($check_report_query);
$stmt->bindParam(':date', $date);
$stmt->execute();
$existing_report = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_report) {
    // Update existing report
    $update_query = "
        UPDATE sales_analytics
        SET 
            revenue = :revenue,
            profit_margin = :profit_margin,
            revenue_by_category = :revenue_by_category,
            year_over_year_growth = :year_over_year_growth,
            inventory_turnover_rate = :inventory_turnover_rate,
            stock_to_sales_ratio = :stock_to_sales_ratio,
            sell_through_rate = :sell_through_rate,
            gross_margin = :gross_margin,
            net_margin = :net_margin,
            total_sales = :total_sales,
            total_quantity = :total_quantity,
            total_profit = :total_profit
        WHERE id = :id";
    $stmt = $connection->prepare($update_query);
    $stmt->execute([
        ':revenue' => $total_sales,
        ':profit_margin' => ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0,
        ':revenue_by_category' => $revenue_by_category,
        ':year_over_year_growth' => $year_over_year_growth,
        ':inventory_turnover_rate' => $inventory_turnover_rate,
        ':stock_to_sales_ratio' => $stock_to_sales_ratio,
        ':sell_through_rate' => $sell_through_rate,
        ':gross_margin' => $gross_margin,
        ':net_margin' => $net_margin,
        ':total_sales' => $total_sales,
        ':total_quantity' => $total_quantity,
        ':total_profit' => $total_profit,
        ':id' => $existing_report['id']
    ]);
} else {
    // Insert new report
    $insert_query = "
        INSERT INTO sales_analytics (
            date, revenue, profit_margin, revenue_by_category, year_over_year_growth,
            inventory_turnover_rate, stock_to_sales_ratio, sell_through_rate,
            gross_margin, net_margin, total_sales, total_quantity, total_profit
        ) VALUES (
            :date, :revenue, :profit_margin, :revenue_by_category, :year_over_year_growth,
            :inventory_turnover_rate, :stock_to_sales_ratio, :sell_through_rate,
            :gross_margin, :net_margin, :total_sales, :total_quantity, :total_profit
        )";
    $stmt = $connection->prepare($insert_query);
    $stmt->execute([
        ':date' => $date,
        ':revenue' => $total_sales,
        ':profit_margin' => ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0,
        ':revenue_by_category' => $revenue_by_category,
        ':year_over_year_growth' => $year_over_year_growth,
        ':inventory_turnover_rate' => $inventory_turnover_rate,
        ':stock_to_sales_ratio' => $stock_to_sales_ratio,
        ':sell_through_rate' => $sell_through_rate,
        ':gross_margin' => $gross_margin,
        ':net_margin' => $net_margin,
        ':total_sales' => $total_sales,
        ':total_quantity' => $total_quantity,
        ':total_profit' => $total_profit
    ]);
}


// Fetch metrics data from the `sales_analytics` table for all available dates
$metrics_query = "SELECT * FROM sales_analytics ORDER BY date ASC";
$stmt = $connection->prepare($metrics_query);
$stmt->execute();
$metrics_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!$metrics_data) {
    exit("Error: No report data found.");
}

// Display metrics data in a table


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
      <title>Category Analytics</title>
      
      <!-- Favicon -->
      <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon-blue.ico" />
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend-plugin.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/css/backend.css?v=1.0.0">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
      <link rel="stylesheet" href="http://localhost:8000/assets/vendor/remixicon/fonts/remixicon.css">  </head>
  <body class=" color-light ">
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
            <div class="col-sm-12">
               <div class="card">
                  <div class="card-header d-flex justify-content-between">
                     <div class="header-title">
                        <h4 class="card-title">Category Overview</h4>
                     </div>
                  </div>
                  <div class="card-body">
                     <p>Category Metrics</p>

                     <p>This report analyzes key product and sales data to calculate total sales, quantity sold, profit, and expenses, along with revenue and profit margin based on category of products and services</p>
                     <div class="table-responsive rounded mb-3">
                <table class="data-tables table mb-0 tbl-server-info">
                            <thead>
                                <tr class="light">
                                    <th>Category</th>
                                    <th>Products Sold</th>
                                    <th>Total Sales</th>
                                    <th>Items Quantity</th>
                                    <th>Profit</th>
                                    <th>Total Cost</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sales_category_data as $data): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($data['category_name']); ?></td>
                                        <td><?php echo htmlspecialchars($data['num_products']); ?></td>
                                        <td>$<?php echo number_format($data['total_sales'] ?? 0, 2); ?></td>
                                        <td><?php echo number_format($data['total_quantity']); ?></td>
                                        <td>$<?php echo number_format($data['total_profit']?? 0, 2); ?></td>
                                        <td>$<?php echo number_format($data['total_expenses']?? 0, 2); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                  </div>
               </div>
            </div>
         </div>
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
  </body>
</html>