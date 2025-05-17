<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');

use Orhanerday\OpenAi\OpenAi;

$open_ai_key = 'your_openai_api_key';
$openAi = new OpenAi($open_ai_key);

if (!isset($_SESSION["username"])) {
    exit("Error: No username found in session.");
}

$username = htmlspecialchars($_SESSION["username"]);

try {
    // Fetch user information
    $user_query = "SELECT username, email, date FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        exit("Error: User not found.");
    }

    $email = htmlspecialchars($user_info['email']);
    $date = htmlspecialchars($user_info['date']);

    // Fetch data from reports table
    $reportsQuery = $connection->query("SELECT * FROM reports");
    $reports = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch data from sales_analytics table
    $analyticsQuery = $connection->query("SELECT * FROM sales_analytics");
    $salesAnalytics = $analyticsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch revenue by product data from the reports table (assuming it's stored as JSON)
    $revenueProductsQuery = $connection->query("SELECT revenue_by_product FROM reports LIMIT 1");
    $revenueProductsRow = $revenueProductsQuery->fetch(PDO::FETCH_ASSOC);
    $revenueByProduct = json_decode($revenueProductsRow['revenue_by_product'], true);

    // Prepare data for AI analysis
    $salesData = [
        'weekly_sales' => $reports,
        'analytics' => $salesAnalytics,
        'revenue_by_product' => $revenueByProduct,
    ];
    $salesDataJson = json_encode($salesData);

    // AI analysis using OpenAI
    $prompt = "
Analyze the following sales data and provide insights:
$salesDataJson
- Identify trends in sales and inventory.
- Suggest actions to improve performance.
- Highlight any anomalies or concerns.
    ";
    $response = $openAi->completion([
        'model' => 'gpt-4',
        'prompt' => $prompt,
        'temperature' => 0.7,
        'max_tokens' => 1000,
        'top_p' => 1.0,
        'frequency_penalty' => 0.0,
        'presence_penalty' => 0.0,
    ]);

    $responseData = json_decode($response, true);
    $insights = $responseData['choices'][0]['text'] ?? 'No insights found.';

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
} catch (Exception $e) {
    die("Error: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            font-family: Arial, sans-serif;
        }
        .card {
            margin-bottom: 20px;
        }
        .table-container {
            overflow-x: auto;
            overflow-y: auto;
            max-height: 400px;
            margin-bottom: 20px;
        }
        .table {
            width: 100%;
            border-collapse: collapse;
        }
        .table th, .table td {
            padding: 10px;
            text-align: left;
        }
        .table th {
            background-color: #007bff;
            color: white;
        }
        .table-striped tbody tr:nth-of-type(odd) {
            background-color: #f9f9f9;
        }
        .insights {
            background-color: #fff;
            padding: 15px;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .pagination {
            display: flex;
            justify-content: center;
            margin-top: 10px;
        }
        .pagination button {
            margin: 0 5px;
        }
        .pagination button:disabled {
            background-color: #ddd;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1 class="mt-4">Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
        <p>Email: <?php echo $email; ?></p>
        <p>Registration Date: <?php echo $date; ?></p>

        <!-- AI Insights -->
        <div class="card">
            <div class="card-header">
                <h3>AI Insights</h3>
            </div>
            <div class="card-body">
                <div class="insights">
                    <p><?php echo nl2br(htmlspecialchars($insights)); ?></p>
                </div>
            </div>
        </div>

        <!-- Reports Table -->
        <div class="card">
            <div class="card-header">
                <h3>Reports</h3>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped" id="reportsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Revenue</th>
                                <th>Profit Margin</th>
                                <th>Expenses</th>
                                <th>Net Profit</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($reports, 0, 10) as $report): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($report['report_date']); ?></td>
                                <td><?php echo number_format($report['revenue'], 2); ?></td>
                                <td><?php echo number_format($report['profit_margin'], 2); ?>%</td>
                                <td><?php echo number_format($report['total_expenses'], 2); ?></td>
                                <td><?php echo number_format($report['net_profit'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="reportsPagination">
                    <button id="prevReports" onclick="paginate('reports', -1)" disabled>Prev</button>
                    <button id="nextReports" onclick="paginate('reports', 1)">Next</button>
                </div>
            </div>
        </div>

        <!-- Revenue by Product -->
        <div class="card">
            <div class="card-header">
                <h3>Revenue by Product</h3>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped" id="revenueByProductTable">
                        <thead>
                            <tr>
                                <th>Product Name</th>
                                <th>Total Sales</th>
                                <th>Total Quantity</th>
                                <th>Total Profit</th>
                                <th>Sell-Through Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($revenueByProduct, 0, 10) as $product): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($product['product_name']); ?></td>
                                <td><?php echo number_format($product['total_sales'], 2); ?></td>
                                <td><?php echo $product['total_quantity']; ?></td>
                                <td><?php echo number_format($product['total_profit'], 2); ?></td>
                                <td><?php echo number_format($product['sell_through_rate'], 2); ?>%</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="revenueByProductPagination">
                    <button id="prevRevenueByProduct" onclick="paginate('revenueByProduct', -1)" disabled>Prev</button>
                    <button id="nextRevenueByProduct" onclick="paginate('revenueByProduct', 1)">Next</button>
                </div>
            </div>
        </div>

        <!-- Sales Analytics -->
        <div class="card">
            <div class="card-header">
                <h3>Sales Analytics</h3>
            </div>
            <div class="card-body">
                <div class="table-container">
                    <table class="table table-striped" id="salesAnalyticsTable">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Revenue</th>
                                <th>Profit Margin</th>
                                <th>Cost of Selling</th>
                                <th>Net Profit</th>
                                <th>Most Sold Product</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($salesAnalytics, 0, 10) as $analytics): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($analytics['date']); ?></td>
                                <td><?php echo number_format($analytics['revenue'], 2); ?></td>
                                <td><?php echo number_format($analytics['profit_margin'], 2); ?>%</td>
                                <td><?php echo number_format($analytics['cost_of_selling'], 2); ?></td>
                                <td><?php echo number_format($analytics['net_profit'], 2); ?></td>
                                <td><?php echo htmlspecialchars($analytics['most_sold_product_id']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <div class="pagination" id="salesAnalyticsPagination">
                    <button id="prevSalesAnalytics" onclick="paginate('salesAnalytics', -1)" disabled>Prev</button>
                    <button id="nextSalesAnalytics" onclick="paginate('salesAnalytics', 1)">Next</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        let reportsPage = 0;
        let revenueByProductPage = 0;
        let salesAnalyticsPage = 0;

        // Function to handle pagination
        function paginate(tableName, direction) {
            let page;

            if (tableName === 'reports') {
                page = reportsPage;
                const table = <?php echo json_encode($reports); ?>;
                const tableLength = table.length;
                const rows = document.querySelector('#reportsTable tbody').rows;

                if (direction === 1 && page * 10 + 10 < tableLength) {
                    reportsPage++;
                } else if (direction === -1 && page > 0) {
                    reportsPage--;
                }

                updateTable('reports', page, table);
            }

            // Similar pagination logic for 'revenueByProduct' and 'salesAnalytics'

        }

        // Function to update the table with paginated data
        function updateTable(tableName, page, data) {
            const rowsPerPage = 10;
            const start = page * rowsPerPage;
            const paginatedData = data.slice(start, start + rowsPerPage);

            let tableBody = '';
            paginatedData.forEach(item => {
                tableBody += `
                    <tr>
                        <td>${item.date}</td>
                        <td>${item.revenue}</td>
                        <td>${item.profit_margin}</td>
                        <td>${item.cost_of_selling}</td>
                        <td>${item.net_profit}</td>
                        <td>${item.most_sold_product}</td>
                    </tr>
                `;
            });

            document.querySelector(`#${tableName}Table tbody`).innerHTML = tableBody;
        }
    </script>
</body>
</html>
