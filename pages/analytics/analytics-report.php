<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection


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

// Retrieve the time range from the request
$range = $_GET['range'] ?? 'yearly';
$startDate = '';
$endDate = '';

// Define the date range based on the selected period
switch ($range) {
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('this week Monday'));
        $endDate = date('Y-m-d', strtotime('this week Sunday'));
        break;
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'yearly':
    default:
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
}

try {
    // Fetch product metrics for the first table (Product Name and Total Sales)
    $productMetricsQuery = $connection->prepare("
        SELECT p.name, SUM(s.sales_qty) AS total_sales 
        FROM sales s 
        JOIN products p ON s.product_id = p.id 
        WHERE DATE(s.sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY p.name
    ");
    $productMetricsQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $productMetrics = $productMetricsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch top 5 products by revenue
    $revenueByProductQuery = $connection->prepare("
        SELECT p.name, SUM(s.sales_qty * p.price) AS revenue 
        FROM sales s 
        JOIN products p ON s.product_id = p.id 
        WHERE DATE(s.sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY p.name 
        ORDER BY revenue DESC 
        LIMIT 5
    ");
    $revenueByProductQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $topProducts = $revenueByProductQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch inventory metrics for the third table
    $inventoryMetricsQuery = $connection->prepare("
        SELECT p.name, i.available_stock, i.inventory_qty, i.sales_qty 
        FROM inventory i 
        JOIN products p ON i.product_id = p.id
    ");
    $inventoryMetricsQuery->execute();
    $inventoryMetrics = $inventoryMetricsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch income overview for the last table
    $revenueQuery = $connection->prepare("
        SELECT DATE(s.sale_date) AS date, SUM(s.sales_qty * p.price) AS revenue 
        FROM sales s 
        JOIN products p ON s.product_id = p.id 
        WHERE DATE(s.sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE(s.sale_date)
    ");
    $revenueQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

    $totalCostQuery = $connection->prepare("
        SELECT DATE(sale_date) AS date, SUM(sales_qty * cost) AS total_cost 
        FROM sales 
        JOIN products ON sales.product_id = products.id 
        WHERE DATE(sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE(sale_date)
    ");
    $totalCostQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $totalCostData = $totalCostQuery->fetchAll(PDO::FETCH_ASSOC);

    $expenseQuery = $connection->prepare("
        SELECT DATE(expense_date) AS date, SUM(amount) AS total_expenses 
        FROM expenses 
        WHERE DATE(expense_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE(expense_date)
    ");
    $expenseQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $expenseData = $expenseQuery->fetchAll(PDO::FETCH_ASSOC);

    // Combine revenue, total cost, and additional expenses for the income overview
    $incomeOverview = [];
    foreach ($revenueData as $data) {
        $date = $data['date'];
        $revenue = isset($data['revenue']) ? (float)$data['revenue'] : 0;

        $totalCost = 0;
        foreach ($totalCostData as $cost) {
            if ($cost['date'] === $date) {
                $totalCost = isset($cost['total_cost']) ? (float)$cost['total_cost'] : 0;
                break;
            }
        }

        $expenses = 0;
        foreach ($expenseData as $expense) {
            if ($expense['date'] === $date) {
                $expenses = isset($expense['total_expenses']) ? (float)$expense['total_expenses'] : 0;
                break;
            }
        }

        $totalExpenses = $totalCost + $expenses;
        $profit = $revenue - $totalExpenses;

        $incomeOverview[] = [
            'date' => $date,
            'revenue' => number_format($revenue, 2),
            'total_expenses' => number_format($totalExpenses, 2),
            'profit' => number_format($profit, 2)
        ];
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
        JOIN products p ON JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_id')) = p.id
        WHERE JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) > :high_revenue 
           OR JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.revenue')) < :low_revenue
        ORDER BY r.report_date DESC
    ");
    $reportsQuery->execute([
        ':high_revenue' => 10000,
        ':low_revenue' => 1000,
    ]);
    $reportsNotifications = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    // Handle any errors during database queries
    echo "Error: " . $e->getMessage();
}
?>


<!DOCTYPE html>
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

    <title>Analytics Report</title>
    <!-- Favicon -->
    <link rel="shortcut icon" href="http://localhost:8000/assets/images/favicon-blue.ico" />
    <link rel="stylesheet" href="http://localhost:8000/assets/css/backend-plugin.min.css">
    <link rel="stylesheet" href="http://localhost:8000/assets/css/backend.css?v=1.0.0">
    <link rel="stylesheet" href="http://localhost:8000/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
    <link rel="stylesheet" href="http://localhost:8000/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
    <link rel="stylesheet" href="http://localhost:8000/assets/vendor/remixicon/fonts/remixicon.css"> 
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/2.5.1/jspdf.umd.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/0.5.0-beta4/html2canvas.min.js"></script>
</head>
<style>
/* General Styles */
body {
    font-family: Arial, sans-serif;
    margin: 50px;
    padding: 50px;
    background-color: #f4f4f4;
}


.dashboard {
    margin: 50px;
    padding: 20px;
    background-color: #f7e1e1;
    border-radius: 10px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
}

.control-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

h1 {
    font-size: 24px;
    color: #333;
}

.print-btn {
    padding: 10px 20px;
    background-color: #007bff;
    color: #fff;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 16px;
}

.print-btn:hover {
    background-color: #0056b3;
}

/* Chart Table Styles */
.charts-table {
    width: 100%;
}

.chart-table {
    width: 100%;
    margin-bottom: 30px;
    border-collapse: collapse;
}

.chart-table th {
    background-color: #007bff;
    color: #fff;
    padding: 10px;
    text-align: left;
}

.chart-table td {
    padding: 10px;
    vertical-align: top;
}

.chart-container {
    width: 100%;
    height: 400px;
}

.date-range-buttons {
    text-align: right;
}

.date-range-buttons button {
    padding: 8px 15px;
    margin: 0 5px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
    font-size: 14px;
}

.date-range-buttons .weekly {
    background-color: #28a745;
    color: #fff;
}

.date-range-buttons .monthly {
    background-color: #ffc107;
    color: #000;
}

.date-range-buttons .yearly {
    background-color: #dc3545;
    color: #fff;
}

.date-range-buttons button:hover {
    opacity: 0.8;
}
    

.dashboard {
    width: 90%;
    margin: auto;
    padding: 20px;
    background-color: #fff;
    box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
}

.control-panel {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

h1 {
    font-size: 24px;
    margin: 0;
}

.print-btn, .time-btn {
    padding: 10px 20px;
    font-size: 16px;
    color: #fff;
    border: none;
    border-radius: 5px;
    cursor: pointer;
    transition: background-color 0.3s ease;
}

.print-btn {
    background-color: #007bff;
}

.print-btn:hover {
    background-color: #0056b3;
}

.button-group {
    display: flex;
    gap: 10px;
}

.time-btn {
    background-color: #28a745;
}

.time-btn:hover {
    background-color: #218838;
}

h2 {
    font-size: 20px;
    margin-top: 20px;
    margin-bottom: 10px;
}

.data-table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 20px;
}

.data-table th, .data-table td {
    border: 1px solid #ddd;
    padding: 10px;
    text-align: left;
}

.data-table th {
    background-color: #007bff;
    color: white;
    font-weight: bold;
}

.data-table tr:nth-child(even) {
    background-color: #f2f2f2;
}

.data-table tr:hover {
    background-color: #ddd;
}


</style>
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

<div class="dashboard" id="dashboard">
    <div class="control-panel">
        <h1 style="font-weight: bold; text-decoration: underline; ">Report</h1> 
    </div>
    <a href="analytics.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Analytics</a>
    <a href="pdf_generate.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>PDF</a>
    <h2 class="bg-light" style="text-decoration: underline;">Product Sales</h2>
    <div class="table-responsive rounded mb-3">
                <table class="data-tables table mb-0 tbl-server-info">
        <thead>
            <tr class="bg-light">
                <th>Product Name</th>
                <th>Product Sold</th>
            </tr>
        </thead>
        <tbody id="barTableBody">
            <?php foreach ($productMetrics as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td><?php echo htmlspecialchars($product['total_sales']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="bg-light" style="text-decoration: underline;">Top Products </h2>
    <div class="table-responsive rounded mb-3">
                <table class="data-tables table mb-0 tbl-server-info">
        <thead>
            <tr class="bg-light">
                <th>Product Name</th>
                <th>Revenue</th>
            </tr>
        </thead>
        <tbody id="pieTableBody">
            <?php foreach ($topProducts as $product): ?>
                <tr>
                    <td><?php echo htmlspecialchars($product['name']); ?></td>
                    <td>$<?php echo number_format (htmlspecialchars($product['revenue']), 2); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="bg-light" style="text-decoration: underline;">Inventory </h2>
    <div class="table-responsive rounded mb-3">
                <table class="data-tables table mb-0 tbl-server-info">
        <thead>
            <tr class="bg-light">
                <th>Product Name</th>
                <th>Available Stock</th>
                <th>Inventory Quantity</th>
                <th>Sales Quantity</th>
            </tr>
        </thead>
        <tbody id="candleTableBody">
            <?php foreach ($inventoryMetrics as $inventory): ?>
                <tr>
                    <td><?php echo htmlspecialchars($inventory['name']); ?></td>
                    <td><?php echo htmlspecialchars($inventory['available_stock']); ?></td>
                    <td><?php echo htmlspecialchars($inventory['inventory_qty']); ?></td>
                    <td><?php echo htmlspecialchars($inventory['sales_qty']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <h2 class="bg-light" style="text-decoration: underline;">Expenditure</h2>
    <div class="table-responsive rounded mb-3">
                <table class="data-tables table mb-0 tbl-server-info">
        <thead>
            <tr class="bg-light">
                <th>Date</th>
                <th>Revenue</th>
                <th>Total Expenses</th>
                <th>Profit</th>
            </tr>
        </thead>
        <tbody id="areaTableBody">
            <?php foreach ($incomeOverview as $income): ?>
                <tr>
                    <td><?php echo htmlspecialchars($income['date']); ?></td>
                    <td>$<?php echo htmlspecialchars($income['revenue']); ?></td>
                    <td>$<?php echo htmlspecialchars($income['total_expenses']); ?></td>
                    <td>$<?php echo  htmlspecialchars($income['profit']); ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

 
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
    document.addEventListener("DOMContentLoaded", function() {
    // Function to fetch data for the dashboard based on the selected range
    function fetchData(range) {
        // Fetch data dynamically based on the range (weekly, monthly, yearly)
        const xhr = new XMLHttpRequest();
        xhr.open("GET", `chart-data.php?range=${range}`, true);
        xhr.onload = function() {
            if (xhr.status === 200) {
                const data = JSON.parse(xhr.responseText);
                
                // Populate Product Metrics
                const barTableBody = document.getElementById('barTableBody');
                barTableBody.innerHTML = '';
                data.productMetrics.forEach(product => {
                    const row = `<tr>
                        <td>${product.product_name}</td>
                        <td>$${parseFloat(product.total_sales).toFixed(2)}</td>
                    </tr>`;
                    barTableBody.innerHTML += row;
                });

                // Populate Top 5 Products by Revenue
                const pieTableBody = document.getElementById('pieTableBody');
                pieTableBody.innerHTML = '';
                data.topProducts.forEach(product => {
                    const row = `<tr>
                        <td>${product.name}</td>
                        <td>$${parseFloat(product.revenue).toFixed(2)}</td>
                    </tr>`;
                    pieTableBody.innerHTML += row;
                });

                // Populate Inventory Metrics
                const candleTableBody = document.getElementById('candleTableBody');
                candleTableBody.innerHTML = '';
                data.inventoryMetrics.forEach(inventory => {
                    const row = `<tr>
                        <td>${inventory.name}</td>
                        <td>${inventory.available_stock}</td>
                        <td>${inventory.inventory_qty}</td>
                    </tr>`;
                    candleTableBody.innerHTML += row;
                });

                // Populate Income Overview
                const areaTableBody = document.getElementById('areaTableBody');
                areaTableBody.innerHTML = '';
                data.incomeOverview.forEach(income => {
                    const row = `<tr>
                        <td>${income.date}</td>
                        <td>$${parseFloat(income.revenue).toFixed(2)}</td>
                        <td>$${parseFloat(income.total_expenses).toFixed(2)}</td>
                        <td>$${parseFloat(income.profit).toFixed(2)}</td>
                    </tr>`;
                    areaTableBody.innerHTML += row;
                });
            } else {
                console.error("Failed to fetch data.");
            }
        };
        xhr.send();
    }

    // Add event listeners to the buttons
    document.querySelectorAll('.time-btn').forEach(button => {
        button.addEventListener('click', function() {
            fetchData(this.innerText.toLowerCase());
        });
    });

    // Initial data load (default to 'yearly')
    fetchData('yearly');
    
    // Function to print the dashboard as PDF
    window.printPDF = function() {
        window.print();  // This is a basic implementation, you can customize it further if needed
    };
});

</script>


<script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'invoice-form.php';
});
</script>
</body>
</html>