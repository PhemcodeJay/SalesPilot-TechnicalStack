<?php
require 'vendor/autoload.php'; // Include OpenAI PHP SDK or use CURL if not installed

use Orhanerday\OpenAi\OpenAi;

$open_ai_key = 'your_openai_api_key';
$openAi = new OpenAi($open_ai_key);

// Example: Sending product sales data to OpenAI for analysis
$salesData = [
    'weekly_sales' => $productMetrics,
    'top_products' => $topProducts,
    'inventory_metrics' => $inventoryMetrics,
];

// Convert data to JSON
$salesDataJson = json_encode($salesData);

// Construct the prompt
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

// Decode the response
$responseData = json_decode($response, true);
$insights = $responseData['choices'][0]['text'] ?? 'No insights found.';

// Display insights on the dashboard
echo "<h2>AI Analysis</h2>";
echo "<div>" . nl2br(htmlspecialchars($insights)) . "</div>";
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Data Analysis Results</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f4f4f4;
            color: #333;
        }
        header {
            background: #007bff;
            color: #fff;
            padding: 10px 20px;
            text-align: center;
        }
        main {
            padding: 20px;
        }
        .analysis-container {
            margin: 0 auto;
            max-width: 800px;
            background: #fff;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            border-radius: 8px;
            padding: 20px;
        }
        .analysis-container h2 {
            color: #007bff;
        }
        .analysis-result {
            background: #f9f9f9;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        .loading {
            text-align: center;
            color: #666;
        }
    </style>
</head>
<body>
    <header>
        <h1>Dynamic Data Analysis</h1>
    </header>
    <main>
        <div class="analysis-container">
            <h2>Analysis Results</h2>
            <div id="results" class="loading">Loading analysis...</div>
        </div>
    </main>
    <script>
        // Fetch data dynamically (replace the URL with your server endpoint)
        async function fetchAnalysis() {
            const resultsContainer = document.getElementById('results');
            try {
                const response = await fetch('https://your-server-endpoint/api/analyze', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': 'Bearer YOUR_OPENAI_API_KEY' // Add your server-side key
                    },
                    body: JSON.stringify({
                        query: "Analyze this data",
                        data: "Sample dynamic data from your database" // Replace with real data
                    })
                });

                if (!response.ok) {
                    throw new Error(`Error: ${response.status}`);
                }

                const analysis = await response.json();

                // Display the analysis
                resultsContainer.innerHTML = '';
                analysis.results.forEach((item, index) => {
                    const resultDiv = document.createElement('div');
                    resultDiv.className = 'analysis-result';
                    resultDiv.innerHTML = `<strong>Result ${index + 1}:</strong> <p>${item}</p>`;
                    resultsContainer.appendChild(resultDiv);
                });
            } catch (error) {
                resultsContainer.innerHTML = `<p>Error loading analysis: ${error.message}</p>`;
            }
        }

        // Initialize the analysis on page load
        window.onload = fetchAnalysis;
    </script>
</body>
</html>
