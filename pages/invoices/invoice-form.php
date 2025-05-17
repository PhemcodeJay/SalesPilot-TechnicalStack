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
      <link rel="shortcut icon" href="http://localhost/salespilot/assets/images/favicon-blue.ico" />
      <link rel="stylesheet" href="http://localhost/salespilot/assets/css/backend-plugin.min.css">
      <link rel="stylesheet" href="http://localhost/salespilot/assets/css/backend.css?v=1.0.0">
      <link rel="stylesheet" href="http://localhost/salespilot/assets/vendor/@fortawesome/fontawesome-free/css/all.min.css">
      <link rel="stylesheet" href="http://localhost/salespilot/assets/vendor/line-awesome/dist/line-awesome/css/line-awesome.min.css">
      <link rel="stylesheet" href="http://localhost/salespilot/assets/vendor/remixicon/fonts/remixicon.css">  </head>
  <body class="  ">
    <!-- loader Start -->
    <div id="loading">
          <div id="loading-center">
          </div>
    </div>
    <!-- loader END -->
    <!-- Wrapper Start -->
    <div class="wrapper">
      
      <div class="iq-sidebar  sidebar-default ">
          <div class="iq-sidebar-logo d-flex align-items-center justify-content-between">
              <a href="http://localhost/salespilot/dashboard.php" class="header-logo">
                  <img src="http://localhost/salespilot/logonew1.jpg" class="img-fluid rounded-normal light-logo" alt="logo"><h5 class="logo-title light-logo ml-3">SalesPilot</h5>
              </a>
              <div class="iq-menu-bt-sidebar ml-0">
                  <i class="las la-bars wrapper-menu"></i>
              </div>
          </div>
          <div class="data-scrollbar" data-scroll="1">
              <nav class="iq-sidebar-menu">
                  <ul id="iq-sidebar-toggle" class="iq-menu">
                      <li class="">
                          <a href="http://localhost/salespilot/dashboard.php" class="svg-icon">                        
                              <svg  class="svg-icon" id="p-dash1" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path><polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline><line x1="12" y1="22.08" x2="12" y2="12"></line>
                              </svg>
                              <span class="ml-4">Dashboards</span>
                          </a>
                      </li>
                      <li class=" ">
                          <a href="#product" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash2" width="20" height="20"  xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"></circle><circle cx="20" cy="21" r="1"></circle>
                                  <path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"></path>
                              </svg>
                              <span class="ml-4">Products</span>
                              <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                              </svg>
                          </a>
                          <ul id="product" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                              <li class="">
                                  <a href="http://localhost/salespilot/page-list-product.php">
                                      <i class="las la-minus"></i><span>List Product</span>
                                  </a>
                              </li>
                              <li class="">
                                  <a href="http://localhost/salespilot/page-add-product.php">
                                      <i class="las la-minus"></i><span>Add Product</span>
                                  </a>
                              </li>
                          </ul>
                      </li>
                      <li class=" ">
                          <a href="#category" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash3" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"></rect>
                                  <path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"></path>
                              </svg>
                              <span class="ml-4">Categories</span>
                              <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                              </svg>
                          </a>
                          <ul id="category" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-category.php">
                                              <i class="las la-minus"></i><span>List Category</span>
                                          </a>
                                  </li>
                             
                          </ul>
                      </li>
                      <li class=" ">
                          <a href="#sale" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash4" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <path d="M21.21 15.89A10 10 0 1 1 8 2.83"></path><path d="M22 12A10 10 0 0 0 12 2v10z"></path>
                              </svg>
                              <span class="ml-4">Sale</span>
                              <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                              </svg>
                          </a>
                          <ul id="sale" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-sale.php">
                                              <i class="las la-minus"></i><span>List Sale</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-add-sale.php">
                                              <i class="las la-minus"></i><span>Add Sale</span>
                                          </a>
                                  </li>
                          </ul>
                      </li>
                      <li class=" ">
                          <a href="#purchase" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash5" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
                                  <line x1="1" y1="10" x2="23" y2="10"></line>
                              </svg>
                              <span class="ml-4">Expenses</span>
                              <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                              </svg>
                          </a>
                          <ul id="purchase" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-expense.php">
                                              <i class="las la-minus"></i><span>List Expenses</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-add-expense.php">
                                              <i class="las la-minus"></i><span>Add Expenses</span>
                                          </a>
                                  </li>
                          </ul>
                      </li>
                      <li class=" ">
                          <a href="#return" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash6" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="4 14 10 14 10 20"></polyline><polyline points="20 10 14 10 14 4"></polyline><line x1="14" y1="10" x2="21" y2="3"></line><line x1="3" y1="21" x2="10" y2="14"></line>
                              </svg>
                              <span class="ml-4">Inventory</span>
                              <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                              </svg>
                          </a>
                          <ul id="return" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-inventory.php">
                                              <i class="las la-minus"></i><span>List Inventory</span>
                                          </a>
                                  </li>
                              
                          </ul>
                      </li>
                      <li class=" ">
                          <a href="#people" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash8" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"></path><circle cx="9" cy="7" r="4"></circle><path d="M23 21v-2a4 4 0 0 0-3-3.87"></path><path d="M16 3.13a4 4 0 0 1 0 7.75"></path>
                              </svg>
                              <span class="ml-4">People</span>
                              <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                  <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                              </svg>
                          </a>
                          <ul id="people" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-customers.php">
                                              <i class="las la-minus"></i><span>Customers</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-add-customers.php">
                                              <i class="las la-minus"></i><span>Add Customers</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-staffs.php">
                                              <i class="las la-minus"></i><span>Staffs</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-add-staffs.php">
                                              <i class="las la-minus"></i><span>Add Staffs</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-list-suppliers.php">
                                              <i class="las la-minus"></i><span>Suppliers</span>
                                          </a>
                                  </li>
                                  <li class="">
                                          <a href="http://localhost/salespilot/page-add-supplier.php">
                                              <i class="las la-minus"></i><span>Add Suppliers</span>
                                          </a>
                                  </li>
                          </ul>
                      </li>
                      <li class=" ">
                        <a href="#otherpage" class="collapsed" data-toggle="collapse" aria-expanded="false">
                              <svg class="svg-icon" id="p-dash9" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <rect x="3" y="3" width="18" height="18" rx="2" ry="2"></rect><rect x="7" y="7" width="3" height="9"></rect><rect x="14" y="7" width="3" height="5"></rect>
                            </svg>
                            <span class="ml-4">Analytics</span>
                            <svg class="svg-icon iq-arrow-right arrow-active" width="20" height="20" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="10 15 15 20 20 15"></polyline><path d="M4 4h7a4 4 0 0 1 4 4v12"></path>
                            </svg>
                        </a>
                        <ul id="otherpage" class="iq-submenu collapse" data-parent="#iq-sidebar-toggle">
                                <li class="">
                                        <a href="http://localhost/salespilot/analytics.php">
                                            <i class="las la-minus"></i><span>Charts</span>
                                        </a>
                                </li>
                                <li class="">
                                        <a href="http://localhost/salespilot/analytics-report.php">
                                            <i class="las la-minus"></i><span>Reports</span>
                                        </a>
                                </li>
                                <li class="">
                                        <a href="http://localhost/salespilot/sales-metrics.php">
                                            <i class="las la-minus"></i><span>Sales Metrics</span>
                                        </a>
                                </li>
                                <li class="">
                                        <a href="http://localhost/salespilot/inventory-metrics.php">
                                            <i class="las la-minus"></i><span>Inventory Metrics</span>
                                        </a>
                                </li>
                                
                        </ul>
                    </li>   
                  </ul>
              </nav>
              <div id="sidebar-bottom" class="position-relative sidebar-bottom">
                  <div class="card border-none">
                      <div class="card-body p-0">
                          
                      </div>
                  </div>
              </div>
              <div class="p-3"></div>
          </div>
          </div>      <div class="iq-top-navbar">
          <div class="iq-navbar-custom">
              <nav class="navbar navbar-expand-lg navbar-light p-0">
                  <div class="iq-navbar-logo d-flex align-items-center justify-content-between">
                      <i class="ri-menu-line wrapper-menu"></i>
                      <a href="http://localhost/salespilot/dashboard.php" class="header-logo">
                          <img src="http://localhost/salespilot/logonew1.jpg" class="img-fluid rounded-normal" alt="logo">
                          <h5 class="logo-title ml-3">SalesPilot</h5>
      
                      </a>
                  </div>
                  <div class="iq-search-bar device-search">
                      <form action="#" class="searchbox">
                          <a class="search-link" href="#"><i class="ri-search-line"></i></a>
                          <input type="text" class="text search-input" placeholder="Search here...">
                      </form>
                  </div>
                  <div class="d-flex align-items-center">
                      <button class="navbar-toggler" type="button" data-toggle="collapse"
                          data-target="#navbarSupportedContent" aria-controls="navbarSupportedContent"
                          aria-label="Toggle navigation">
                          <i class="ri-menu-3-line"></i>
                      </button>
                      <div class="collapse navbar-collapse" id="navbarSupportedContent">
                          <ul class="navbar-nav ml-auto navbar-list align-items-center">
                              <li class="nav-item nav-icon dropdown">
                                  <a href="#" class="search-toggle dropdown-toggle btn border add-btn"
                                      id="dropdownMenuButton02" data-toggle="dropdown" aria-haspopup="true"
                                      aria-expanded="false">
                                      
                                  </a>
                                  
                              </li>
                              <li>
                                  <a href="#" class="btn border add-btn shadow-none mx-2 d-none d-md-block"
                                      data-toggle="modal" data-target="#new-order"><i class="las la-plus mr-2"></i>New
                                      Invoice</a>
                              </li>
                              <li class="nav-item nav-icon search-content">
                                  <a href="#" class="search-toggle rounded" id="dropdownSearch" data-toggle="dropdown"
                                      aria-haspopup="true" aria-expanded="false">
                                      <i class="ri-search-line"></i>
                                  </a>
                                  <div class="iq-search-bar iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownSearch">
                                      <form action="#" class="searchbox p-2">
                                          <div class="form-group mb-0 position-relative">
                                              <input type="text" class="text search-input font-size-12"
                                                  placeholder="type here to search...">
                                              <a href="#" class="search-link"><i class="las la-search"></i></a>
                                          </div>
                                      </form>
                                  </div>
                              </li>
                              <li class="nav-item nav-icon dropdown">
    <a href="#" class="search-toggle dropdown-toggle" id="dropdownMenuButton"
        data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24"
            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
            stroke-linejoin="round" class="feather feather-bell">
            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
        </svg>
        <span class="bg-primary "></span>
    </a>
    <div class="iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownMenuButton">
        <div class="card shadow-none m-0">
            <div class="card-body p-0">
                <div class="cust-title p-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <h5 class="mb-0">Notifications</h5>
                        <a class="badge badge-primary badge-card" href="#">
                            <?= count($inventoryNotifications) + count($reportsNotifications) ?>
                        </a>
                    </div>
                </div>
                <div class="px-3 pt-0 pb-0 sub-card">

                    <?php if (!empty($inventoryNotifications)): ?>
                        <?php foreach ($inventoryNotifications as $notification): ?>
                            <a href="#" class="iq-sub-card">
                                <div class="media align-items-center cust-card py-3 border-bottom">
                                    <div>
                                        <img class="avatar-50 rounded-small"
                                            src="<?= htmlspecialchars($notification['image_path']); ?>" 
                                            alt="<?= htmlspecialchars($notification['product_name']); ?>">
                                    </div>
                                    <div class="media-body ml-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0"><?= htmlspecialchars($notification['product_name']); ?></h6>
                                            <small class="text-dark">
                                                <b>Available: <?= htmlspecialchars($notification['available_stock']); ?></b>
                                            </small>
                                        </div>
                                        <small>Inventory: <?= htmlspecialchars($notification['inventory_qty']); ?>, 
                                        Sales: <?= htmlspecialchars($notification['sales_qty']); ?></small>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No inventory notifications available.</p>
                    <?php endif; ?>

                    <?php if (!empty($reportsNotifications)): ?>
                        <?php foreach ($reportsNotifications as $notification): ?>
                            <a href="#" class="iq-sub-card">
                                <div class="media align-items-center cust-card py-3 border-bottom">
                                    <div>
                                        <img class="avatar-50 rounded-small"
                                            src="<?= htmlspecialchars($notification['image_path']); ?>" 
                                            alt="<?= htmlspecialchars($notification['product_name']); ?>">
                                    </div>
                                    <div class="media-body ml-3">
                                        <div class="d-flex align-items-center justify-content-between">
                                            <h6 class="mb-0"><?= htmlspecialchars($notification['product_name']); ?></h6>
                                            <small class="text-dark">
                                                <b>Revenue: <?= htmlspecialchars($notification['revenue']); ?></b>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <p class="text-center">No reports notifications available.</p>
                    <?php endif; ?>
                </div>
                <a class="right-ic btn btn-primary btn-block position-relative p-2" href="page-list-inventory.php" role="button">
                    View All
                </a>
            </div>
        </div>
    </div>
</li>

                              <li class="nav-item nav-icon dropdown caption-content">
                                  <a href="#" class="search-toggle dropdown-toggle" id="dropdownMenuButton4"
                                      data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                                      <img src="http://localhost/salespilot/<?php echo htmlspecialchars($image_to_display); ?>" 
         alt="profile-img" class="rounded profile-img img-fluid avatar-70">

                                  </a>
                                  <div class="iq-sub-dropdown dropdown-menu" aria-labelledby="dropdownMenuButton">
                                      <div class="card shadow-none m-0">
                                          <div class="card-body p-0 text-center">
                                              <div class="media-body profile-detail text-center">
                                                  <img src="http://localhost/salespilot/assets/images/page-img/profile-bg.jpg" alt="profile-bg"
                                                      class="rounded-top img-fluid mb-4">
                                                      <img src="http://localhost/salespilot/<?php echo htmlspecialchars($image_to_display); ?>" 
         alt="profile-img" class="rounded profile-img img-fluid avatar-70">


                                              </div>
                                              <div class="p-3">
                                                <h5 class="mb-1"><?php echo $email; ?></h5>
                                                <p class="mb-0">Since <?php echo $date; ?></p>
                                                  <div class="d-flex align-items-center justify-content-center mt-3">
                                                      <a href="http://localhost/salespilot/user-profile-edit.php" class="btn border mr-2">Profile</a>
                                                      <a href="logout.php" class="btn border">Sign Out</a>
                                                  </div>
                                              </div>
                                          </div>
                                      </div>
                                  </div>
                              </li>
                          </ul>
                      </div>
                  </div>
              </nav>
          </div>
      </div>
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
                            <img src="http://localhost/salespilot/logonew1.jpg" class="logo-invoice img-fluid mb-3" alt="Logo">
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
<footer class="iq-footer">
    <div class="container-fluid">
    <div class="card">
        <div class="card-body">
            <div class="row">
                <div class="col-lg-6">
                    <ul class="list-inline mb-0">
                        <li class="list-inline-item"><a href="http://localhost/salespilot/privacy-policy.php">Privacy Policy</a></li>
                        <li class="list-inline-item"><a href="http://localhost/salespilot/terms-of-service.php">Terms of Use</a></li>
                        <li class="list-inline-item"><a href="http://localhost/salespilot/subscription.php">Subscriptions</a></li>
                        <li class="list-inline-item"><a href="http://localhost/salespilot/pay.php">Pay Now</a></li>
                        <li class="list-inline-item"><a href="http://localhost/salespilot/help.html">Help</a></li>
                    </ul>
                </div>
                <div class="col-lg-6 text-right">
                    <span class="mr-1"><script>document.write(new Date().getFullYear())</script>©</span> <a href="http://localhost/salespilot/dashboard.php" class="">SalesPilot</a>.
                </div>
            </div>
        </div>
    </div>
</div>
</footer>
<!-- Backend Bundle JavaScript -->
<script src="http://localhost/salespilot/assets/js/backend-bundle.min.js"></script>

<!-- Table Treeview JavaScript -->
<script src="http://localhost/salespilot/assets/js/table-treeview.js"></script>

<!-- app JavaScript -->
<script src="http://localhost/salespilot/assets/js/app.js"></script>
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