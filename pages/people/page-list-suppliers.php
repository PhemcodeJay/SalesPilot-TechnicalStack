<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');



// Check if username is set in session
if (!isset($_SESSION["username"])) {
    exit("No username found in session.");
}

$username = htmlspecialchars($_SESSION["username"]);

// Retrieve user information from the users table
try {
    $user_query = "SELECT username, email, date FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        exit("User not found.");
    }

    // Retrieve user email and registration date
    $email = htmlspecialchars($user_info['email']);
    $date = htmlspecialchars($user_info['date']);
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
}

// Retrieve supplier from the suppliers table
$supplier_query = "SELECT supplier_id, supplier_name, supplier_email, supplier_phone, supplier_location FROM suppliers";
$stmt = $connection->prepare($supplier_query);
$stmt->execute();
$supplier = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form actions
$action = $_POST['action'] ?? null;
$supplier_id = $_POST['supplier_id'] ?? null;

if ($action && $supplier_id) {
    try {
        // Handle delete action
        if ($action === 'delete') {
            $delete_query = "DELETE FROM suppliers WHERE supplier_id = :supplier_id";
            $stmt = $connection->prepare($delete_query);
            $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
            $stmt->execute();
            echo json_encode(['success' => true, 'message' => 'Supplier deleted successfully']);
            exit;
        }

        // Handle update action
        elseif ($action === 'update') {
            $supplier_name = $_POST['supplier_name'] ?? null;
            $supplier_email = $_POST['supplier_email'] ?? null;
            $supplier_phone = $_POST['supplier_phone'] ?? null;
            $supplier_location = $_POST['supplier_location'] ?? null;

            if ($supplier_name && $supplier_email && $supplier_phone && $supplier_location) {
                $update_query = "UPDATE suppliers 
                                 SET supplier_name = :supplier_name, supplier_email = :supplier_email, 
                                     supplier_phone = :supplier_phone, supplier_location = :supplier_location
                                 WHERE supplier_id = :supplier_id";
                $stmt = $connection->prepare($update_query);
                $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
                $stmt->bindParam(':supplier_name', $supplier_name);
                $stmt->bindParam(':supplier_email', $supplier_email);
                $stmt->bindParam(':supplier_phone', $supplier_phone);
                $stmt->bindParam(':supplier_location', $supplier_location);
                $stmt->execute();
                echo json_encode(['success' => true, 'message' => 'Supplier updated successfully']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Incomplete form data']);
            }
            exit;
        }
    } catch (PDOException $e) {
        error_log("PDO Error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Database error occurred']);
        exit;
    }
}

// Handle PDF generation (GET request)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['supplier_id'])) {
    $supplier_id = $_GET['supplier_id'];
    $query = "SELECT * FROM suppliers WHERE supplier_id = :supplier_id";
    $stmt = $connection->prepare($query);
    $stmt->bindParam(':supplier_id', $supplier_id, PDO::PARAM_INT);
    $stmt->execute();
    $supplier = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($supplier) {
        $pdf = new FPDF();
        $pdf->AddPage();
        $pdf->SetFont('Arial', 'B', 16);
        $pdf->Cell(40, 10, 'Supplier Details');
        $pdf->Ln();
        $pdf->SetFont('Arial', '', 12);
        $pdf->Cell(40, 10, 'Name: ' . $supplier['supplier_name']);
        $pdf->Ln();
        $pdf->Cell(40, 10, 'Email: ' . $supplier['supplier_email']);
        $pdf->Ln();
        $pdf->Cell(40, 10, 'Phone: ' . $supplier['supplier_phone']);
        $pdf->Ln();
        $pdf->Cell(40, 10, 'Location: ' . $supplier['supplier_location']);
        $pdf->Output('D', 'supplier_' . $supplier_id . '.pdf');
        exit;
    } else {
        echo 'Supplier not found.';
        exit;
    }
}

// Fetch supplier data from the database
try {
    $query = "SELECT * FROM suppliers";
    $stmt = $connection->prepare($query);
    $stmt->execute();
    $suppliers = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
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
    $inventoryNotifications = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
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
    $reportsNotifications = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
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
      <title>List Suppliers</title>
      
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
                        <h4 class="mb-3">Suppliers Records</h4>
                        <p class="mb-0">Manage your vendor list and handle purchase orders seamlessly with your online dashboard, serving as your central hub for supplier operations.</p>
                    </div>
                    <a href="page-add-supplier.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Add Supplier</a>
                </div>
            </div>
            <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
            <table class="data-tables table mb-0 tbl-server-info">
                        <thead class="bg-white text-uppercase">
                            <tr class="ligth ligth-data">
                                <th>
                                    <div class="checkbox d-inline-block">
                                        <input type="checkbox" class="checkbox-input" id="checkbox1">
                                        <label for="checkbox1" class="mb-0"></label>
                                    </div>
                                </th>
                                <th>Supplier Name</th>
                                <th>Product Name</th>
                                <th>Email</th>
                                <th>Phone No.</th>
                                <th>Location</th>
                                <th>Note</th>
                                <th>Supply Qty</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody class="light-body">
    <?php foreach ($suppliers as $supplier): ?>
        <tr data-supplier-id="<?php echo htmlspecialchars($supplier['supplier_id']); ?>">
            <td>
                <div class="checkbox d-inline-block">
                    <input type="checkbox" class="checkbox-input" id="checkbox<?php echo $supplier['supplier_id']; ?>">
                    <label for="checkbox<?php echo $supplier['supplier_id']; ?>" class="mb-0"></label>
                </div>
            </td>
            <td contenteditable="true" class="editable" data-field="supplier_name"><?php echo htmlspecialchars($supplier['supplier_name']); ?></td>
                <td contenteditable="true" class="editable" data-field="product_name""><?php echo htmlspecialchars($supplier['product_name']); ?></td>
                <td contenteditable="true" class="editable" data-field="supplier_email"><?php echo htmlspecialchars($supplier['supplier_email']); ?></td>
                <td contenteditable="true" class="editable" data-field="supplier_phone"><?php echo htmlspecialchars($supplier['supplier_phone']); ?></td>
                <td contenteditable="true" class="editable" data-field="supplier_location"><?php echo htmlspecialchars($supplier['supplier_location']); ?></td>
                <td contenteditable="true" class="editable" data-field="note"><?php echo htmlspecialchars($supplier['note']); ?></td>
                <td contenteditable="true" class="editable" data-field="supply_qty"><?php echo htmlspecialchars($supplier['supply_qty']); ?></td>

            <td>
                <button type="button" name="action" class="btn btn-success action-btn" data-action="edit" data-supplier-id="<?php echo htmlspecialchars($supplier['supplier_id']); ?>" data-toggle="tooltip" data-placement="top" title="Edit">
                    <i class="ri-pencil-line mr-0"></i>
                </button>
                <button type="button" name="action" class="btn btn-warning action-btn" data-action="delete" data-supplier-id="<?php echo htmlspecialchars($supplier['supplier_id']); ?>" data-toggle="tooltip" data-placement="top" title="Delete">
                    <i class="ri-delete-bin-line mr-0"></i>
                </button>
                <button type="button" name="action" class="btn btn-info action-btn" data-action="save_pdf" data-supplier-id="<?php echo htmlspecialchars($supplier['supplier_id']); ?>" data-toggle="tooltip" data-placement="top" title="Save as PDF">
                    <i class="ri-eye-line mr-0"></i>
                </button>
            </td>
        </tr>
    <?php endforeach; ?>
</tbody>

                    </table>
                </div>
            </div>
        </div>
        <!-- Page end  -->
    </div>
  >
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
        var currentText = $this.text().trim();
        var input = $('<input>', {
            type: 'text',
            value: currentText,
            class: 'form-control form-control-sm'
        });
        $this.html(input);
        input.focus();

        // Save new value on blur
        input.on('blur', function() {
            var newText = $(this).val().trim();
            $this.html(newText);
        });

        // Handle enter key to save
        input.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $(this).blur();
            }
        });
    });

    // Save updated supplier details
    $('.action-btn').on('click', function() {
        var actionType = $(this).data('action');
        var supplierId = $(this).data('supplier-id');
        
        // Perform update action
        if (actionType === 'edit') {
            var $row = $(this).closest('tr');
            var supplierData = {
                supplier_id: supplierId,
                supplier_name: $row.find('[data-field="supplier_name"]').text().trim(),
                supplier_email: $row.find('[data-field="supplier_email"]').text().trim(),
                supplier_phone: $row.find('[data-field="supplier_phone"]').text().trim(),
                supplier_location: $row.find('[data-field="supplier_location"]').text().trim(),
                action: 'update'
            };

            // Check for completeness
            if (!supplierData.supplier_name || !supplierData.supplier_email || 
                !supplierData.supplier_phone || !supplierData.supplier_location) {
                alert('Please fill in all fields before saving.');
                return;
            }

            // AJAX call to update
            $.post('page-list-suppliers.php', supplierData)
                .done(function(response) {
                    response = JSON.parse(response);
                    if (response.success) {
                        alert(response.message);
                        location.reload();
                    } else {
                        alert('Error: ' + response.message);
                    }
                })
                .fail(function() {
                    alert('Error updating supplier.');
                });
        }

        // Perform delete action
        else if (actionType === 'delete') {
            if (confirm('Are you sure you want to delete this supplier?')) {
                $.post('page-list-suppliers.php', { supplier_id: supplierId, action: 'delete' })
                    .done(function(response) {
                        response = JSON.parse(response);
                        if (response.success) {
                            alert(response.message);
                            location.reload();
                        } else {
                            alert('Error: ' + response.message);
                        }
                    })
                    .fail(function() {
                        alert('Error deleting supplier.');
                    });
            }
        }

        // Perform PDF generation action
        else if (actionType === 'save_pdf') {
            window.location.href = 'page-list-suppliers.php?supplier_id=' + supplierId;
        }
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