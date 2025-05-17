<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../../config/config.php'; // Include your database connection script

// Retrieve the time range from the request
$range = $_GET['range'] ?? 'yearly';
$startDate = '';
$endDate = '';

// Define the date range based on the selected period
switch ($range) {
    case 'weekly':
        $startDate = date('Y-m-d', strtotime('last week Monday'));
        $endDate = date('Y-m-d', strtotime('last week Sunday'));
        break;
    case 'monthly':
        $startDate = date('Y-m-01');
        $endDate = date('Y-m-t');
        break;
    case 'yearly':
        $startDate = date('Y-01-01');
        $endDate = date('Y-12-31');
        break;
}

// Fetch revenue by category for the top 5 categories (Layered Column Chart)
$categoryRevenueQuery = $connection->prepare("
    SELECT categories.category_name, SUM(sales_qty * price) AS revenue 
    FROM sales
    JOIN products ON sales.product_id = products.id
    JOIN categories ON products.category_id = categories.category_id
    WHERE sale_date BETWEEN :startDate AND :endDate
    GROUP BY categories.category_name
    ORDER BY revenue DESC
    LIMIT 5
");
$categoryRevenueQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
$categoryRevenueData = $categoryRevenueQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch revenue and profit for the combination chart (Column and Line Chart)
$revenueProfitQuery = $connection->prepare("
    SELECT DATE(sale_date) AS date, 
           SUM(sales_qty * price) AS revenue,
           SUM(sales_qty * (price - cost)) AS profit
    FROM sales
    JOIN products ON sales.product_id = products.id
    WHERE sale_date BETWEEN :startDate AND :endDate
    GROUP BY DATE(sale_date)
");
$revenueProfitQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
$revenueProfitData = $revenueProfitQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch profit only data (layout1-chart-3)
$profitQuery = $connection->prepare("
    SELECT DATE_FORMAT(sale_date, '%b %Y') AS date,  -- Format date as 'Mon YYYY'
           SUM(sales_qty * (price - cost)) AS profit
    FROM sales
    JOIN products ON sales.product_id = products.id
    WHERE sale_date BETWEEN :startDate AND :endDate
    GROUP BY DATE_FORMAT(sale_date, '%b %Y')
");
$profitQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
$profitData = $profitQuery->fetchAll(PDO::FETCH_ASSOC);

// Fetch expenses only data (layout1-chart-4)
$expensesQuery = $connection->prepare("
    SELECT DATE_FORMAT(expense_date, '%b %Y') AS date,  -- Format date as 'Mon YYYY'
           SUM(amount) AS expenses
    FROM expenses
    WHERE expense_date BETWEEN :startDate AND :endDate
    GROUP BY DATE_FORMAT(expense_date, '%b %Y')
");
$expensesQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
$expensesData = $expensesQuery->fetchAll(PDO::FETCH_ASSOC);

$profitExpenseQuery = $connection->prepare("
    SELECT 
        DATE_FORMAT(s.sale_date, '%b %Y') AS date,  -- Format date as 'Mon YYYY'
        SUM(s.sales_qty * (p.price - p.cost)) AS profit,
        COALESCE(SUM(s.sales_qty * p.cost), 0) + COALESCE(SUM(e.amount), 0) AS expenses
    FROM sales s
    JOIN products p ON s.product_id = p.id
    LEFT JOIN expenses e ON DATE(e.expense_date) = DATE(s.sale_date)  -- Match expenses by day
    WHERE s.sale_date BETWEEN :startDate AND :endDate
    GROUP BY DATE_FORMAT(s.sale_date, '%b %Y')
");

$profitExpenseQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
$profitExpenseData = $profitExpenseQuery->fetchAll(PDO::FETCH_ASSOC);

// Combine data into the final response
$response = [
    'apexLayeredColumnChart' => $categoryRevenueData,  // Revenue by Top 6 Categories
    'apexColumnLineChart' => $revenueProfitData,       // Revenue vs. Profit
    'layout1-chart-3' => $profitData,             // Profit Only (layout1-chart-3)
    'layout1-chart-4' => $expensesData,           // Expenses Only (layout1-chart-4)
    'layout1-chart-5' => $profitExpenseData       // Profit and Expenses Combined (layout1-chart-5)
];

// Output the JSON response
echo json_encode($response);
?>
