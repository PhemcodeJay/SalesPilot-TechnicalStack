<?php
session_start([
    'cookie_lifetime' => 86400,
    'cookie_secure'   => true,
    'cookie_httponly' => true,
    'use_strict_mode' => true,
    'sid_length'      => 48,
]);

include('config.php'); // Includes database connection



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

try {

    
    
    // Fetch data from the reports table
    $reportsQuery = $connection->query("SELECT * FROM reports");
    $reports = $reportsQuery->fetchAll();

    // Fetch data from the sales_analytics table
    $salesAnalyticsQuery = $connection->query("SELECT * FROM sales_analytics");
    $salesAnalytics = $salesAnalyticsQuery->fetchAll();
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Display - Reports and Sales Analytics</title>
    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >
    <style>
        body {
            padding: 20px;
            background-color: #f9f9f9;
        }
        .table-responsive {
            margin-top: 20px;
        }
        .table-header {
            text-align: center;
            margin: 20px 0;
        }
        .table-container {
            background: #fff;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 20px;
        }
        .highlight {
            background-color: #f5f5f5;
            font-weight: bold;
        }
    </style>
</head>
<body>

    <div class="container">
        <h1 class="text-center mb-4">Reports and Sales Analytics</h1>
        
        <!-- Reports Table -->
        <div class="table-container">
            <h2 class="table-header">Reports Table</h2>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            
                            <th>Date</th>
                            <th>Revenue</th>
                            <th>Profit Margin (%)</th>
                            <th>Total Sales</th>
                            <th>Total Profit</th>
                            <th>Total Expenses</th>
                            <th>Net Profit</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($reports as $report): ?>
                            <tr>
                                
                                <td><?= htmlspecialchars($report['report_date']) ?></td>
                                <td>$<?= number_format($report['revenue'], 2) ?></td>
                                <td><?= htmlspecialchars($report['profit_margin']) ?>%</td>
                                <td><?= number_format($report['total_sales']) ?></td>
                                <td>$<?= number_format($report['total_profit'], 2) ?></td>
                                <td>$<?= number_format($report['total_expenses'], 2) ?></td>
                                <td>$<?= number_format($report['net_profit'], 2) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sales Analytics Table -->
        <div class="table-container mt-5">
            <h2 class="table-header">Sales Analytics Table</h2>
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>Date</th>
                            <th>Revenue</th>
                            <th>Profit Margin (%)</th>
                            <th>Total Sales</th>
                            <th>Total Quantity</th>
                            <th>Total Profit</th>
                            <th>Net Profit</th>
                            <th>Most Sold Product ID</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($salesAnalytics as $sale): ?>
                            <tr>
                                <td><?= htmlspecialchars($sale['date']) ?></td>
                                <td>$<?= number_format($sale['revenue'], 2) ?></td>
                                <td><?= htmlspecialchars($sale['profit_margin']) ?>%</td>
                                <td><?= number_format($sale['total_sales']) ?></td>
                                <td><?= number_format($sale['total_quantity']) ?></td>
                                <td>$<?= number_format($sale['total_profit'], 2) ?></td>
                                <td>$<?= number_format($sale['net_profit'], 2) ?></td>
                                <td><?= htmlspecialchars($sale['most_sold_product_id']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle JS -->
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"
    ></script>
</body>
</html>
