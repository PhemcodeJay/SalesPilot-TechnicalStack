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

// Calculate metrics for each product
$product_metrics_query = "
    SELECT 
        products.id AS product_id,
        products.name AS product_name,
        SUM(sales.sales_qty) AS total_quantity,
        SUM(sales.sales_qty * products.price) AS total_sales,
        SUM(sales.sales_qty * products.cost) AS total_cost,
        SUM(sales.sales_qty * (products.price - products.cost)) AS total_profit,
        SUM(sales.sales_qty) / NULLIF(SUM(products.stock_qty), 0) AS inventory_turnover_rate, -- Adding inventory turnover rate
        (SUM(products.price) / NULLIF(SUM(products.cost), 0)) * 100 AS sell_through_rate -- Adding sell-through rate
    FROM sales
    INNER JOIN products ON sales.product_id = products.id
    GROUP BY products.id";
$stmt = $connection->query($product_metrics_query);
$product_metrics_data = $stmt->fetchAll(PDO::FETCH_ASSOC);



// Initialize metrics for the entire report
$total_sales = 0;
$total_quantity = 0;
$total_profit = 0;
$total_cost = 0;

foreach ($product_metrics_data as $product) {
    $total_sales += $product['total_sales'];
    $total_quantity += $product['total_quantity'];
    $total_profit += $product['total_profit'];
    $total_cost += $product['total_cost'];
}

// Ensure total expenses are calculated correctly
$total_expenses = $total_cost;

// Additional calculations
$gross_margin = ($total_sales > 0) ? $total_sales - $total_expenses : 0;
$net_margin = ($total_sales > 0) ? $total_profit - $total_expenses : 0;
$profit_margin = ($total_sales > 0) ? ($total_profit / $total_sales) * 100 : 0;
$inventory_turnover_rate = ($total_quantity > 0) ? ($total_cost / ($total_cost / 2)) : 0;
$stock_to_sales_ratio = ($total_sales > 0) ? ($total_quantity / $total_sales) * 100 : 0;
$sell_through_rate = ($total_quantity > 0) ? ($total_sales / $total_quantity) / 100 : 0;

// Encode revenue by product as JSON
$revenue_by_product = json_encode($product_metrics_data);

// Check if a report for the current date already exists
$report_date = date('Y-m-d');
$check_report_query = "SELECT reports_id FROM reports WHERE report_date = :report_date";
$stmt = $connection->prepare($check_report_query);
$stmt->execute([':report_date' => $report_date]);
$existing_report = $stmt->fetch(PDO::FETCH_ASSOC);


if ($existing_report) {
    // Update existing report
    $update_query = "
        UPDATE reports
        SET 
            revenue = :revenue,
            profit_margin = :profit_margin,
            revenue_by_product = :revenue_by_product,
            gross_margin = :gross_margin,
            net_margin = :net_margin,
            inventory_turnover_rate = :inventory_turnover_rate,
            stock_to_sales_ratio = :stock_to_sales_ratio,
            sell_through_rate = :sell_through_rate,
            total_sales = :total_sales,
            total_quantity = :total_quantity,
            total_profit = :total_profit,
            total_expenses = :total_expenses,
            net_profit = :net_profit
        WHERE reports_id = :reports_id";
    $stmt = $connection->prepare($update_query);
    $stmt->execute([
        ':revenue' => $total_sales,
        ':profit_margin' => $profit_margin,
        ':revenue_by_product' => $revenue_by_product,
        ':gross_margin' => $gross_margin,
        ':net_margin' => $net_margin,
        ':inventory_turnover_rate' => $inventory_turnover_rate,
        ':stock_to_sales_ratio' => $stock_to_sales_ratio,
        ':sell_through_rate' => $sell_through_rate,
        ':total_sales' => $total_sales,
        ':total_quantity' => $total_quantity,
        ':total_profit' => $total_profit,
        ':total_expenses' => $total_expenses,
        ':net_profit' => $net_margin,  // This should be net margin, which is profit - expenses
        ':reports_id' => $existing_report['reports_id']
    ]);
} else {
    // Insert new report
    $insert_query = "
        INSERT INTO reports (
            report_date, revenue, profit_margin, revenue_by_product, gross_margin,
            net_margin, inventory_turnover_rate, stock_to_sales_ratio, sell_through_rate,
            total_sales, total_quantity, total_profit, total_expenses, net_profit
        ) VALUES (
            :report_date, :revenue, :profit_margin, :revenue_by_product, :gross_margin,
            :net_margin, :inventory_turnover_rate, :stock_to_sales_ratio, :sell_through_rate,
            :total_sales, :total_quantity, :total_profit, :total_expenses, :net_profit
        )";
    $stmt = $connection->prepare($insert_query);
    $stmt->execute([
        ':report_date' => $report_date,
        ':revenue' => $total_sales,
        ':profit_margin' => $profit_margin,
        ':revenue_by_product' => $revenue_by_product,
        ':gross_margin' => $gross_margin,
        ':net_margin' => $net_margin,
        ':inventory_turnover_rate' => $inventory_turnover_rate,
        ':stock_to_sales_ratio' => $stock_to_sales_ratio,
        ':sell_through_rate' => $sell_through_rate,
        ':total_sales' => $total_sales,
        ':total_quantity' => $total_quantity,
        ':total_profit' => $total_profit,
        ':total_expenses' => $total_expenses,
        ':net_profit' => $net_margin  // This should be net margin, which is profit - expenses
    ]);
}


// Fetch metrics data from the `reports` table for the current date
$metrics_query = "SELECT * FROM reports WHERE report_date = :report_date";
$stmt = $connection->prepare($metrics_query);
$stmt->execute([':report_date' => $report_date]);
$metrics_data = $stmt->fetchAll(PDO::FETCH_ASSOC);


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
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta content="" name="Boost your business efficiency with SalesPilot – the ultimate sales management app. Track leads, manage clients, and increase revenue effortlessly with our user-friendly platform.">
  <meta content="" name="Sales productivity tools, Sales and Client management, Business efficiency tools">

      <title>Products Analytics</title>
      
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
                        <h4 class="card-title">Product Overview</h4>
                        <a href="http://localhost:8000/config/pdf_generate.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>PDF</a>
                     </div>
                  </div>
                  <div class="card-body">
                     <p>Product Metrics</p>

                     <p>The report analyzes sales and product data to compute KPI, including revenue, profit margin, and revenue by product. </p>
                     <div class="table-responsive">
                     

<table id="datatable" class="table data-tables table-striped">
    <thead>
        <tr class="light">
            <th>Product ID</th>
            <th>Product Name</th>
            <th>Total Sales</th>
            <th>Quantity Sold</th>
            <th>Profit</th>
            <th>Sell Through Rate</th>
            <th>Inventory Turnover Ratio</th>
        </tr>
    </thead>
    <tbody>
        <?php
        // To store already displayed product IDs and avoid duplicates
        $displayed_products = [];

        foreach ($metrics_data as $data):
            $revenue_by_product = json_decode($data['revenue_by_product'], true);

            // Check if decoding was successful
            if (is_array($revenue_by_product)):
                foreach ($revenue_by_product as $product):
                    // Ensure all required fields are present
                    if (isset($product['product_id'], $product['product_name'], $product['total_sales'], $product['total_quantity'], $product['total_profit'], $product['sell_through_rate'], $product['inventory_turnover_rate'])
                        && !empty($product['product_id']) && !empty($product['product_name'])
                        && !empty($product['total_sales']) && !empty($product['total_quantity'])
                        && !empty($product['total_profit']) && !empty($product['sell_through_rate']) && !empty($product['inventory_turnover_rate'])
                    ):
                        // Check if the product has already been displayed
                        if (in_array($product['product_id'], $displayed_products)) {
                            continue; // Skip this product if it has been displayed
                        }

                        // Add product ID to the list of displayed products
                        $displayed_products[] = $product['product_id'];
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($product['product_id']); ?></td>
                            <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                            <td>$<?php echo number_format($product['total_sales'], 2); ?></td>
                            <td><?php echo number_format($product['total_quantity']); ?></td>
                            <td>$<?php echo number_format($product['total_profit'], 2); ?></td>
                            <td><?php echo number_format($product['sell_through_rate'], 2); ?>%</td>
                            <td><?php echo number_format($product['inventory_turnover_rate'], 2); ?></td>
                        </tr>
                    <?php
                    endif; // End check for valid product data
                endforeach;
            else: ?>
                <tr>
                    <td colspan="7">No product data available</td>
                </tr>
            <?php endif;
        endforeach; ?>
    </tbody>
</table>

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
    window.location.href = 'http://localhost:8000/pages/invoices/invoice-form.php';
});
</script>
  </body>
</html>