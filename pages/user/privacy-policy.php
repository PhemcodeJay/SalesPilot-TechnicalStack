<?php
session_start();

require_once __DIR__ . '/../../config/config.php'; // Includes database connection

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

<meta content="" name="Boost your business efficiency with SalesPILOT – the ultimate sales management app. Track leads, manage clients, and increase revenue effortlessly with our user-friendly platform.">
  <meta content="" name="Sales productivity tools, Sales and Client management, Business efficiency tools">
      <title>Privacy Policy</title>
      
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
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">Introduction</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p style="font-weight: bold; text-decoration: underline;">Welcome to SalesPILOT! </p>
                            <p>We value your privacy and are committed to protecting your personal information. </p>
                            <p>This Privacy Policy outlines our practices regarding the collection, use, and disclosure of information when you use our web application for inventory management and sales analytics.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">Information We Collect</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p>
                            <p style="font-weight: bold; text-decoration: underline;"><strong>Personal Information</strong></p>
                            <p style="text-decoration: underline;"><strong>User Account Information</strong></p>
                            <p> When you register for an account, we collect your name, email address, and other contact information.</p>
                            <p style="text-decoration: underline;"><strong>Customer and Staff Data</strong> </p>
                            <p>We collect information about your customers and staff, including names, email addresses, and transaction details.</p>
                            <p style="font-weight: bold; text-decoration: underline;"><strong>Usage Data</strong></p>
                            <p style="text-decoration: underline;"><strong>Log Data</strong> </p>
                            <p>We collect information that your browser sends whenever you visit our web app. This may include your IP address, browser type, browser version, the pages of our app that you visit, the time and date of your visit, the time spent on those pages, and other statistics.</p>
                            <p style="text-decoration: underline;"><strong>Cookies and Tracking Technologies</strong> </p>
                            <p>We use cookies and similar tracking technologies to track activity on our app and hold certain information.</p>
                            <p style="font-weight: bold; text-decoration: underline;"><strong>Inventory and Sales Data</strong></p>
                            <p style="text-decoration: underline;"><strong>Product Information</strong> </p>
                            <p>Details about the products you manage through the app, including product types, categories, and inventory quantities.</p>
                            <p style="text-decoration: underline;"><strong>Sales Transactions</strong> </p>
                            <p>Information related to sales transactions, such as sales quantities, customer and staff involvement, and transaction dates. </p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">How We Use Your Information</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p style="font-weight: bold; text-decoration: underline;"><strong>To Provide and Maintain Our Service</strong></p>
                            <p>We use the collected data to operate and maintain our web app, including managing your inventory and sales analytics.</p>
                                
                            <p style="font-weight: bold; text-decoration: underline;"><strong>To Improve Our Service</strong></p>
                            <p>We use your information to understand how our service is used and to enhance user experience, fix issues, and develop new features.</p>
                                
                            <p style="font-weight: bold; text-decoration: underline;"><strong>To Communicate With You</strong></p>
                            <p> We may use your contact information to send you updates, notifications, and promotional materials. You can opt out of receiving these communications at any time.</p>
                                
                            <p style="font-weight: bold; text-decoration: underline;"><strong>To Ensure Security</strong></p>
                            <p>We use your information to monitor for and address security issues, and to prevent fraudulent activity.</p>
                        </div>
                    </div>
                    
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">Sharing and Disclosure of Information</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p style="font-weight: bold; text-decoration: underline;">Third-Party Service Providers</p>
                            <p>We may employ third-party companies and individuals to facilitate our service, provide the service on our behalf, perform service-related tasks, or assist us in analyzing how our service is used. These third parties have access to your personal information only to perform these tasks and are obligated not to disclose or use it for any other purpose.</p>
                                
                            <p style="font-weight: bold; text-decoration: underline;">Legal Requirements</p>
                            <p>We may disclose your personal information in the good faith belief that such action is necessary to:</p>
                                
                            <p>Comply with a legal obligation.</p>
                            <p>Protect and defend the rights or property of SalesPILOT.</p>
                            <p>Prevent or investigate possible wrongdoing in connection with the service.</p>
                            <p>Protect the personal safety of users of the service or the public.</p>
                            <p>Protect against legal liability.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">Data Security</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p>We prioritize the security of your data and use commercially acceptable means to protect it. However, no method of transmission over the internet or electronic storage is 100% secure. </p>
                            <p>While we strive to use acceptable means to protect your personal information, we cannot guarantee its absolute security.</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">Your Rights</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p>You have the right to access, correct, update, or delete your personal information. </p>
                            <p>You can do this directly within your account settings or by contacting us. We will respond to your request as soon as possible..</p>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-header d-flex justify-content-between">
                            <div class="header-title">
                                <h4 class="card-title">Changes to This Privacy Policy</h4>
                            </div>
                        </div>
                        <div class="card-body">
                            <p>We may update our Privacy Policy from time to time. </p>
                            <p>We will notify you of any changes by posting the new Privacy Policy on this page and updating the "Effective Date" at the top of this Privacy Policy. </p>
                            <p>You are advised to review this Privacy Policy</p>
                        </div>
                    </div>
                    
                </div>
            </div>
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
    <script>
document.getElementById('createButton').addEventListener('click', function() {
    // Optional: Validate input or perform any additional checks here
    
    // Redirect to invoice-form.php
    window.location.href = 'invoice-form.php';
});
</script>
  </body>
  </html>