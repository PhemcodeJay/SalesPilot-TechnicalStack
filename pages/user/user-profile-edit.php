<?php
session_start();

require_once __DIR__ . '/../../config/config.php'; // Database connection

try {
    // Check if username is set in session
    if (!isset($_SESSION["username"])) {
        throw new Exception("No username found in session.");
    }

    $username = htmlspecialchars($_SESSION["username"]);

    // Retrieve user information from the users table
    $user_query = "SELECT id, username, date, email, phone, location, is_active, role, user_image FROM users WHERE username = :username";
    $stmt = $connection->prepare($user_query);
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user_info = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user_info) {
        throw new Exception("User not found.");
    }

    // Retrieve user details
    $email = htmlspecialchars($user_info['email']);
    $date = htmlspecialchars($user_info['date']);
    $location = htmlspecialchars($_POST['location']);
    $user_id = htmlspecialchars($user_info['id']);
    $existing_image = htmlspecialchars($user_info['user_image']);
    $image_to_display = $existing_image ?: 'uploads/user/default.png'; // Use default image if none exists

    // Check if form is submitted
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sanitize and retrieve form inputs
        $username = htmlspecialchars($_POST['username']);
        $email = htmlspecialchars($_POST['email']);
        $location = htmlspecialchars($_POST['location']);
        $is_active = isset($_POST['is_active']) ? htmlspecialchars($_POST['is_active']) : null;
        $role = isset($_POST['role']) ? htmlspecialchars($_POST['role']) : null;

        // Handle file upload
        if (isset($_FILES['user_image']) && $_FILES['user_image']['error'] == UPLOAD_ERR_OK) {
            // Generate unique file name to avoid collisions (using time() and basename)
            $user_image = time() . '_' . basename($_FILES['user_image']['name']);
            $target_dir = "uploads/user/";
            $target_file = $target_dir . $user_image;

            // Ensure the target directory exists
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0755, true);
            }

            // Check file size (limit to 10MB)
            if ($_FILES['user_image']['size'] > 10000000) {
                exit("Error: File is too large. Maximum allowed size is 10MB.");
            }

            // Only allow JPEG and PNG files
            $allowed_types = ['image/jpeg', 'image/png'];
            $file_type = mime_content_type($_FILES['user_image']['tmp_name']);
            if (!in_array($file_type, $allowed_types)) {
                exit("Error: Invalid file type. Only JPEG and PNG are allowed.");
            }

            // Move the uploaded file
            if (move_uploaded_file($_FILES["user_image"]["tmp_name"], $target_file)) {
                $image_to_save = $target_file;
            } else {
                // Log file upload failure for debugging
                error_log("File upload failed for user ID: $user_id");
                $image_to_save = $existing_image ?: 'uploads/user/default.png'; // Use existing or default image if upload fails
            }
        } else {
            $image_to_save = $existing_image ?: 'uploads/user/default.png'; // Use existing or default image if no new image is uploaded
        }

        // Update user record
        $sql = "UPDATE users SET username=?, email=?, location=?, is_active=?, role=?, user_image=? WHERE id=?";
        $stmt = $connection->prepare($sql);
        $stmt->execute([$username, $email, $location, $is_active, $role, $image_to_save, $user_id]);

        echo "User updated successfully!";
    }

} catch (PDOException $e) {
    error_log("PDO Error: " . $e->getMessage());
    exit("Database Error: " . $e->getMessage());
} catch (Exception $e) {
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

// Fetch the subscription details for the logged-in user (replace `$_SESSION['user_id']` with actual user id)
$user_id = 1; // Example user ID, replace with dynamic value like $_SESSION['user_id'] or from URL parameter
$sql = "SELECT * FROM subscriptions WHERE user_id = :user_id ORDER BY start_date DESC LIMIT 1";
$stmt = $connection->prepare($sql);
$stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
$stmt->execute();

// Check if subscription exists using rowCount() instead of num_rows
if ($stmt->rowCount() > 0) {
    // Get subscription data
    $subscription = $stmt->fetch(PDO::FETCH_ASSOC);
    $subscription_status = $subscription['status'];
    $subscription_plan = $subscription['subscription_plan'];
    $start_date = $subscription['start_date'];
    $end_date = $subscription['end_date'];
    $is_free_trial_used = $subscription['is_free_trial_used'];
} else {
    $subscription_status = 'No active subscription';
    $subscription_plan = '';
    $start_date = '';
    $end_date = '';
    $is_free_trial_used = 0;
}
?>




<!doctype html>
<html lang="en">
  <head>
    <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <title>User Profile</title>
      
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
               <div class="card">
                  <div class="card-body p-0">
                     <div class="iq-edit-list usr-edit">
                        <ul class="iq-edit-profile d-flex nav nav-pills">
                           <li class="col-md-4 p-0">
                              <a class="nav-link active" data-toggle="pill" href="#personal-information">
                              Personal Information
                              </a>
                           </li>
                           
                           <li class="col-md-4 p-0">
                              <a class="nav-link" data-toggle="pill" href="#emailandsms">
                              Settings
                              </a>
                           </li>
                           <li class="col-md-4 p-0">
                              <a class="nav-link" data-toggle="pill" href="#manage-contact">
                              Subscriptions
                              </a>
                           </li>
                        </ul>
                     </div>
                  </div>
               </div>
            </div>
            <div class="col-lg-12">
               <div class="iq-edit-list-data">
                  <div class="tab-content">
                     <div class="tab-pane fade active show" id="personal-information" role="tabpanel">
                        <div class="card">
                           <div class="card-header d-flex justify-content-between">
                              <div class="iq-header-title">
                                 <h4 class="card-title">Personal Information</h4>
                              </div>
                           </div>
                           <div class="card-body">
                           <form action="user-profile-edit.php" method="post" enctype="multipart/form-data">
    <!-- Hidden fields for user ID and existing image -->
    <input type="hidden" name="id" value="<?php echo $user_id; ?>">
    <input type="hidden" name="existing_image" value="<?php echo $existing_image; ?>">

    <!-- Profile Image Section -->
    <div class="form-group row align-items-center">
        <div class="col-md-12">
            <div class="profile-img-edit">
                <div class="crm-profile-img-edit">
                    <!-- Display current or default profile image -->
                    <img class="crm-profile-pic rounded-circle avatar-100" 
                         src="<?php echo $existing_image ?: 'uploads/user/default.png'; ?>" 
                         alt="profile-pic">

                    <!-- Upload icon to trigger file input -->
                    <div class="crm-p-image bg-primary">
                        <i class="las la-pen upload-button" style="cursor: pointer;"></i>
                        <input class="file-upload" type="file" name="user_image" accept="image/*" style="display:none;">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- User Information Fields -->
    <div class="row align-items-center">
        <!-- Username -->
        <div class="form-group col-sm-6">
            <label for="username">Username:</label>
            <input type="text" class="form-control" id="username" name="username" 
                   value="<?php echo htmlspecialchars($user_info['username']); ?>" required>
        </div>

        <!-- Email -->
        <div class="form-group col-sm-6">
            <label for="email">Email:</label>
            <input type="email" class="form-control" id="email" name="email" 
                   value="<?php echo htmlspecialchars($user_info['email']); ?>" required>
        </div>

        <!-- Location -->
        <div class="form-group col-sm-6">
            <label for="location">Location:</label>
            <input type="text" class="form-control" id="location" name="location" 
                   value="<?php echo htmlspecialchars($user_info['location']); ?>" required>
        </div>

        <!-- Role -->
        <div class="form-group col-sm-6">
            <label for="role">Role:</label>
            <select class="form-control" id="role" name="role">
                <option value="admin" <?php echo ($user_info['role'] === 'admin') ? 'selected' : ''; ?>>Admin</option>
                <option value="sales" <?php echo ($user_info['role'] === 'sales') ? 'selected' : ''; ?>>Sales</option>
                <option value="inventory" <?php echo ($user_info['role'] === 'inventory') ? 'selected' : ''; ?>>Inventory</option>
            </select>
        </div>

        <!-- Active Status -->
        <div class="form-group col-sm-6">
            <label for="is_active">Active:</label>
            <select class="form-control" id="is_active" name="is_active">
                <option value="1" <?php echo ($user_info['is_active']) ? 'selected' : ''; ?>>Yes</option>
                <option value="0" <?php echo (!$user_info['is_active']) ? 'selected' : ''; ?>>No</option>
            </select>
        </div>
    </div>
    <!-- Submit and Reset Buttons -->
    <button type="submit" class="btn btn-primary mr-2">Submit</button>
                            <button type="reset" class="btn iq-bg-danger">Cancel</button>
                        </form>
                        </div>
                        </div>
                     </div>
                     <div class="tab-pane fade" id="emailandsms" role="tabpanel">
                        <div class="card">
                           <div class="card-header d-flex justify-content-between">
                              <div class="iq-header-title">
                                 <h4 class="card-title">Email Settings</h4>
                              </div>
                           </div>
                           <div class="card-body">
                           <form action="user-profile-edit.php" method="post" enctype="multipart/form-data">
                            <!-- Display Username and Email -->
                            <div class="form-group row align-items-center">
                                <label class="col-md-3" for="username">Username:</label>
                                <div class="col-md-9">
                                    <input type="text" class="form-control" id="username" name="username" value="<?php echo $user_info['username']; ?>" readonly>
                                </div>
                            </div>
                            <div class="form-group row align-items-center">
                                <label class="col-md-3" for="email">Email:</label>
                                <div class="col-md-9">
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo $user_info['email']; ?>" readonly>
                                </div>
                            </div>
                            
                            <!-- Email Notification Settings -->
                            <div class="form-group row align-items-center">
                                <label class="col-md-3" for="emailnotification">Email Notification:</label>
                                <div class="col-md-9 custom-control custom-switch">
                                    <input type="checkbox" class="custom-control-input" id="emailnotification" checked="">
                                    <label class="custom-control-label" for="emailnotification"></label>
                                </div>
                            </div>

                            <!-- When To Email -->
                            <div class="form-group row align-items-center">
                                <label class="col-md-3" for="email-options">When To Email</label>
                                <div class="col-md-9">
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="email01">
                                        <label class="custom-control-label" for="email01">You have new notifications.</label>
                                    </div>
                                    <div class="custom-control custom-checkbox">
                                        <input type="checkbox" class="custom-control-input" id="email02">
                                        <label class="custom-control-label" for="email02">Send a mail message</label>
                                    </div>
                                    
                                </div>
                            </div>

                           

                            <!-- Submit and Reset Buttons -->
                            <button type="submit" class="btn btn-primary mr-2">Submit</button>
                            <button type="reset" class="btn iq-bg-danger">Cancel</button>
                        </form>
                           </div>
                        </div>
                     </div>
                     <div class="tab-pane fade" id="manage-contact" role="tabpanel">
                        <div class="card">
                           <div class="card-header d-flex justify-content-between">
                              <div class="iq-header-title">
                                 <h4 class="card-title">Manage Subscription</h4>
                              </div>
                           </div>
                           <div class="card-body">
                           <form action="user-profile-edit.php" method="post" enctype="multipart/form-data">
                            <!-- Display Username and Email -->
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" class="form-control" id="username" name="username" value="<?php echo $user_info['username']; ?>" readonly>
                            </div>
                            <div class="form-group">
                                <label for="email">Email:</label>
                                <input type="email" class="form-control" id="email" name="email" value="<?php echo $user_info['email']; ?>" readonly>
                            </div>
                            
                            <!-- Contact Number and Location -->
                            
                            <div class="form-group">
                                <label for="location">Location:</label>
                                <input type="text" class="form-control" id="location" name="location" value="<?php echo $user_info['location']; ?>">
                            </div>

                            <!-- Subscription Status -->
                            <div class="form-group">
                                <label for="subscription_status">Subscription Status:</label>
                                <input type="text" class="form-control" id="subscription_status" name="subscription_status" 
                                    value="<?php echo htmlspecialchars($subscription_status); ?>" readonly>
                            </div>
                            
                            <!-- Submit and Reset Buttons -->
                            <button type="submit" class="btn btn-primary mr-2">Submit</button>
                            <button type="reset" class="btn iq-bg-danger">Cancel</button>
                            <a href="http://localhost:8000/pages/payment/subscription.php" class="btn btn-secondary mr-2">Go to Subscriptions</a>
                        </form>
                           </div>
                        </div>
                     </div>
                        
                  </div>
               </div>
            </div>
         </div>
         
      </div>
      </div>
    </div>
    <!-- Wrapper End-->
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/partials/footer.php' ; ?>
    <script src="http://localhost:8000/assets/js/backend-bundle.min.js"></script>
    
    <!-- Table Treeview JavaScript -->
    <script src="http://localhost:8000/assets/js/table-treeview.js"></script>
    
    <!-- app JavaScript -->
    <script src="http://localhost:8000/assets/js/app.js"></script>
    <!-- JavaScript to handle file upload trigger -->
<script>
    document.querySelector('.upload-button').addEventListener('click', function() {
        document.querySelector('.file-upload').click(); // Trigger the hidden file input when the icon is clicked
    });
</script>
  </body>
</html>