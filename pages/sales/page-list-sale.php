<?php
session_start();
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');

// Check if username is set in session
if (!isset($_SESSION["username"])) {
    exit("Error: No username found in session.");
}

$username = htmlspecialchars($_SESSION["username"]);

// Retrieve user information from the users table
$user_query = "SELECT id, username, email, date FROM users WHERE username = :username";
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

// Retrieve sales data from the sales table only
$query = "SELECT 
            sales_id, 
            sale_date, 
            name AS product_name, 
            total_price, 
            sale_status AS sales_status, 
            sales_qty, 
            payment_status, 
            sales_price 
          FROM sales
          ORDER BY sale_date DESC";

$stmt = $connection->prepare($query);
$stmt->execute();
$sales_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'] ?? '';
    $sales_id = $_POST['sales_id'] ?? null;

    

    // Delete sale
    if ($action === 'delete') {
        $query = "DELETE FROM sales WHERE sales_id = ?";
        $stmt = $connection->prepare($query);

        if ($stmt->execute([$sales_id])) {
            echo json_encode(['success' => 'Sale deleted successfully.']);
            exit;
        }

        echo json_encode(['error' => 'Failed to delete sale.']);
        exit;
    }

    // Save as PDF
    if ($action === 'save_pdf') {
        $query = "SELECT * FROM sales WHERE sales_id = ?";
        $stmt = $connection->prepare($query);
        $stmt->execute([$sales_id]);
        $sale = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($sale) {
            $sales_price = $sale['sales_price'] ?? 'N/A';
            $total_price = $sale['total_price'] ?? 'N/A';

            // Generate PDF
            $pdf = new FPDF();
            $pdf->AddPage();
            $pdf->SetFont('Arial', 'B', 16);
            $pdf->Cell(40, 10, 'Sales Record');
            $pdf->Ln(10);
            $pdf->SetFont('Arial', '', 12);
            $pdf->Cell(40, 10, 'Sale Date: ' . $sale['sale_date']);
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Product Name: ' . $sale['product_name']);
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Unit: ' . $sale['unit']);
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Sales Price: $' . number_format($sales_price, 2));
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Total Price: $' . number_format($total_price, 2));
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Quantity: ' . $sale['sales_qty']);
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Sales Status: ' . $sale['sales_status']);
            $pdf->Ln(8);
            $pdf->Cell(40, 10, 'Payment Status: ' . $sale['payment_status']);

            // Save the PDF file
            $pdf_filename = 'sale_' . $sales_id . '.pdf';
            $pdf->Output('F', $pdf_filename);

            echo json_encode(['success' => 'PDF saved successfully.', 'pdf_url' => $pdf_filename]);
            exit;
        }

        echo json_encode(['error' => 'Sale not found.']);
        exit;
    }

    // Invalid action response
    echo json_encode(['error' => 'Invalid action.']);
    exit;
}

// Fetch inventory notifications with product images
try {
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
} catch (PDOException $e) {
    echo "Error: " . $e->getMessage();
}

// Fetch reports notifications with product images
try {
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
    echo "Error: " . $e->getMessage();
}

// Prepare user data for display
try {
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
    exit("Database error: " . $e->getMessage());
} catch (Exception $e) {
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
      <title>List Sales</title>
      
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
            <div class="col-lg-12">
                <div class="d-flex flex-wrap align-items-center justify-content-between mb-4">
                    <div>
                        <h4 class="mb-3">Sale Records</h4>
                        <p class="mb-0">Sales Records allows you to manage key performance indicators (KPIs) and track them in one central location, supporting teams in achieving their sales objectives. </p>
                    </div>
                    <a href="page-add-sale.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Add Sale</a>
                </div>
            </div>
            <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
            <table class="data-tables table mb-0 tbl-server-info">
    <thead class="bg-white text-uppercase">
        <tr class="light light-data">
            <th>Date</th>
            <th>Name</th>
            <th>Unit</th>
            <th>Total</th>
            <th>Quantity</th>
            <th>Sales</th>
            <th>Payment</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody class="light-body">
    <?php if (!empty($sales_data)): ?>
        <?php foreach ($sales_data as $sale): ?>
            <tr data-sale-id="<?php echo htmlspecialchars($sale['sales_id']); ?>">
                <td><?php echo htmlspecialchars(date('d M Y', strtotime($sale['sale_date']))); ?></td>
                <td><?php echo htmlspecialchars($sale['product_name']); ?></td>
                <td>$<?php echo htmlspecialchars(number_format($sale['sales_price'], 2)); ?></td>
                <td>$<?php echo htmlspecialchars(number_format($sale['total_price'], 2)); ?></td>
                <td><?php echo htmlspecialchars($sale['sales_qty']); ?></td>
                <td>
                    <div class="badge badge-success"><?php echo htmlspecialchars($sale['sales_status']); ?></div>
                </td>
                
                <td>
                    <div class="badge badge-success"><?php echo htmlspecialchars($sale['payment_status']); ?></div>
                </td>
                <td>
                    
                    <button type="button" class="btn btn-warning action-btn" data-action="delete" data-sale-id="<?php echo htmlspecialchars($sale['sales_id']); ?>">
                        <i data-toggle="tooltip" data-placement="top" title="Delete" class="ri-delete-bin-line mr-0"></i>
                    </button>
                    <button type="button" class="btn btn-info action-btn" data-action="save_pdf" data-sale-id="<?php echo htmlspecialchars($sale['sales_id']); ?>">
                        <i data-toggle="tooltip" data-placement="top" title="Save as PDF" class="ri-save-line mr-0"></i>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    <?php else: ?>
        <tr>
            <td colspan="7">No sales data found.</td>
        </tr>
    <?php endif; ?>
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
    
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
$(document).ready(function() {

    // Delete sale
    $(document).on('click', '.action-btn[data-action="delete"]', function() {
        if (confirm('Are you sure you want to delete this sale?')) {
            let salesId = $(this).data('sale-id');

            $.post('page-list-sale.php', {
                sales_id: salesId,
                action: 'delete'
            })
            .done(function(response) {
                console.log("Response from server:", response); // Log the response for debugging
                try {
                    let data = JSON.parse(response);
                    alert(data.success || data.error);
                    location.reload(); // Reload the page to reflect changes
                } catch (error) {
                    console.error('Error parsing response:', error);
                    alert('Error processing delete response.');
                }
            })
            .fail(function() {
                console.error('AJAX error occurred while deleting sale.');
                alert('Error deleting sale.');
            });
        }
    });

    // Save sale details as PDF
    $(document).on('click', '.action-btn[data-action="save_pdf"]', function() {
        let salesId = $(this).data('sale-id');
        window.location.href = 'http://localhost:8000/config/pdf_generate.php?sales_id=' + salesId; // Redirect to PDF generation script
    });
});
</script>



    <script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'http://localhost:8000/pages/invoices/invoice-form.php';
});
</script>
  </body>
</html>