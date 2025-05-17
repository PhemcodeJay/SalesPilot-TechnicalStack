<?php
session_start([]);

error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);
require_once __DIR__ . '/../../config/config.php'; // Includes database connection
require __DIR__ .  '/../../vendor/autoload.php';
require __DIR__ . ('/../../fpdf/fpdf.php');


try {
    // Check if username is set in session
    if (!isset($_SESSION["username"])) {
        throw new Exception("No username found in session.");
    }

    $username = htmlspecialchars($_SESSION["username"]);

    // Retrieve user information from the Users table
    $user_query = "SELECT username, email, date FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        throw new Exception("User not found.");
    }

    // Retrieve user email and registration date
    $email = htmlspecialchars($user_info['email']);
    $date = htmlspecialchars($user_info['date']);

  // Retrieve expenses from the expenses table
$expenses_query = "SELECT expense_id, description, amount, expense_date, created_by FROM expenses";
$stmt = $connection->prepare($expenses_query);
$stmt->execute();
$expenses = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $expense_id = $_POST['expense_id'] ?? null;

    if ($action === 'delete') {
        if ($expense_id) {
            $delete_query = "DELETE FROM expenses WHERE expense_id = :expense_id";
            $stmt = $connection->prepare($delete_query);
            $stmt->bindParam(':expense_id', $expense_id);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Expense not found or failed to delete.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'No expense ID provided for deletion.']);
        }
        
    } elseif ($action === 'save_pdf') {
        // Handle save as PDF action
        if ($expense_id) {
            $query = "SELECT * FROM expenses WHERE expense_id = :expense_id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':expense_id', $expense_id);  // Fixed parameter binding
            $stmt->execute();
            $expense = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($expense) {
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(40, 10, 'Expense Details');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(40, 10, 'Date: ' . $expense['expense_date']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Description: ' . $expense['description']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Amount: $' . $expense['amount']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Created by: ' . $expense['created_by']);
                
                ob_clean();

                // Output the PDF
                $pdf->Output('D', 'expense_' . $expense_id . '.pdf');
            } else {
                echo 'Expense not found.';
            }
        } else {
            echo 'No expense ID provided.';
        }
        exit;
    } elseif ($action === 'update') {
        // Handle update action
        $description = $_POST['description'] ?? null;
        $amount = $_POST['amount'] ?? null;
        $expense_date = $_POST['expense_date'] ?? null;
        $created_by = $_POST['created_by'] ?? null;

        if ($expense_id && $description && $amount && $expense_date && $created_by) {
            $update_query = "UPDATE expenses 
                             SET description = :description, amount = :amount, expense_date = :expense_date, created_by = :created_by
                             WHERE expense_id = :expense_id";
            $stmt = $connection->prepare($update_query);
            $stmt->bindParam(':expense_id', $expense_id);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':amount', $amount);
            $stmt->bindParam(':expense_date', $expense_date);
            $stmt->bindParam(':created_by', $created_by);
            $stmt->execute();

            if ($stmt->rowCount() > 0) {
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'Update failed or no changes detected.']);
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Incomplete data for updating expense.']);
        }
        exit;
    }
}


} catch (PDOException $e) {
    // Handle database errors
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
} catch (Exception $e) {
    // Handle other errors
    error_log("Error: " . $e->getMessage());
    exit("Error: " . $e->getMessage());
}

try {
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
    $inventoryNotifications = $inventoryQuery->fetchAll(PDO::FETCH_ASSOC);

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
    $reportsNotifications = $reportsQuery->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Handle any errors during database queries
    echo "Error: " . $e->getMessage();
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

      <title>List Expenses</title>
      
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
                        <h4 class="mb-3">Expense Records</h4>
                        <p class="mb-0">An expense dashboard enables efficient tracking, evaluation, and optimization of all acquisition and purchasing processes.</p>
                    </div>
                    <a href="page-add-expense.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Add Expense</a>
                </div>
            </div>
            <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
            <table class="data-tables table mb-0 tbl-server-info">
    <thead class="bg-white text-uppercase">
        <tr class="light light-data">
            <th>Date</th>
            <th>Description</th>
            <th>Amount</th>
            <th>Created By</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody class="light-body">
        <?php if (!empty($expenses)): ?>
            <?php foreach ($expenses as $expense): ?>
                <tr data-expense-id="<?php echo $expense['expense_id']; ?>">
                    <!-- Inline editable fields -->
                    <td contenteditable="true" class="editable" data-field="expense_date"><?php echo htmlspecialchars($expense['expense_date']); ?></td>
                    <td contenteditable="true" class="editable" data-field="description"><?php echo htmlspecialchars($expense['description']); ?></td>
                    <td contenteditable="true" class="editable" data-field="amount">$<?php echo number_format(htmlspecialchars($expense['amount']) ,2); ?></td>
                    <td contenteditable="true" class="editable" data-field="created_by"><?php echo htmlspecialchars($expense['created_by']); ?></td>

                    <!-- Action buttons -->
                    <td>
                        <button type="button" class="btn btn-success save-btn" data-expense-id="<?php echo $expense['expense_id']; ?>">
                            <i data-toggle="tooltip" data-placement="top" title="Update" class="ri-pencil-line mr-0"></i>
                        </button>
                        <button type="button" class="btn btn-warning delete-btn" data-expense-id="<?php echo $expense['expense_id']; ?>">
                            <i data-toggle="tooltip" data-placement="top" title="Delete" class="ri-delete-bin-line mr-0"></i>
                        </button>
                        <button type="button" class="btn btn-info save-pdf-btn" data-expense-id="<?php echo $expense['expense_id']; ?>">
                            <i data-toggle="tooltip" data-placement="top" title="Save as PDF" class="ri-save-line mr-0"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No expenses found.</td>
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

    // Save updated expense details
    $('.save-btn').on('click', function() {
        var $row = $(this).closest('tr'); // Get the closest table row for the clicked button
        var expenseId = $(this).data('expense-id'); // Use data attribute for expense ID
        var expenseDate = $row.find('[data-field="expense_date"]').text().trim(); // Get the text from the editable cell
        var description = $row.find('[data-field="description"]').text().trim();
        var amount = $row.find('[data-field="amount"]').text().trim();
        var createdBy = $row.find('[data-field="created_by"]').text().trim();

        // Validate that all fields are filled
        if (!expenseDate || !description || !amount || !createdBy) {
            alert('Please fill in all fields before saving.');
            return; // Stop execution if any field is empty
        }

        // Send update request
        $.post('page-list-expense.php', {
            expense_id: expenseId, // Corrected to match PHP code
            expense_date: expenseDate,
            description: description,
            amount: amount,
            created_by: createdBy,
            action: 'update'
        })
        .done(function(response) {
            try {
                response = JSON.parse(response); // Parse JSON response
                if (response.success) { // Check for a successful response
                    alert('Expense updated successfully!');
                    location.reload(); // Reload to see updates
                } else {
                    alert('Error updating expense: ' + response.error);
                }
            } catch (e) {
                alert('Invalid server response for update.');
            }
        })
        .fail(function() {
            alert('Request failed for updating expense.');
        });
    });

    // Delete an expense
    $('.delete-btn').on('click', function() {
        if (confirm('Are you sure you want to delete this expense?')) {
            var expenseId = $(this).data('expense-id');
            $.post('page-list-expense.php', {
                expense_id: expenseId, // Corrected to match PHP code
                action: 'delete'
            })
            .done(function(response) {
                try {
                    response = JSON.parse(response); // Parse JSON response
                    if (response.success) { // Check for a successful response
                        alert('Expense deleted successfully!');
                        location.reload(); // Refresh the page to reflect changes
                    } else {
                        alert('Error deleting expense: ' + response.error);
                    }
                } catch (e) {
                    alert('Delete Successful.');
                    location.reload(); // Reload to see updates
                }
            })
            .fail(function() {
                alert('Request failed for deleting expense.');
            });
        }
    });

    // Save an expense as PDF
    $('.save-pdf-btn').on('click', function() {
        var expenseId = $(this).data('expense-id');
        if (expenseId) {
            // Redirect to the PDF generation page
            window.location.href = 'pdf_generate.php?expense_id=' + expenseId;
        } else {
            alert('Invalid expense ID.'); // Alert if the ID is not valid
        }
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