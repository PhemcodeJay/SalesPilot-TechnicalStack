<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php';// Include database connection script

try {
    // Ensure the user is logged in
    if (isset($_SESSION["loggedin"]) && $_SESSION["loggedin"] === true) {
        $username = htmlspecialchars($_SESSION["username"]);

        try {
            $user_query = "SELECT id, username, date, email, phone, location, is_active, role, user_image FROM users WHERE username = :username";
            $stmt = $connection->prepare($user_query);
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user_info) {
                // Set user data and sanitize
                $email = htmlspecialchars($user_info['email']);
                $date = date('d F, Y', strtotime($user_info['date']));
                $location = htmlspecialchars($user_info['location']);
                $user_id = htmlspecialchars($user_info['id']);
                $image_to_display = !empty($user_info['user_image']) ? htmlspecialchars($user_info['user_image']) : 'uploads/user/default.png';

                // Generate personalized greeting
                $current_hour = (int)date('H');
                $time_of_day = ($current_hour < 12) ? "Morning" : (($current_hour < 18) ? "Afternoon" : "Evening");
                $greeting = "Hi " . $username . ", Good " . $time_of_day;
            } else {
                $greeting = "Hello, Guest";
                $image_to_display = 'uploads/user/default.png';
            }
        } catch (Exception $e) {
            exit("Error: " . $e->getMessage());
        }
    }

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Get and sanitize form data
        $invoiceData = [
            'invoice_number'     => $_POST['invoice_number'] ?? '',
            'customer_name'      => $_POST['customer_name'] ?? '',
            'invoice_description' => $_POST['invoice_description'] ?? '',
            'order_date'         => $_POST['order_date'] ?? '',
            'order_status'       => $_POST['order_status'] ?? '',
            'order_id'           => $_POST['order_id'] ?? '',
            'delivery_address'    => $_POST['delivery_address'] ?? '',
            'mode_of_payment'     => $_POST['mode_of_payment'] ?? '',
            'due_date'           => $_POST['due_date'] ?? '',
            'subtotal'           => $_POST['subtotal'] ?? 0,
            'discount'           => $_POST['discount'] ?? 0,
            'total_amount'       => $_POST['total_amount'] ?? 0,
        ];

        // Extract item details from form data
        $items = $_POST['item_name'] ?? [];
        $quantities = $_POST['quantity'] ?? [];
        $prices = $_POST['price'] ?? [];

        // Validate that items are not empty
        if (empty($items) || empty($quantities) || empty($prices)) {
            throw new Exception("No items were added to the invoice.");
        }

        // Begin database transaction
        try {
            $connection->beginTransaction();

            // Insert main invoice data
            $invoiceQuery = "
                INSERT INTO invoices 
                (invoice_number, customer_name, invoice_description, order_date, order_status, order_id, 
                 delivery_address, mode_of_payment, due_date, subtotal, discount, total_amount)
                VALUES 
                (:invoice_number, :customer_name, :invoice_description, :order_date, :order_status, :order_id, 
                :delivery_address, :mode_of_payment, :due_date, :subtotal, :discount, :total_amount)
            ";

            $stmt = $connection->prepare($invoiceQuery);
            $stmt->execute($invoiceData);
            $invoiceId = $connection->lastInsertId();

            // Insert each item linked to the invoice
            $itemQuery = "
                INSERT INTO invoice_items (invoice_id, item_name, qty, price, total)
                VALUES (:invoice_id, :item_name, :qty, :price, :total)
            ";

            $itemStmt = $connection->prepare($itemQuery);

            foreach ($items as $index => $itemName) {
                // Calculate totals for each item
                $quantity = (int)($quantities[$index] ?? 0);
                $price = (float)($prices[$index] ?? 0);
                $total = $quantity * $price;

                // Bind parameters and execute for each item
                $itemStmt->execute([
                    ':invoice_id' => $invoiceId,
                    ':item_name'  => $itemName,
                    ':qty'        => $quantity,
                    ':price'      => $price,
                    ':total'      => $total
                ]);
            }

            $connection->commit();
            header("Location: pages-invoice.php");
            exit(); // Ensure no further code is executed
        } catch (PDOException $e) {
            $connection->rollBack();
            throw new Exception("Database error while processing invoice: " . $e->getMessage());
        }
        
    }

    // Fetch inventory notifications
    $inventoryQuery = $connection->prepare("
        SELECT i.product_name, i.available_stock, i.inventory_qty, i.sales_qty, p.image_path
        FROM inventory i
        JOIN products p ON i.product_id = p.id
        WHERE i.available_stock < :low_stock OR i.available_stock > :high_stock
        ORDER BY i.last_updated DESC
    ");
    $inventoryQuery->execute([':low_stock' => 10, ':high_stock' => 1000]);
    $inventoryNotifications = $inventoryQuery->fetchAll();

    // Fetch report notifications
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
    $reportsQuery->execute([':high_revenue' => 10000, ':low_revenue' => 1000]);
    $reportsNotifications = $reportsQuery->fetchAll();

    try {
        // Prepare and execute the query to fetch detailed user information
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
        // Handle other exceptions
        exit("Error: " . $e->getMessage());
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

      <title>Invoice</title>
      
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
<form action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" method="POST">
    <div class="row">
        <div class="col-lg-12">
            <div class="card card-block card-stretch card-height print rounded">
                <div class="card-header d-flex justify-content-between bg-primary header-invoice">
                    <div class="iq-header-title">
                        <h4 class="card-title mb-0">Invoice#</h4>
                        <input type="text" class="form-control" name="invoice_number" placeholder="Enter invoice no" required>
                    </div>
                    <div class="invoice-btn">
                        <a href="pages-invoice.php" class="btn btn-primary-dark mr-2">
                            <i class="las la-print"></i> View Invoice
                        </a>
                        
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-sm-12">
                            <img src="http://localhost:8000/logo/logonew1.jpg" class="logo-invoice img-fluid mb-3" alt="Logo">
                            <h5 class="mb-0">Hello, <?php echo $username; ?></h5>
                            <input type="text" class="form-control" name="customer_name" placeholder="Customer Name" required>
                            <textarea name="invoice_description" class="form-control mt-2" placeholder="Invoice Details" required></textarea>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-lg-12">
                            <div class="table-responsive-sm mt-3">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th scope="col">Order Date</th>
                                            <th scope="col">Order Status</th>
                                            <th scope="col">Order ID</th>
                                            <th scope="col">Delivery Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="date" class="form-control" name="order_date" required></td>
                                            <td>
                                                <select name="order_status" class="form-control" required>
                                                    <option value="unpaid" selected>Unpaid</option>
                                                    <option value="paid">Paid</option>
                                                </select>
                                            </td>
                                            <td><input type="text" class="form-control" name="order_id" placeholder="Enter order id number" required></td>
                                            
                                            <td>
                                                <textarea name="delivery_address" class="form-control" placeholder="Enter shipping address" required></textarea>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-sm-12">
                            <h5 class="mb-3">Order Summary</h5>
                            <div class="table-responsive-sm">
                            <table class="table" id="invoice-items">
    <thead>
        <tr>
            <th class="text-center" scope="col">#</th>
            <th scope="col">Item</th>
            <th class="text-center" scope="col">Quantity</th>
            <th class="text-center" scope="col">Price ($)</th>
            <th class="text-center" scope="col">Total ($)</th>
        </tr>
    </thead>
    <tbody id="invoice-items-body">
        <tr>
            <th class="text-center" scope="row">1</th>
            <td><input type="text" class="form-control" name="item_name[]" placeholder="Product or Service" required></td>
            <td class="text-center"><input type="number" class="form-control" name="quantity[]" value="1" required oninput="calculateSubtotal()"></td>
            <td class="text-center"><input type="number" step="0.01" class="form-control" name="price[]" value="0.00" required oninput="calculateSubtotal()"></td>
            <td class="text-center"><input type="text" class="form-control" name="total[]" value="0.00" readonly></td>
        </tr>
    </tbody>
</table>
<button type="button" class="btn btn-primary" onclick="addRow()">Add Row</button>

                            </div>
                        </div>
                    </div>
                    
                    <div class="row mt-4 mb-3">
                        <div class="col-sm-12">
                            <div class="or-detail rounded">
                                <div class="p-3">
                                    <h5 class="mb-3">Order Details</h5>
                                    <div class="mb-2">
                                        <label for="mode_of_payment">Payment Mode</label>
                                        <input type="text" id="mode_of_payment" name="mode_of_payment" class="form-control" placeholder="MasterCard" required>
                                    </div>
                                    
                                    <div class="mb-2">
                                        <label for="due_date">Due Date</label>
                                        <input type="date" id="due_date" name="due_date" class="form-control" required>
                                    </div>
                                    <div class="mb-2">
                                        <label for="subtotal">Sub Total</label>
                                        <input type="number" step="0.01" id="subtotal" name="subtotal" class="form-control" value="0.00" readonly>
                                    </div>
                                    <div class="mb-2">
                                        <label for="discount">Discount (%)</label>
                                        <input type="number" id="discount" name="discount" class="form-control" value="0" oninput="calculateSubtotal()">
                                    </div>
                                    <div class="mb-2">
                                        <label for="total-amount">Total Amount ($)</label>
                                        <span id="total-amount" class="font-weight-bold">0.00</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary">Submit Invoice</button>
                </div>
            </div>
        </div>
    </div>
</form>
<script>
function calculateSubtotal() {
    const itemRows = document.querySelectorAll('#invoice-items-body tr');
    let subtotal = 0;

    itemRows.forEach(row => {
        const quantity = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
        const price = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;
        const total = (quantity * price).toFixed(2);
        subtotal += parseFloat(total);
        
        // Update the total for the current row
        row.querySelector('input[name="total[]"]').value = total;
    });

    // Update the subtotal input field
    document.getElementById('subtotal').value = subtotal.toFixed(2);

    // Recalculate the total amount after updating the subtotal
    calculateTotal(subtotal);
}

function calculateTotal(subtotal) {
    const discount = parseFloat(document.getElementById('discount').value) || 0;

    const discountAmount = (subtotal * (discount / 100));
    const totalAmount = (subtotal - discountAmount).toFixed(2);

    // Update the total amount display
    document.getElementById('total-amount').textContent = totalAmount;
}

function addRow() {
    const tableBody = document.getElementById('invoice-items-body');
    const rowCount = tableBody.children.length + 1;

    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <th class="text-center" scope="row">${rowCount}</th>
        <td><input type="text" class="form-control" name="item_name[]" placeholder="Product or Service" required></td>
        <td class="text-center"><input type="number" class="form-control" name="quantity[]" value="1" required oninput="calculateSubtotal()"></td>
        <td class="text-center"><input type="number" step="0.01" class="form-control" name="price[]" value="0.00" required oninput="calculateSubtotal()"></td>
        <td class="text-center"><input type="text" class="form-control" name="total[]" value="0.00" readonly></td>
    `;

    tableBody.appendChild(newRow);
}
</script>



</div>
<!-- Wrapper End-->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/footer.php' ; ?>
<!-- Backend Bundle JavaScript -->
<script src="http://localhost:8000/assets/js/backend-bundle.min.js"></script>

<!-- Table Treeview JavaScript -->
<script src="http://localhost:8000/assets/js/table-treeview.js"></script>

<!-- app JavaScript -->
<script src="http://localhost:8000/assets/js/app.js"></script>
<script src="https://code.jquery.com/jquery-3.2.1.slim.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.11.0/umd/popper.min.js"></script>
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/js/bootstrap.min.js"></script>
    <script>
let rowCount = 1; // Starting count of rows

function calculateRowTotal(row) {
    const quantity = parseFloat(row.querySelector('input[name="quantity[]"]').value) || 0;
    const price = parseFloat(row.querySelector('input[name="price[]"]').value) || 0;
    const total = (quantity * price).toFixed(2);
    row.querySelector('input[name="total[]"]').value = total;
    return parseFloat(total);
}

function calculateSubtotal() {
    const itemRows = document.querySelectorAll('#invoice-items-body tr');
    let subtotal = 0;

    itemRows.forEach(row => {
        subtotal += calculateRowTotal(row);
    });

    document.getElementById('subtotal').value = subtotal.toFixed(2);
    calculateTotal(subtotal);
}

function calculateTotal(subtotal) {
    const discount = parseFloat(document.getElementById('discount').value) || 0;
    const discountAmount = (subtotal * (discount / 100));
    const totalAmount = (subtotal - discountAmount).toFixed(2);

    document.getElementById('total-amount').textContent = totalAmount;
}

function addRow() {
    const tableBody = document.getElementById('invoice-items-body');
    rowCount++;

    const newRow = document.createElement('tr');
    newRow.innerHTML = `
        <th class="text-center" scope="row">${rowCount}</th>
        <td><input type="text" class="form-control" name="item_name[]" placeholder="Product or Service" required></td>
        <td class="text-center"><input type="number" class="form-control" name="quantity[]" value="1" required oninput="calculateSubtotal()"></td>
        <td class="text-center"><input type="number" step="0.01" class="form-control" name="price[]" value="0.00" required oninput="calculateSubtotal()"></td>
        <td class="text-center"><input type="text" class="form-control" name="total[]" value="0.00" readonly></td>
    `;

    // Attach input event listeners for quantity and price to recalculate on input
    const quantityInput = newRow.querySelector('input[name="quantity[]"]');
    const priceInput = newRow.querySelector('input[name="price[]"]');

    quantityInput.addEventListener('input', () => {
        calculateRowTotal(newRow);
        calculateSubtotal(); // Update overall subtotal
    });

    priceInput.addEventListener('input', () => {
        calculateRowTotal(newRow);
        calculateSubtotal(); // Update overall subtotal
    });

    tableBody.appendChild(newRow);
}
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