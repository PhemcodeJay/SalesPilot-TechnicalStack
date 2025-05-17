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
    <meta charset="utf-8">
      <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
      <title>Terms of Service</title>
      
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
        <div id="faqAccordion" class="container-fluid">
            <div class="row">
                <div class="col-lg-12">
                    <div class="iq-accordion career-style faq-style">
                        <div class="card iq-accordion-block">
                            <div class="active-faq clearfix" id="headingOne">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-sm-12">
                                            <a role="contentinfo" class="accordion-title" data-toggle="collapse"
                                                data-target="#collapseOne" aria-expanded="true" aria-controls="collapseOne">
                                                <span><p style="font-weight: bold; text-decoration: underline;"><strong>Introduction</strong></p></span>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-details collapse show" id="collapseOne" aria-labelledby="headingOne"
                                data-parent="#faqAccordion">
                                <p class="mb-0">
                                <p style="text-decoration: underline;"><strong>Welcome to SalesPILOT!</strong> </p>
                                <p>These Terms of Service govern your use of our web application for inventory management and sales analytics. By accessing or using SalesPILOT, you agree to comply with and be bound by these Terms. If you do not agree to these Terms, please do not use our service. </p>
                            </div>
                        </div>
                        <div class="card iq-accordion-block">
                            <div class="active-faq clearfix" id="headingTwo">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-sm-12"><a role="contentinfo" class="accordion-title collapsed"
                                                data-toggle="collapse" data-target="#collapseTwo" aria-expanded="false"
                                                aria-controls="collapseTwo"><span><p style="font-weight: bold; text-decoration: underline;"><strong> Use of the Service
                                            </p></strong></span> </a></div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-details collapse" id="collapseTwo" aria-labelledby="headingTwo"
                                data-parent="#faqAccordion">
                                <p class="mb-0">
                                <p style="text-decoration: underline;"><strong>Eligibility</strong></p>
                                <p>You must be at least 18 years old to use SalesPILOT. By using our service, you represent and warrant that you meet this requirement.</p>
                                    
                                <p style="text-decoration: underline;"><strong>Account Registration</strong></p>
                                <p>To access certain features of SalesPILOT, you may be required to create an account.</p> 
                                
                                <p style="text-decoration: underline;"><strong>You agree to</strong></p>   
                                <p>Provide accurate, current, and complete information during the registration process.</p>
                                <p>Maintain and promptly update your account information.</p>
                                <p>Keep your password secure and not disclose it to any third party.</p>
                                <p>Accept responsibility for all activities that occur under your account.
                                </p>
                            </div>
                        </div>
                        <div class="card iq-accordion-block ">
                            <div class="active-faq clearfix" id="headingThree">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-sm-12"><a role="contentinfo" class="accordion-title collapsed"
                                                data-toggle="collapse" data-target="#collapseThree" aria-expanded="false"
                                                aria-controls="collapseThree"><span><p style="font-weight: bold; text-decoration: underline;"><strong>User Responsibilities</p></strong> </span> </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-details collapse" id="collapseThree" aria-labelledby="headingThree"
                                data-parent="#faqAccordion">
                                <p class="mb-0">
                                <p style="text-decoration: underline;"><strong>Compliance with Laws</strong></p>
                                <p>You agree to use SalesPILOT in compliance with all applicable laws and regulations. You are solely responsible for ensuring that your use of the service complies with all applicable laws, including data protection and privacy laws.</p>
                                    
                                <p style="text-decoration: underline;"><strong>Prohibited Activities</strong></p>
                                <p style="text-decoration: underline;"><strong> You agree not to</strong></p>
                                    
                                <p>Use the service for any unlawful purposes.</p>
                                <p>Engage in any activity that could harm or interfere with the operation of the service.</p>
                                <p>Attempt to gain unauthorized access to any part of the service or its related systems or networks.</p>
                                <p>Use the service to store, transmit, or distribute any illegal or unauthorized content.</p>
                                <p>Use any automated means to access the service without our permission.
                                </p>
                            </div>
                        </div>
                        <div class="card iq-accordion-block ">
                            <div class="active-faq clearfix" id="headingFour">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-sm-12"><a role="contentinfo" class="accordion-title collapsed"
                                                data-toggle="collapse" data-target="#collapseFour" aria-expanded="false"
                                                aria-controls="collapseFour"><span><p style="font-weight: bold; text-decoration: underline;"><strong> Intellectual Property</strong></p> </span> </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-details collapse" id="collapseFour" aria-labelledby="headingFour"
                                data-parent="#faqAccordion">
                                <p class="mb-0">
                                <p style="text-decoration: underline;"><strong>Ownership</strong></p>
                                <p>SalesPILOT and its original content, features, and functionality are and will remain the exclusive property of SalesPILOT and its licensors. The service is protected by copyright, trademark, and other laws of both the United States and foreign countries.</p>
                                    
                                <p style="text-decoration: underline;"><strong>License</strong></p>
                                <p>We grant you a limited, non-exclusive, non-transferable, and revocable license to use the service for your internal business purposes, subject to these Terms.</p>
                                    
                                <p style="text-decoration: underline;"><strong>Termination</strong></p>
                                <p>We may terminate or suspend your account and access to the service immediately, without prior notice or liability, if you breach these Terms. Upon termination, your right to use the service will immediately cease. If you wish to terminate your account, you may do so by contacting us.
                                </p>
                            </div>
                        </div>
                        <div class="card iq-accordion-block">
                            <div class="active-faq clearfix" id="headingFive">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-sm-12"><a role="contentinfo" class="accordion-title collapsed"
                                                data-toggle="collapse" data-target="#collapseFive" aria-expanded="false"
                                                aria-controls="collapseFive"><span><p style="font-weight: bold; text-decoration: underline;"><strong> Limitation of Liability</strong></p> </span> </a></div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-details collapse" id="collapseFive" aria-labelledby="headingFive"
                                data-parent="#faqAccordion">
                                <p class="mb-0">
                                <p><strong>To the maximum extent permitted by law, </strong></p>
                                <p>SalesPILOT and its affiliates, directors, employees, agents, and partners shall not be liable for any indirect, incidental, special, consequential, or punitive damages, or any loss of profits or revenues, whether incurred directly or indirectly, or any loss of data, use, goodwill, or other intangible losses, resulting from:</p>
                                    
                                    <p>Your use or inability to use the service.</p>
                                    <p>Any unauthorized access to or use of our servers and/or any personal information stored therein.</p>
                                    <p>Any interruption or cessation of transmission to or from the service.</p>
                                    <p>Any bugs, viruses, trojan horses, or the like that may be transmitted to or through the service by any third party.</p>
                                    <p>Any errors or omissions in any content or for any loss or damage incurred as a result of the use of any content posted, emailed, transmitted, or otherwise made available through the service.</p>
                                    <p style="text-decoration: underline;"><strong>Disclaimer of Warranties</strong></p>
                                    <p>The service is provided on an "as is" and "as available" basis. SalesPILOT makes no representations or warranties of any kind, express or implied, including but not limited to the implied warranties of merchantability, fitness for a particular purpose, and non-infringement.</p>
                                    
                                    <p style="text-decoration: underline;"><strong>Governing Law</strong></p>
                                    <p>These Terms shall be governed and construed in accordance with the global laws governing application develpoment and usage, without regard to its conflict of law provisions.
                                </p>
                            </div>
                        </div>
                        <div class="card iq-accordion-block">
                            <div class="active-faq clearfix" id="headingSix">
                                <div class="container-fluid">
                                    <div class="row">
                                        <div class="col-sm-12"><a role="contentinfo" class="accordion-title collapsed"
                                                data-toggle="collapse" data-target="#collapseSix" aria-expanded="false"
                                                aria-controls="collapseSix"><span><p style="font-weight: bold; text-decoration: underline;"><strong> Changes to These Terms</strong></p> </span> </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="accordion-details collapse" id="collapseSix" aria-labelledby="headingSix"
                                data-parent="#faqAccordion">
                                <p class="mb-0">
                                <p>We reserve the right, at our sole discretion, to modify or replace these Terms at any time. If a revision is material, we will provide at least 30 days' notice prior to any new terms taking effect. </p>
                                <p>By continuing to access or use our service after those revisions become effective, you agree to be bound by the revised terms.</p>
                                    
                                <p style="text-decoration: underline;"><strong>Contact Us</strong></p>
                                <p>If you have any questions about these Terms, please contact us at
                                </p>
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