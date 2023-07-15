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
        $query = "SELECT * FROM users";
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
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'contact') {
        //posted fields
        $name = $_POST['name'];
        $email = $_POST['email'];
        $phone = $_POST['phone'];

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
         $partnerAccountId = $_POST['partnerAccountId'];
         $paymentMethodId = $_POST['paymentMethodId'];
         $journalId = $_POST['journalId'];
         $invoiceNumber = $_POST['invoiceNumber'];
         
        // create payment with associated partner_id/customer
        // $paymentId = $models->execute_kw($db, $uid, $password, 'account.payment', 'create', [['payment_type' => 'inbound', 'partner_id' => $partnerId]]); 
        // $response = [ 'status' => 'success', 'id_created' => $paymentId,];
        // echo json_encode($response);
        
        //Add payment without attaching to an invoice
        // $paymentData = [
        //     'partner_id' => $partnerId, // ID of the customer or partner
        //     'date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
        //     // 'journal_id' => $journalId, // ID of the journal for the payment
        //     // 'payment_type' => 'inbound', // Type of the payment (e.g., 'inbound' for customer payment)
        //     'payment_method_id' => $paymentMethodId, // ID of the payment method
        //     'ref' => $invoiceNumber, // Payment communication/reference
        //     // 'communication' => $invoiceNumber,
        //     'amount' => 1200.00, // Amount of the payment
        //     'currency_id' => 2, // ID of the currency used for the payment
        //     // 'company_currency_id' => 2,
        //     // 'partner_type' => 'customer', // Type of the partner (e.g., 'customer' or 'supplier')
        //     'state' => 'posted'
        // ];

         // Fetch invoice ID based on invoice number (e.g., 'USD')
        $invoiceNum = $invoiceNumber;
        $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['payment_reference', '=', $invoiceNum]]]);
        $invoiceId = $invoiceId[0] ?? false;
        echo ('inoviceId '. $invoiceId );
        // exit;
       


        // $newPaymentId = $models->execute_kw($db, $uid, $password, 'account.payment', 'write', [[$paymentId],$paymentData]);
        // $response = ['status' => 'success', 'is_updated' => $newPaymentId,];
        // echo json_encode($response);

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
            'amount' => 1200.00, // Amount of the payment
        ];
        $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
        $response = ['status' => 'success', 'payment_created' => $paymentRegisterId,];
        echo json_encode($response);
        
        // Create the payments based on the payment register
        if (is_int($paymentRegisterId)) {
            $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
        }
        


        // $payment = $models->execute_kw($db, $uid, $password, 'account.payment', 'search_read', [[['id', '=', $paymentId]], ['amount']]);
        // echo json_encode($payment);

        // if ($payment) {
        //     $paymentAmount = $payment[0]['amount'];
        //     $reconciliationData = [
        //         [
        //             'payment_id' => $newPaymentId,
        //             'payment_amount' => $paymentAmount,
        //             'invoice_id' => $invoiceId,
        //         ],
        //     ];

        //     $recon = $models->execute_kw($db, $uid, $password, 'account.payment', 'process_reconciliations', [$reconciliationData]);
        //     $response = ['status' => 'success', 'is_recon' => $recon,];
        //     echo json_encode($response);
        // }

        // $invoiceUpdate = $models->execute_kw($db, $uid, $password, 'account.move', 'action_register_payment', [[$invoiceId], ['payment_id' => $paymentId]]);
        // $response = ['status' => 'success', 'payment_registered' => $invoiceUpdate,];
        // echo json_encode($response);

        // $paymentData = [
        //     'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
        //     'journal_id' => $journalId, // ID of the journal for the payment
        //     'amount' => 1200.00, // Amount of the payment
        //     'payment_method_id' => $paymentMethodId, // ID of the payment method
        //     'communication' => $invoiceNumber,
        //     'active_ids' => [$invoiceId]
        // ];

        // $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.move', 'action_register_payment', [[$paymentData]]);
        // $response = ['status' => 'success', 'payment_registered' => $paymentRegisterId,];
        // echo json_encode($response);

        // $result = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [[$paymentRegisterId]]);
        // $result = $models->execute_kw($db, $uid, $password, 'account.move', 'action_register_payment', [$invoiceId], ['payment_data' => $paymentData]);

        // // $result = $models->execute_kw($db, $uid, $password, 'account.move', 'action_register_payment', [$paymentData]);
        // $response = ['status' => 'success', 'is_updated' => $result,];
        // echo json_encode($response);

       
    }
         //posted fields
         $partnerId = $_POST['name'];
         $invoiceDate = $_POST['email'];
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

        /***** INVOICE ******/
        $invoiceData = [
            'partner_id' => $partnerId, // ID of the customer or partner
            'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format)
            'invoice_line_ids' => [
                [0, false, [
                    'name' => 'Product A', // Name of the product or service
                    'quantity' => 2, // Quantity
                    'price_unit' => 100, // Unit price
                    'account_id' => $accountId, // ID of the account to be used for this line
                ]],
                [0, false, [
                    'name' => 'Product B',
                    'quantity' => 1,
                    'price_unit' => 150,
                    'account_id' => $accountId,
                ]],
            ],
        ];

        $newInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [$invoiceData]);
        
        $response = [
            'status' => 'success',
            'id_created' => $newInvoiceId,
        ];

        echo json_encode($response, JSON_PRETTY_PRINT);
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
        $partnerId = $_POST['name'];
        $invoiceDate = $_POST['email'];
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

       /***** BILL ******/
       $billData = [
        'name' => 'BILL/2023/06/0021-1',
        'partner_id' => $partnerId, // ID of the customer or partner
        'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format)
        'journal_id' => 2, // Date of the invoice (YYYY-MM-DD format)
        'invoice_payment_term_id' => 7,
        'invoice_line_ids' => [
            [0, false, [
                'product_id' => 5,
                'name' => 'Product A', // Name of the product or service
                'quantity' => 2, // Quantity
                'price_unit' => 150, // Unit price
                'credit' => 0, // Unit price
                'debit' => 150, // Unit price
                'account_id' => $accountId, // ID of the account to be used for this line
            ]],
            [0, false, [
                'product_id' => 7,
                'name' => 'Product B',
                'quantity' => 1,
                'price_unit' => 150,
                'credit' => 0,
                'debit' => 150,
                'account_id' => $accountId,
            ]],
            [0, false, [
                // 'product_id' => 7,
                // 'name' => 'Product B',
                // 'quantity' => 1,
                // 'price_unit' => 150,
                'credit' => 300,
                'debit' => 0,
                'account_id' => 14, //Accounts Payable
            ]]
        ],
        'state' => 'draft',
        'currency_id' => 2,
        ];

        $newBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [$billData]);
        
        $response = [
            'status' => 'success',
            'id_created' => $newBillId,
        ];


       echo json_encode($response, JSON_PRETTY_PRINT);

    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'inv') {
        //posted fields
       $invoiceNumber = $_POST['invoiceNumber'];
       $partnerId = $_POST['partnerId'];
       $invoiceDate = $_POST['invoiceDate'];
       $invoiceDateDue = $_POST['invoiceDateDue'];
       $accountId = $_POST['accountId'];
       $journalId = $_POST['journalId'];
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
            //invoice data
            $invoiceData = [
                // 'name' => $invoiceNumber,
                'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                //   'invoice_date_due' =>  $invoiceDateDue,
                //    'journal_id' => $journalId, //journal_id is added by default
                'company_id' => 1, // Company associated with the invoice
                'company_currency_id' => 2,
                'currency_id' => 2, // Currency used in the invoice
                'invoice_payment_term_id' => 4, //payment terms
                'invoice_line_ids' => [
                    [0, false, [
                        'product_id' => 33, // ID of the product
                        //'name' => 'Product A', // Name of the product or service ::optional
                        'quantity' => 1, // Quantity
                        'price_unit' => 2350.00, // Unit price ::can be overwritten/optional
                        'account_id' => $accountId, // ID of the account to be used for this line
                        'tax_ids' => [1] // Tax ID to be applied to product
                    ]],
                    [0, false, [
                        'product_id' => 35, // ID of the product
                        'name' => 'Product B', // Name of the product or service ::optional
                        'quantity' => 3,
                    //    'price_unit' => 1500.00, // Unit price ::can be overwritten/optional
                        'account_id' => $accountId, // ID of the account to be used for this line
                        'tax_ids' => [1] // Tax ID to be applied to product
                    ]]
                ],
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
    // Handle other endpoints similarly based on the HTTP method

    // Return a 404 response for unsupported endpoints
    http_response_code(404);