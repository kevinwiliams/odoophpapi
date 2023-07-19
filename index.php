<?php

    include 'include/connection/mysql_db.php';
    // API endpoint to retrieve data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        // Establish a connection to your MySQL database
        $connection = mysqli_connect($host, $username, $password, $database);

        if (!$connection) {
            echo "Failed to connect to the database.";
            exit;
        }
    
        // Query to fetch data from the database
        $query = "SELECT * FROM users WHERE is_active = 1";
        $result = mysqli_query($connection, $query);
    
        // Check if the query execution was successful
        if (!$result) {
            echo "Query execution failed.";
            exit;
        }
    
        // Fetch the data from the result set
        $data = mysqli_fetch_all($result, MYSQLI_ASSOC);
    
        // Set the response header and encode the data as JSON
        header('Content-Type: application/json');
        echo json_encode($data);
    
        // Close the database connection
        mysqli_close($connection);
    
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'load') {
        

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        if ($uid) {
            echo 'Autheniticated';
        } else {
            echo 'Not authenticated';
        }

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");

        
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);

        $response = file_get_contents('https://odoophpapi.test', false, $context);

        // Process the API response
        if ($response) {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);

            // Process the data as needed
            foreach ($data as $item) {
                $itemData = 
                [
                    'name' => $item['first_name'].' '. $item['last_name'],
                    'phone'  => $item['contact_number'], //area code required
                    'email'  => $item['email'],
                    // 'function' => 'Developer'
                ];

                $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', [$itemData]); 
                
                
            }
            $response = [ 'status' => 'success', 'idCreated' => $new_id];
            echo json_encode($response, JSON_PRETTY_PRINT);

        } else {
            $response = [ 'status' => 'failed'];
            echo json_encode($response, JSON_PRETTY_PRINT);
            //echo 'API request failed.';
            // Handle the failure as needed
        }
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'contact') {
        //posted fields
        $postData = file_get_contents('php://input');
        $contactData = json_decode($postData, true);

        // Extract invoice fields from the JSON data
        $name = $contactData['name'];
        $email = $contactData['email'];
        $phone = $contactData['phone'];
        $company = $contactData['company_id'];

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        if ($uid) {
            echo 'Autheniticated';
        } else {
            echo 'Not authenticated';
        }

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");

        $itemData = 
                [[
                    'name' => $name,
                    'phone'  => $phone, //area code required
                    'email'  => $email,
                    'company_id' => 1,
                    // 'commercial_partner_id' => 52,
                    // 'company_name' => 'My Company (San Francisco)',
                    'is_company' => false
                ]];

        $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
        $response = [ 'status' => 'success', 'id_created' => $new_id];
        echo json_encode($response, JSON_PRETTY_PRINT);
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'company') {
         //posted fields
         $postData = file_get_contents('php://input');
         $companyData = json_decode($postData, true);

        //posted fields
        $companyName = $companyData['name'];
        $companyEmail = $companyData['email'];
        $companyPhone = $companyData['phone'];
        $currency = $companyData['currency'];
        $country = $companyData['country'];
        $fiscalLastDay = $companyData['fiscal_last_day'] ?? 31;


        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        if ($uid) {
            echo 'Autheniticated';
        } else {
            echo 'Not authenticated';
        }

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");

        // Fetch currency id based on name (e.g., 'USD')
        $currenyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
        $currenyId = $currenyId[0] ?? false;

        $countryId = $models->execute_kw($db, $uid, $password, 'res.country', 'search', [[['code', '=', $country]]]);
        $countryId = $countryId[0] ?? false;
      
        $companyData = [
            'name' => $companyName, // Name of the company
            'email' => $companyEmail,
            'phone' => $companyPhone,
            'currency_id' => intval($currenyId), // ID of the currency used in the company
            'account_fiscal_country_id' => $countryId, // ID of the country where the company is located
            //'chart_template_id' => 1, // Link the company to an account chart template (fiscal localization)
            // // Other company data...
            //journal
        ];
        
        $newCompanyId = $models->execute_kw($db, $uid, $password, 'res.company', 'create', [$companyData]);
        $response = [ 'status' => 'success', 'id_created' => $newCompanyId];
        echo json_encode($response, JSON_PRETTY_PRINT);

        if (is_int($newCompanyId)) {
       
            $chartTemplateId = 1;
            // Configure fiscal period
            $settingsData = [
                'company_id' => $newCompanyId,
                // 'chart_template_id' => 1, // Link the company to an account chart template (fiscal localization)
                'fiscalyear_last_day' => $fiscalLastDay, // Last day of the fiscal year
                //'fiscalyear_last_month' => 12, // Last month of the fiscal year
                'fiscalyear_lock_date' => '2023-01-01', // Lock date for fiscal year
                // Other fiscal period configuration...
            ];
            $configSettingsId = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'create', [$settingsData]);
            $response = [ 'status' => 'success', 'id_created' => $configSettingsId];
            echo json_encode($response, JSON_PRETTY_PRINT);

            // Update the 'res.config.settings' record to apply fiscal localization
            $updateSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'write', [
                [$configSettingsId],
                [
                    'chart_template_id' => 1, // Replace with the ID of the desired chart template
                ],
            ]);
            $response = [ 'status' => 'success', 'settings_updated' => $updateSettings];
            echo json_encode($response, JSON_PRETTY_PRINT);

            // Apply fiscal localization and load chart of accounts
            $localizationApplied = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
            $response = ['status' => 'success', 'localization_applied' => $localizationApplied];
            echo json_encode($response, JSON_PRETTY_PRINT);
            // Update the company's chart template
            $applyChartTemp = $models->execute_kw($db, $uid, $password, 'res.company', 'write', [[$newCompanyId], ['chart_template_id' => $chartTemplateId]]);
            $response = ['status' => 'success', 'chart_template_applied' => $applyChartTemp];
            echo json_encode($response, JSON_PRETTY_PRINT);

            // $updatedSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
            // $response = [ 'status' => 'success', 'is_exe' => $updatedSettings];
            // echo json_encode($response, JSON_PRETTY_PRINT);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'inv') {
        //posted fields
        $postData = file_get_contents('php://input');
        $invoiceInfo = json_decode($postData, true);

        //posted fields
       $invoiceNumber = $invoiceInfo['name'] ?? false;
       $partnerId = $invoiceInfo['customer_id'];
       $invoiceDate = $invoiceInfo['invoice_date'];
       $invoiceDateDue = $invoiceInfo['invoice_date_due'];
       $currency = $invoiceInfo['currency'];
       $paymentTerm = $invoiceInfo['payment_term'];
       $accountId = $invoiceInfo['account_id'] ?? 21;
       $invoiceLines = $invoiceInfo['invoice_lines'];
        //connect to odoo
       include('include/connection/odoo_db.php');
       require_once('include/ripcord/ripcord.php');
       $common = ripcord::client("$url/xmlrpc/2/common");
       $uid = $common->authenticate($db, $username, $password, array());

       if ($uid)
           echo 'Autheniticated';
       else
           echo 'Not authenticated';
       
        //load odoo models
       $models = ripcord::client("$url/xmlrpc/2/object");
    
       //create invoice with associated partner_id/customer
       $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [['move_type' => 'out_invoice', 'partner_id' => $partnerId]]); 
       $response = [ 'status' => 'success', 'id_created' => $invoiceId ];
        echo json_encode($response);

        //if ID is created 
        if (is_int($invoiceId)) {

            // Fetch currency id based on name (e.g., 'USD')
            $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
            $currencyId = $currencyId[0] ?? false;
            //invoice data
            $invoiceLineIds = [];
            foreach ($invoiceLines as $line) {
                $invoiceLineIds[] = [0, false, [
                    'product_id' => $line['product_id'],
                    'name' => $line['name'] ?? false,
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price'] ?? false,
                    'account_id' => $accountId,
                    'tax_ids' => $line['tax_ids'] ?? []
                ]];
            }

            $invoiceData = [
                'name' => $invoiceNumber,
                'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                //   'invoice_date_due' =>  $invoiceDateDue,
                'company_id' => 1, // Company associated with the invoice
                'company_currency_id' => $currencyId,
                'currency_id' => $currencyId, // Currency used in the invoice
                'invoice_payment_term_id' => $paymentTerm, //payment terms
                'invoice_line_ids' => $invoiceLineIds
            ];
            //update created invoice with journal details
            $newInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$invoiceId],$invoiceData]);
    
            $response = [ 'status' => 'success', 'is_updated' => $newInvoiceId ];
            echo json_encode($response);
            
            //POST drafted invoice if information provided is valid
            if ($newInvoiceId) {
                $postedInvoice = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$invoiceId]);
    
                $response = [ 'status' => 'success', 'invoice' => $postedInvoice ];
                echo json_encode($response);
            }
        }
        
       


   }

   if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'pay') {

    include('include/connection/odoo_db.php');
    require_once('include/ripcord/ripcord.php');
    $common = ripcord::client("$url/xmlrpc/2/common");
    $uid = $common->authenticate($db, $username, $password, array());
    

    if ($uid)
        echo 'Autheniticated';
    else
        echo 'Not authenticated';
    

    $models = ripcord::client("$url/xmlrpc/2/object"); 
    //posted fields
    $postData = file_get_contents('php://input');
    $paymentInfo = json_decode($postData, true);

    //posted fields
     $partnerId = $paymentInfo['customer_id'];
     $paymentDate = $paymentInfo['payment_date'];
     $amount = $paymentInfo['amount'];
     $paymentMethodId = $paymentInfo['payment_method'];
     $journalId = $paymentInfo['journalId'] ?? 8;
     $invoiceNumber = $paymentInfo['invoice_num'];

      // Fetch invoice ID based on invoice number (e.g., 'INV/2023/00055')
      $invoiceNum = $invoiceNumber;
      $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['payment_reference', '=', $invoiceNum]]]);
      $invoiceId = $invoiceId[0] ?? false;
      echo ('inoviceId '. $invoiceId );
      // exit;
     
      $context = [
          'active_model' => 'account.move',
          'active_ids' => $invoiceId,
      ];
      
      // Create the payment register record
      $paymentRegisterData = [
          'partner_id' => $partnerId, // ID of the customer or partner
          'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
          'journal_id' => $journalId,
          'payment_method_line_id' => $paymentMethodId,
          'amount' => $amount, // Amount of the payment
      ];
      $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
      $response = ['status' => 'success', 'payment_created' => $paymentRegisterId,];
      echo json_encode($response);
      
      // Create the payments based on the payment register
      if (is_int($paymentRegisterId)) {
          $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
      }

   }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'payment') {

        include('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');
        $common = ripcord::client("$url/xmlrpc/2/common");
        $uid = $common->authenticate($db, $username, $password, array());
        

        if ($uid)
            echo 'Autheniticated';
        else
            echo 'Not authenticated';
        

        $models = ripcord::client("$url/xmlrpc/2/object"); 
        
        //posted fields
         $partnerId = $_POST['partnerId'];
         $paymentDate = $_POST['paymentDate'];
         $amount = $_POST['amount'];
         $paymentMethodId = $_POST['paymentMethodId'];
         $journalId = $_POST['journalId'];
         $invoiceNumber = $_POST['invoiceNumber'];
         
        // create payment with associated partner_id/customer
        $paymentId = $models->execute_kw($db, $uid, $password, 'account.payment', 'create', [['payment_type' => 'inbound', 'partner_id' => $partnerId]]); 
        $response = [ 'status' => 'success', 'id_created' => $paymentId,];
        echo json_encode($response);
        
        // Add payment without attaching to an invoice
        $paymentData = [
            'partner_id' => $partnerId, // ID of the customer or partner
            'date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
            // 'journal_id' => $journalId, // ID of the journal for the payment
            // 'payment_type' => 'inbound', // Type of the payment (e.g., 'inbound' for customer payment)
            'payment_method_id' => $paymentMethodId, // ID of the payment method
            'ref' => $invoiceNumber, // Payment communication/reference
            // 'communication' => $invoiceNumber,
            'amount' => $amount, // Amount of the payment
            'currency_id' => 2, // ID of the currency used for the payment
            // 'company_currency_id' => 2,
            // 'partner_type' => 'customer', // Type of the partner (e.g., 'customer' or 'supplier')
            'state' => 'posted'
        ];

        $newPaymentId = $models->execute_kw($db, $uid, $password, 'account.payment', 'write', [[$paymentId],$paymentData]);
        $response = ['status' => 'success', 'is_updated' => $newPaymentId,];
        echo json_encode($response);

       
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'purchaseorder') {
        $partnerId = $_POST['name'];
        $orderDate = $_POST['email'];
        $accountId = $_POST['phone'];

        include('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');
        $common = ripcord::client("$url/xmlrpc/2/common");
       $uid = $common->authenticate($db, $username, $password, array());
       

       if ($uid) {
           echo 'Autheniticated';
       } else {
           echo 'Not authenticated';
       }

       $models = ripcord::client("$url/xmlrpc/2/object");

       /***** PURCHASE ORDER ******/

        $purchaseOrderData = [
            'partner_id' => $partnerId, // ID of the supplier or partner
            'date_order' => $orderDate, // Date of the purchase order (YYYY-MM-DD format)
            'order_line' => [
                [0, false, [
                    'product_id' => 19, // ID of the product
                    'name' => 'Product A', // Name of the product
                    'product_qty' => 5, // Quantity
                    'price_unit' => 100, // Unit price
                ]],
                [0, false, [
                    'product_id' => 16,
                    'name' => 'Product B',
                    'product_qty' => 3,
                    'price_unit' => 150,
                ]],
            ],
            'state' => 'draft', // Status of the purchase order
            'partner_ref' => '', // Supplier reference for the purchase order
            'currency_id' => 2, // Currency used in the purchase order
            'is_shipped' => false, // Whether the order has been shipped
            'id' => false, // ID of the purchase order (auto-generated when saved)
            'company_id' => 1, // Company associated with the purchase order
            'date_approve' => false, // Date when the purchase order was approved
            'mail_reception_confirmed' => false, // Confirmation of email reception
            'date_planned' => false, // Planned date for the purchase order
            'mail_reminder_confirmed' => false, // Confirmation of reminder email
            'on_time_rate' => false, // On-time rate for the purchase order
            'receipt_reminder_email' => false, // Email address for receipt reminder
            'reminder_date_before_receipt' => false, // Reminder date before receipt
            'effective_date' => false, // Effective date of the purchase order
            'tax_country_id' => false, // Country ID for tax purposes
        ];
        
        
        $newPurchaseOrderId = $models->execute_kw($db, $uid, $password, 'purchase.order', 'create', [$purchaseOrderData]);
        
        $response = [
            'status' => 'success',
            'id_created' => $newPurchaseOrderId,
        ];
        echo json_encode($response, JSON_PRETTY_PRINT);

    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'bill') {
        //posted fields
        $postData = file_get_contents('php://input');
        $billInfo = json_decode($postData, true);

        //posted fields
        $partnerId = $billInfo['customer_id'];
        $invoiceDate = $billInfo['bill_date'];
        $accountId = $billInfo['accountId'] ?? 26;
        $invoiceNumber = $billInfo['reference'];
        $paymentTerm = $billInfo['payment_term'];
        $currency = $billInfo['currency'];
        $invoiceLines = $billInfo['invoice_lines'];

        include('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');
        $common = ripcord::client("$url/xmlrpc/2/common");
       $uid = $common->authenticate($db, $username, $password, array());
       

       if ($uid) {
           echo 'Autheniticated';
       } else {
           echo 'Not authenticated';
       }

       $models = ripcord::client("$url/xmlrpc/2/object");

        //create invoice with associated partner_id/customer
        $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [['move_type' => 'in_invoice', 'partner_id' => $partnerId]]); 
        $response = [ 'status' => 'success', 'id_created' => $billId ];
        echo json_encode($response);

       /***** BILL ******/
       if (is_int($billId)) {

        // Fetch currency id based on name (e.g., 'USD')
        $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
        $currencyId = $currencyId[0] ?? false;
        
        //invoice lines
        $invoiceLineIds = [];
        foreach ($invoiceLines as $line) {
            $invoiceLineIds[] = [0, false, [
                'product_id' => $line['product_id'],
                'name' => $line['name'] ?? false,
                'quantity' => $line['quantity'],
                'price_unit' => $line['price'] ?? false,
                'account_id' => $accountId,
            ]];
        }

        $billData = [
            // 'name' => 'BILL/2023/06/0022',
            //'partner_id' => $partnerId, // ID of the customer or partner
            'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format)
            'invoice_payment_term_id' => $paymentTerm,
            'currency_id' => $currencyId,
            'ref' => $invoiceNumber,
            'invoice_line_ids' => $invoiceLineIds,
            ];
    
            //update created bill with journal details
            $newBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$billId],$billData]);
    
            $response = [ 'status' => 'success', 'is_updated' => $newBillId ];
            echo json_encode($response);
            
            //POST drafted bill if information provided is valid
            if ($newBillId) {
                $postedBill = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$billId]);
    
                $response = [ 'status' => 'success', 'bill' => $postedBill ];
                echo json_encode($response);
            }
    
       }
      
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'product') {
         //posted fields
         $postData = file_get_contents('php://input');
         $productData = json_decode($postData, true);

        $productName = $productData['name'];
        $productCode = $productData['code'];
        $productType = $productData['type'];
        $productCat = $productData['category'];
        $productPrice = $productData['price'];
        $productSalesTax = $productData['sales_tax'];
        $productVendorTax = $productData['vendor_tax'];



        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        if ($uid) {
            echo 'Autheniticated';
        } else {
            echo 'Not authenticated';
        }

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");

        $productData = [
            'name' => $productName, // Name of the product
            'type' => $productType, // Type of the product (e.g., 'product', 'service')
            'list_price' => $productPrice, // Sales price of the product
            'default_code' => $productCode, // Unique code or reference for the product
            'categ_id' => $productCat, // Category of the product (optional) - default - All
            // 'company_id' => 1, // Company associated with the product (optional); Admin user only can apply associated ID
            'taxes_id' => [], // Tax applied to product
            'supplier_taxes_id' => [] //Vender tax applied to product
            
        ];
        
        $newProductId = $models->execute_kw($db, $uid, $password, 'product.product', 'create', [$productData]);
        
        $response = [ 'status' => 'success', 'id_created' => $newProductId];
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
   
    // Handle other endpoints similarly based on the HTTP method

    // Return a 404 response for unsupported endpoints
    http_response_code(404);