<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');

try {
    // Check if user is logged in
    if (!isset($_SESSION["username"])) {
        throw new Exception("No username found in session.");
    }

    $username = htmlspecialchars($_SESSION["username"]);

    // Fetch user information
    $user_query = "SELECT username, email, date, phone, location, user_image FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        throw new Exception("User not found.");
    }

    $email = htmlspecialchars($user_info['email']);
    $date = date('d F, Y', strtotime($user_info['date']));
    $location = htmlspecialchars($user_info['location']);
    $existing_image = htmlspecialchars($user_info['user_image']);
    $image_to_display = !empty($existing_image) ? $existing_image : 'uploads/user/default.png';

    // Retrieve customers from the customers table
$customers_query = "SELECT customer_id, customer_name, customer_email, customer_phone, customer_location FROM customers";
$stmt = $connection->prepare($customers_query);
$stmt->execute();
$customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $customer_id = $_POST['customer_id'] ?? null;

    if ($action === 'delete') {
        // Handle delete action
        if ($customer_id) {
            $delete_query = "DELETE FROM customers WHERE customer_id = :customer_id";
            $stmt = $connection->prepare($delete_query);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->execute();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo 'No customer ID provided.';
        }
    } elseif ($action === 'save_pdf') {
        // Handle save as PDF action
        if ($customer_id) {
            $query = "SELECT * FROM customers WHERE customer_id = :customer_id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->execute();
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($customer) {
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(40, 10, 'Customer Details');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(40, 10, 'Name: ' . $customer['customer_name']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Email: ' . $customer['customer_email']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Phone: ' . $customer['customer_phone']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Location: ' . $customer['customer_location']);
                $pdf->Output('D', 'customer_' . $customer_id . '.pdf');
            } else {
                echo 'Customer not found.';
            }
        } else {
            echo 'No customer ID provided.';
        }
        exit;
    } elseif ($action === 'update') {
        // Handle update action
        $customer_name = $_POST['customer_name'] ?? null;
        $customer_email = $_POST['customer_email'] ?? null;
        $customer_phone = $_POST['customer_phone'] ?? null;
        $customer_location = $_POST['customer_location'] ?? null;

        if ($customer_id && $customer_name && $customer_email && $customer_phone && $customer_location) {
            $update_query = "UPDATE customers 
                             SET customer_name = :customer_name, customer_email = :customer_email, 
                                 customer_phone = :customer_phone, customer_location = :customer_location
                             WHERE customer_id = :customer_id";
            $stmt = $connection->prepare($update_query);
            $stmt->bindParam(':customer_id', $customer_id);
            $stmt->bindParam(':customer_name', $customer_name);
            $stmt->bindParam(':customer_email', $customer_email);
            $stmt->bindParam(':customer_phone', $customer_phone);
            $stmt->bindParam(':customer_location', $customer_location);
            $stmt->execute();
            header("Location: " . $_SERVER['PHP_SELF']);
            exit;
        } else {
            echo 'Incomplete form data.';
        }
    }
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
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    error_log("Error: " . $e->getMessage());
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

      <title>List Customers</title>
      
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
                        <h4 class="mb-3">Customer Records</h4>
                        <p class="mb-0">A customer dashboard allows you to efficiently collect and analyze customer data to enhance the customer experience and improve retention. </p>
                    </div>
                    <a href="page-add-customers.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Add Customer</a>
                </div>
            </div>
            <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
            <table class="data-tables table mb-0 tbl-server-info">
    <thead class="bg-white text-uppercase">
        <tr class="light light-data">
            <th scope="col">Name</th>
            <th scope="col">Email</th>
            <th scope="col">Phone</th>
            <th scope="col">Location</th>
            <th scope="col">Action</th>
        </tr>
    </thead>
    <tbody class="light-body">
        <?php if (!empty($customers)): ?>
            <?php foreach ($customers as $customer): ?>
                <tr data-customer_id="<?php echo $customer['customer_id']; ?>">
                    <td contenteditable="true" class="editable" data-field="customer_name"><?php echo htmlspecialchars($customer['customer_name']); ?></td>
                    <td contenteditable="true" class="editable" data-field="customer_email"><?php echo htmlspecialchars($customer['customer_email']); ?></td>
                    <td contenteditable="true" class="editable" data-field="customer_phone"><?php echo htmlspecialchars($customer['customer_phone']); ?></td>
                    <td contenteditable="true" class="editable" data-field="customer_location"><?php echo htmlspecialchars($customer['customer_location']); ?></td>
                    <td>
                        <button type="button" class="btn btn-success save-btn" 
                                data-customer-id="<?php echo $customer['customer_id']; ?>" 
                                data-action="update">
                            <i data-toggle="tooltip" data-placement="top" title="Update" class="ri-pencil-line mr-0"></i>
                        </button>
                        <button type="button" class="btn btn-warning delete-btn" 
                                data-customer-id="<?php echo $customer['customer_id']; ?>" 
                                data-action="delete">
                            <i data-toggle="tooltip" data-placement="top" title="Delete" class="ri-delete-bin-line mr-0"></i>
                        </button>
                        <button type="button" class="btn btn-info save-pdf-btn" 
                                data-customer-id="<?php echo $customer['customer_id']; ?>" 
                                data-action="save_pdf">
                            <i data-toggle="tooltip" data-placement="top" title="Save as PDF" class="ri-save-line mr-0"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No customers found.</td>
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
    // Enable inline editing on click
    $('.editable').on('click', function() {
        var $this = $(this);
        var currentText = $this.text().trim(); // Trim any whitespace
        var input = $('<input>', {
            type: 'text',
            value: currentText,
            class: 'form-control form-control-sm'
        });
        $this.html(input); // Replace the text with an input element
        input.focus();

        // Save the new value on blur
        input.on('blur', function() {
            var newText = $(this).val().trim(); // Trim the new value as well
            $this.html(newText); // Restore text to the div
        });

        // Handle pressing the enter key to save and blur
        input.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $(this).blur();
            }
        });
    });

    // Save updated customer details
    $('.save-btn').on('click', function() {
        var $row = $(this).closest('tr'); // Get the closest table row for the clicked button
        var customerId = $(this).data('customer-id'); // Use data attribute for customer ID
        var customerName = $row.find('[data-field="customer_name"]').text().trim(); // Get the text from the editable cell
        var customerEmail = $row.find('[data-field="customer_email"]').text().trim();
        var customerPhone = $row.find('[data-field="customer_phone"]').text().trim();
        var customerLocation = $row.find('[data-field="customer_location"]').text().trim();

        if (!customerName || !customerEmail || !customerPhone || !customerLocation) {
            alert('Please fill in all fields before saving.');
            return; // Stop execution if any field is empty
        }

        $.post('page-list-customers.php', {
            customer_id: customerId, // Send 'customer_id' to match with PHP
            customer_name: customerName,
            customer_email: customerEmail,
            customer_phone: customerPhone,
            customer_location: customerLocation,
            action: 'update'
        })
        .done(function(response) {
            alert('Customer updated successfully!');
            location.reload(); // Reload the page to reflect the updates
        })
        .fail(function() {
            alert('Error updating customer.');
        });
    });

    // Delete a customer
    $('.delete-btn').on('click', function() {
        if (confirm('Are you sure you want to delete this customer?')) {
            var customerId = $(this).data('customer-id');
            $.post('page-list-customers.php', {
                customer_id: customerId, // Send 'customer_id' to match with PHP
                action: 'delete'
            })
            .done(function(response) {
                alert('Customer deleted successfully!');
                location.reload(); // Refresh the page to reflect changes
            })
            .fail(function() {
                alert('Error deleting customer.');
            });
        }
    });

    // Save customer details as PDF
    $('.save-pdf-btn').on('click', function() {
        var customerId = $(this).data('customer-id');
        window.location.href = 'pdf_generate.php?customer_id=' + customerId; // Pass 'customer_id' to the PDF generator
    });
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