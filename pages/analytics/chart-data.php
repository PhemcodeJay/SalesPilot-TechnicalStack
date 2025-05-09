<?php
header('Content-Type: application/json');
require 'config.php'; // Include your database connection script

try {
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
            $startDate = date('Y-01-01');  // January 1st of the current year
            $endDate = date('Y-m-d');      // Current date
            break;
        default:
            $startDate = date('Y-01-01');
            $endDate = date('Y-m-d');
            break;
    }

    // Fetch sales quantity data for Apex Basic Chart with 3-letter month and 2-digit year abbreviation (e.g., Jun 24)
    $salesQuery = $connection->prepare("
        SELECT DATE_FORMAT(sale_date, '%b %y') AS date, SUM(sales_qty) AS total_sales 
        FROM sales 
        WHERE DATE(sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE_FORMAT(sale_date, '%b %y')");
    $salesQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $salesData = $salesQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch sell-through rate and inventory turnover rate for Apex Line Area Chart with 3-letter month and 2-digit year abbreviation
    $metricsQuery = $connection->prepare("
        SELECT DATE_FORMAT(report_date, '%b %y') AS date, 
               AVG(sell_through_rate) AS avg_sell_through_rate, 
               AVG(inventory_turnover_rate) AS avg_inventory_turnover_rate 
        FROM reports 
        WHERE DATE(report_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE_FORMAT(report_date, '%b %y')");
    $metricsQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $metricsData = $metricsQuery->fetchAll(PDO::FETCH_ASSOC);

    // Fetch revenue by product for Apex 3D Pie Chart
    $revenueByProductQuery = $connection->prepare("
        SELECT report_date, revenue_by_product 
        FROM reports 
        WHERE DATE(report_date) BETWEEN :startDate AND :endDate");
    $revenueByProductQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $revenueByProductData = $revenueByProductQuery->fetchAll(PDO::FETCH_ASSOC);

    // Decode the revenue_by_product JSON data and aggregate it
    $revenueByProduct = [];
    foreach ($revenueByProductData as $report) {
        $products = json_decode($report['revenue_by_product'], true);

        // Check for JSON decode errors
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('JSON decode error: ' . json_last_error_msg());
            continue;
        }

        if (is_array($products)) {
            foreach ($products as $product) {
                if (is_array($product) && isset($product['product_name'], $product['total_sales'])) {
                    $productName = $product['product_name'];
                    $totalSales = (float)$product['total_sales'];
                    $revenueByProduct[$productName] = ($revenueByProduct[$productName] ?? 0) + $totalSales;
                }
            }
        }
    }

    // Sort and get the top 5 products
    arsort($revenueByProduct);
    $top5Products = array_slice($revenueByProduct, 0, 5, true);

    // Fetch revenue, total cost, and additional expenses for Apex 3-Column Chart with 3-letter month and 2-digit year abbreviation
    $revenueQuery = $connection->prepare("
        SELECT DATE_FORMAT(sale_date, '%b %y') AS date, SUM(sales_qty * price) AS revenue 
        FROM sales 
        JOIN products ON sales.product_id = products.id 
        WHERE DATE(sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE_FORMAT(sale_date, '%b %y')");
    $revenueQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $revenueData = $revenueQuery->fetchAll(PDO::FETCH_ASSOC);

    $totalCostQuery = $connection->prepare("
        SELECT DATE_FORMAT(sale_date, '%b %y') AS date, SUM(sales_qty * cost) AS total_cost 
        FROM sales 
        JOIN products ON sales.product_id = products.id 
        WHERE DATE(sale_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE_FORMAT(sale_date, '%b %y')");
    $totalCostQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $totalCostData = $totalCostQuery->fetchAll(PDO::FETCH_ASSOC);

    $expenseQuery = $connection->prepare("
        SELECT DATE_FORMAT(expense_date, '%b %y') AS date, SUM(amount) AS total_expenses 
        FROM expenses 
        WHERE DATE(expense_date) BETWEEN :startDate AND :endDate 
        GROUP BY DATE_FORMAT(expense_date, '%b %y')");
    $expenseQuery->execute(['startDate' => $startDate, 'endDate' => $endDate]);
    $expenseData = $expenseQuery->fetchAll(PDO::FETCH_ASSOC);

    // Combine revenue, total cost, and additional expenses for Apex 3-Column Chart
    $combinedData = [];
    foreach ($revenueData as $data) {
        $date = $data['date'];
        $revenue = (float)($data['revenue'] ?? 0);

        // Find matching total cost data
        $totalCost = (float)($totalCostData[array_search($date, array_column($totalCostData, 'date'))]['total_cost'] ?? 0);

        // Find matching expense data
        $expenses = (float)($expenseData[array_search($date, array_column($expenseData, 'date'))]['total_expenses'] ?? 0);

        $totalExpenses = $totalCost + $expenses;
        $profit = $revenue - $totalExpenses;

        $combinedData[] = [
            'date' => $date,
            'revenue' => number_format($revenue, 2),
            'total_expenses' => number_format($totalExpenses, 2),
            'profit' => number_format($profit, 2)
        ];
    }

    // Prepare final data for each chart
    $response = [
        'apex-basic' => $salesData,
        'apex-line-area' => $metricsData,
        'am-3dpie-chart' => $top5Products,
        'apex-column' => $combinedData
    ];

    echo json_encode($response);

} catch (PDOException $e) {
    error_log('Database error: ' . $e->getMessage());
    echo json_encode(['error' => 'Failed to retrieve data.']);
    exit;
}
?>

