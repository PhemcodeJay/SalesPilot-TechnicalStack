<?php

error_reporting(E_ALL);
ini_set('display_errors', 1);


session_start([]);
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');

// Check if username is set in session
if (!isset($_SESSION["username"])) {
    exit(json_encode(['success' => false, 'message' => "No username found in session."]));
}

$username = htmlspecialchars($_SESSION["username"]);

// Retrieve user information from the Users table
try {
    $user_query = "SELECT username, email, date FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        exit(json_encode(['success' => false, 'message' => "User not found."]));
    }

    $email = htmlspecialchars($user_info['email']);
    $date = htmlspecialchars($user_info['date']);

    // Fetch products from the database including their categories
    $fetch_products_query = "SELECT id, name, description, price, image_path, category, inventory_qty, cost FROM products";
    $stmt = $connection->prepare($fetch_products_query);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    exit(json_encode(['success' => false, 'message' => "Database error: " . $e->getMessage()]));
}

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $product_id = $_POST['id'] ?? null;

    try {
        // Ensure $connection is available
        if (!isset($connection)) {
            throw new Exception("Database connection not established.");
        }

        // Handle delete action
        if ($action === 'delete') {
            if (!$product_id) {
                throw new Exception("Product ID is required for deletion.");
            }

            $delete_query = "DELETE FROM products WHERE id = :id";
            $stmt = $connection->prepare($delete_query);
            $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => 'Product deleted']);
            } else {
                echo json_encode(['error' => 'Failed to delete product']);
            }
            exit;
        }

        // Handle update action
        if ($action === 'update') {
            $name = $_POST['name'] ?? null;
            $description = $_POST['description'] ?? null;
            $category = $_POST['category'] ?? null;
            $price = $_POST['price'] ?? null;
            $inventory_qty = $_POST['inventory_qty'] ?? null;
            $cost = $_POST['cost'] ?? null;

            if ($product_id && $name && $description && $category && $price && $inventory_qty && $cost) {
                $update_query = "UPDATE products 
                                 SET name = :name,  
                                     description = :description, 
                                     category = :category, 
                                     price = :price, 
                                     inventory_qty = :inventory_qty, 
                                     cost = :cost
                                 WHERE id = :id";
                $stmt = $connection->prepare($update_query);
                $stmt->bindParam(':name', $name);
                $stmt->bindParam(':description', $description);
                $stmt->bindParam(':category', $category);
                $stmt->bindParam(':price', $price);
                $stmt->bindParam(':inventory_qty', $inventory_qty, PDO::PARAM_INT);
                $stmt->bindParam(':cost', $cost);
                $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    echo json_encode(['success' => 'Product updated']);
                } else {
                    echo json_encode(['error' => 'Failed to update product']);
                }
            } else {
                echo json_encode(['error' => 'Incomplete form data']);
            }
            exit;
        }

        // Handle save as PDF action
        if ($action === 'save_pdf') {
            if (!$product_id) {
                throw new Exception("Product ID is required for generating PDF.");
            }

            // Fetch product data
            $query = "SELECT * FROM products WHERE id = :id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':id', $product_id, PDO::PARAM_INT);
            $stmt->execute();
            $product = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($product) {
                // Generate PDF using FPDF
                require 'fpdf.php';
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(40, 10, 'Product Details');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(40, 10, 'Product Name: ' . $product['name']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Description: ' . $product['description']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Category: ' . $product['category']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Sales Price: $' . number_format($product['price'], 2));
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Inventory Quantity: ' . number_format($product['inventory_qty']));
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Cost: $' . number_format($product['cost'], 2));

                $pdf->Output('D', 'product_' . $product_id . '.pdf');
            } else {
                echo json_encode(['error' => 'Product not found']);
            }
            exit;
        }
    } catch (PDOException $e) {
        // Handle database errors
        error_log("PDO Error: " . $e->getMessage());
        echo json_encode(['error' => "Database error: " . $e->getMessage()]);
    } catch (Exception $e) {
        // Handle other exceptions
        error_log("Error: " . $e->getMessage());
        echo json_encode(['error' => "Error: " . $e->getMessage()]);
    }
}



// Fetch inventory and report notifications
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

    $reportsQuery = $connection->prepare("
        SELECT JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_name')) AS product_name, 
               JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.total_sales')) AS revenue,
               p.image_path
        FROM reports r
        JOIN products p ON JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.product_id')) = p.id
        WHERE JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.total_sales')) > :high_revenue 
           OR JSON_UNQUOTE(JSON_EXTRACT(revenue_by_product, '$.total_sales')) < :low_revenue
        ORDER BY r.report_date DESC
    ");
    $reportsQuery->execute([
        ':high_revenue' => 10000,
        ':low_revenue' => 1000,
    ]);
    $reportsNotifications = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    exit(json_encode(['success' => false, 'message' => "Database error: " . $e->getMessage()]));
}

// Fetch user details for the current session user
try {
    $user_query = "SELECT id, username, date, email, phone, location, is_active, role, user_image FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($user_info) {
        $email = htmlspecialchars($user_info['email']);
        $date = date('d F, Y', strtotime($user_info['date']));
        $location = htmlspecialchars($user_info['location']);
        $user_id = htmlspecialchars($user_info['id']);
        
        $existing_image = htmlspecialchars($user_info['user_image']);
        $image_to_display = !empty($existing_image) ? $existing_image : 'uploads/user/default.png';

    }
} catch (PDOException $e) {
    exit("Database error: " . $e->getMessage());
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
      <title>List Products</title>
      
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
                    <h4 class="mb-3">Product Records</h4>
                    <p class="mb-0">Product records effectively showcase your products and services, allowing for organized listings that include price, quantity, and category to enhance presentation and management.</p>
                </div>
                <a href="page-add-product.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Add Product</a>
                <a href="pdf_generate.php?action=generate_product_report" class="btn btn-primary add-list">
    <i class="las la-plus mr-3"></i>Save as PDF</a>

            </div>
        </div>
        <div class="container-fluid">
  
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
            <th>Product</th>
            <th>Description</th>
            <th>Category</th>
            <th>Sales Price</th>
            <th>Inventory Qty</th>
            <th>Cost</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody class="ligth-body">
        <?php foreach ($products as $product): ?>
        <tr data-id="<?php echo $product['id']; ?>">
            <td>
                <div class="checkbox d-inline-block">
                    <input type="checkbox" class="checkbox-input" id="checkbox<?php echo $product['id']; ?>">
                    <label for="checkbox<?php echo $product['id']; ?>" class="mb-0"></label>
                </div>
            </td>
            <td contenteditable="true" class="editable" data-field="name">
                <div class="d-flex align-items-center">
                    <img src="<?php echo $product['image_path']; ?>" class="img-fluid rounded avatar-50 mr-3" alt="image">
                    <div><?php echo htmlspecialchars($product['name']); ?></div>
                </div>
            </td>
            <td contenteditable="true" class="editable" data-field="description"><?php echo htmlspecialchars($product['description']); ?></td>
            <td contenteditable="true" class="editable" data-field="category"><?php echo htmlspecialchars($product['category']); ?></td>
            <td contenteditable="true" class="editable" data-field="price">$<?php echo number_format($product['price'], 2); ?></td>
            <td contenteditable="true" class="editable" data-field="inventory_qty"><?php echo number_format($product['inventory_qty']); ?></td>
            <td contenteditable="true" class="editable" data-field="cost">$<?php echo number_format($product['cost'], 2); ?></td>
            <td>
                <button type="button" class="btn btn-success edit-btn" data-product-id="<?php echo $product['id']; ?>">
                    <i data-toggle="tooltip" data-placement="top" title="Update" class="ri-pencil-line mr-0"></i>
                </button>
                <button type="button" class="btn btn-warning delete-btn" data-product-id="<?php echo $product['id']; ?>">
                    <i data-toggle="tooltip" data-placement="top" title="Delete" class="ri-delete-bin-line mr-0"></i>
                </button>
                <button type="button" class="btn btn-info save-pdf-btn" data-product-id="<?php echo $product['id']; ?>">
                    <i data-toggle="tooltip" data-placement="top" title="Save as PDF" class="ri-save-line mr-0"></i>
                </button>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

            </div>
        </div>
    </div>
</div>

  
        <!-- Page end  -->
    </div>
    
      </div>
    </div>
    <!-- Wrapper End-->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/sidebar.php' ; ?>

    <!-- Backend Bundle JavaScript -->
    <script src="http://localhost:8000/assets/js/backend-bundle.min.js"></script>
    
    <!-- Table Treeview JavaScript -->
    <script src="http://localhost:8000/assets/js/table-treeview.js"></script>
    
    <!-- app JavaScript -->
    <script src="http://localhost:8000/assets/js/app.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
$(document).ready(function() {
    // Inline editing for product details
    $(document).on('click', '.editable', function() {
        let $this = $(this);
        let currentText = $this.text().trim();
        let input = $('<input>', {
            type: 'text',
            value: currentText,
            class: 'form-control form-control-sm'
        });

        $this.html(input);
        input.focus();

        input.on('blur', function() {
            let newText = $(this).val().trim();
            $this.text(newText);
        });

        input.on('keypress', function(e) {
            if (e.which === 13) { // Enter key
                $(this).blur();
            }
        });
    });

    // Save updated product details
    $(document).on('click', '.edit-btn', function() {
        let $row = $(this).closest('tr');
        let productId = $(this).data('product-id');
        let productName = $row.find('[data-field="name"]').text().trim();
        let description = $row.find('[data-field="description"]').text().trim();
        let category = $row.find('[data-field="category"]').text().trim();
        let price = $row.find('[data-field="price"]').text().trim();
        let inventoryQty = $row.find('[data-field="inventory_qty"]').text().trim();
        let cost = $row.find('[data-field="cost"]').text().trim();

        if (!productName || !description || !category || !price || !inventoryQty || !cost) {
            alert('Please fill in all fields before saving.');
            return;
        }

        $.post('page-list-product.php', {
            id: productId,
            name: productName,
            description: description,
            category: category,
            price: price,
            inventory_qty: inventoryQty,
            cost: cost,
            action: 'update'
        })
        .done(function(response) {
            try {
                let data = JSON.parse(response);
                alert(data.success || data.error);
                location.reload();
            } catch (error) {
                alert('Error processing update response.');
            }
        })
        .fail(function() {
            alert('Error updating product.');
        });
    });

    // Delete product
    $(document).on('click', '.delete-btn', function() {
        if (confirm('Are you sure you want to delete this product?')) {
            let productId = $(this).data('product-id');

            $.post('page-list-product.php', {
                id: productId,
                action: 'delete'
            })
            .done(function(response) {
                try {
                    let data = JSON.parse(response);
                    alert(data.success || data.error);
                    location.reload();
                } catch (error) {
                    alert('Error processing delete response.');
                }
            })
            .fail(function() {
                alert('Error deleting product.');
            });
        }
    });

    // Save product details as PDF
    $(document).on('click', '.save-pdf-btn', function() {
        let productId = $(this).data('product-id');
        window.location.href = 'pdf_generate.php?product_id=' + productId;
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