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

    // Retrieve user information from the users table
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

    // Retrieve staff from the staff table
    $staff_query = "SELECT staff_id, staff_name, staff_email, staff_phone, position FROM staffs";
    $stmt = $connection->prepare($staff_query);
    $stmt->execute();
    $staff = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Handle form actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;
    $staff_id = $_POST['staff_id'] ?? null;

    try {
        // Ensure $connection is available
        if (!isset($connection)) {
            throw new Exception("Database connection not established.");
        }

        // Handle delete action
        if ($action === 'delete') {
            if (!$staff_id) {
                throw new Exception("Staff ID is required for deletion.");
            }

            $delete_query = "DELETE FROM staffs WHERE staff_id = :staff_id";
            $stmt = $connection->prepare($delete_query);
            $stmt->bindParam(':staff_id', $staff_id, PDO::PARAM_INT);

            if ($stmt->execute()) {
                echo json_encode(['success' => 'Staff deleted']);
            } else {
                echo json_encode(['error' => 'Failed to delete staff']);
            }
            exit;
        }

        // Handle update action
        if ($action === 'update') {
            $staff_name = $_POST['staff_name'] ?? null;
            $staff_email = $_POST['staff_email'] ?? null;
            $staff_phone = $_POST['staff_phone'] ?? null;
            $position = $_POST['position'] ?? null;

            if ($staff_id && $staff_name && $staff_email && $staff_phone && $position) {
                $update_query = "UPDATE staffs 
                                 SET staff_name = :staff_name,  
                                     staff_email = :staff_email, 
                                     staff_phone = :staff_phone, 
                                     position = :position
                                 WHERE staff_id = :staff_id";
                $stmt = $connection->prepare($update_query);
                $stmt->bindParam(':staff_name', $staff_name);
                $stmt->bindParam(':staff_email', $staff_email);
                $stmt->bindParam(':staff_phone', $staff_phone);
                $stmt->bindParam(':position', $position);
                $stmt->bindParam(':staff_id', $staff_id, PDO::PARAM_INT);

                if ($stmt->execute()) {
                    echo json_encode(['success' => 'Staff updated']);
                } else {
                    echo json_encode(['error' => 'Failed to update staff']);
                }
            } else {
                echo json_encode(['error' => 'Incomplete form data']);
            }
            exit;
        }

        // Handle save as PDF action
        if ($action === 'save_pdf') {
            if (!$staff_id) {
                throw new Exception("Staff ID is required for generating PDF.");
            }

            // Fetch staff data
            $query = "SELECT * FROM staffs WHERE staff_id = :staff_id";
            $stmt = $connection->prepare($query);
            $stmt->bindParam(':staff_id', $staff_id, PDO::PARAM_INT);
            $stmt->execute();
            $staff = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($staff) {
                // Generate PDF using FPDF
                require 'fpdf.php';
                $pdf = new FPDF();
                $pdf->AddPage();
                $pdf->SetFont('Arial', 'B', 16);
                $pdf->Cell(40, 10, 'Staff Details');
                $pdf->Ln();
                $pdf->SetFont('Arial', '', 12);
                $pdf->Cell(40, 10, 'Staff Name: ' . $staff['staff_name']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Staff Email: ' . $staff['staff_email']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Staff Phone: ' . $staff['staff_phone']);
                $pdf->Ln();
                $pdf->Cell(40, 10, 'Position: ' . $staff['position']);

                $pdf->Output('D', 'staff_' . $staff_id . '.pdf');
            } else {
                echo json_encode(['error' => 'Staff not found']);
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
      <title>List Staff</title>
      
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
                                  <input type="text" class="form-control" placeholder="Enter Customer Name">
                              </div>
                              <div class="col-lg-12 mt-4">
                                  <div class="d-flex flex-wrap align-items-ceter justify-content-center">
                                      <div class="btn btn-primary mr-4" data-dismiss="modal">Cancel</div>
                                      <div class="btn btn-outline-primary" data-dismiss="modal">Create</div>
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
                        <h4 class="mb-3">Staffs Records</h4>
                        <p class="mb-0">A dashboard offers an overview of the staff list, granting access to essential data, functions, and controls for managing staff sales. </p>
                    </div>
                    <a href="page-add-staffs.php" class="btn btn-primary add-list"><i class="las la-plus mr-3"></i>Add Staff</a>
                </div>
            </div>
            <div class="col-lg-12">
            <div class="table-responsive rounded mb-3">
            <table class="data-tables table mb-0 tbl-server-info">
    <thead class="bg-white text-uppercase">
        <tr class="light light-data">
            <th>Name</th>
            <th>Email</th>
            <th>Phone</th>
            <th>Position</th>
            <th>Action</th>
        </tr>
    </thead>
    <tbody class="light-body">
        <?php if (!empty($staff)): ?>
            <?php foreach ($staff as $member): ?>
                <tr data-staff-id="<?php echo htmlspecialchars($member['staff_id']); ?>">
                    <td contenteditable="true" class="editable" data-field="staff_name"><?php echo htmlspecialchars($member['staff_name']); ?></td>
                    <td contenteditable="true" class="editable" data-field="staff_email"><?php echo htmlspecialchars($member['staff_email']); ?></td>
                    <td contenteditable="true" class="editable" data-field="staff_phone"><?php echo htmlspecialchars($member['staff_phone']); ?></td>
                    <td contenteditable="true" class="editable" data-field="position"><?php echo htmlspecialchars($member['position']); ?></td>
                    <td>
                        <button type="button" class="btn btn-success edit-btn" data-action="save" data-staff-id="<?php echo htmlspecialchars($member['staff_id']); ?>">
                            <i data-toggle="tooltip" data-placement="top" title="Update" class="ri-pencil-line mr-0"></i>
                        </button>
                        <button type="button" class="btn btn-warning delete-btn" data-action="delete" data-staff-id="<?php echo htmlspecialchars($member['staff_id']); ?>">
                            <i data-toggle="tooltip" data-placement="top" title="Delete" class="ri-delete-bin-line mr-0"></i>
                        </button>
                        <button type="button" class="btn btn-info save-pdf-btn" data-action="save_pdf" data-staff-id="<?php echo htmlspecialchars($member['staff_id']); ?>">
                            <i data-toggle="tooltip" data-placement="top" title="Save as PDF" class="ri-save-line mr-0"></i>
                        </button>
                    </td>
                </tr>
            <?php endforeach; ?>
        <?php else: ?>
            <tr>
                <td colspan="5">No staff data found.</td>
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
    // Inline editing
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
            if (e.which === 13) {
                $(this).blur();
            }
        });
    });

    // Save updated staff details
    $(document).on('click', '.edit-btn', function() {
        let $row = $(this).closest('tr');
        let staffId = $(this).data('staff-id');
        let staffName = $row.find('[data-field="staff_name"]').text().trim();
        let staffEmail = $row.find('[data-field="staff_email"]').text().trim();
        let staffPhone = $row.find('[data-field="staff_phone"]').text().trim();
        let position = $row.find('[data-field="position"]').text().trim();

        if (!staffName || !staffEmail || !staffPhone || !position) {
            alert('Please fill in all fields before saving.');
            return;
        }

        $.post('page-list-staffs.php', {
            staff_id: staffId,
            staff_name: staffName,
            staff_email: staffEmail,
            staff_phone: staffPhone,
            position: position,
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
            alert('Error updating staff.');
        });
    });

    // Delete staff member
    $(document).on('click', '.delete-btn', function() {
        if (confirm('Are you sure you want to delete this staff member?')) {
            let staffId = $(this).data('staff-id');

            $.post('page-list-staffs.php', {
                staff_id: staffId,
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
                alert('Error deleting staff.');
            });
        }
    });

    // Save staff details as PDF
    $(document).on('click', '.save-pdf-btn', function() {
        let staffId = $(this).data('staff-id');
        window.location.href = 'pdf_generate.php?staff_id=' + staffId;
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