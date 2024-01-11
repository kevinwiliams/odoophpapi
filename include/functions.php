<?php

    // Function to generate maintenance code
    function generateCode($string, $endDigit) {
        // Split the input string into words
        $words = explode(' ', $string);
    
        // Initialize an empty prefix
        $prefix = '';
    
        // Extract the first two letters from each word and concatenate them
        foreach ($words as $word) {
            $prefix .= substr($word, 0, 2);
        }
    
        // Concatenate with the supplied end digit
        $code = strtoupper($prefix . str_pad($endDigit, 4, '0', STR_PAD_LEFT));
    
        return $code;
    }

    // Function to create a company
    function createCompany($companyData) {
        try {
   
           //posted fields
           $uuid = $companyData['uuid'] ?? false;
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
   
           //connect to odoo models
           $models = ripcord::client("$url/xmlrpc/2/object");
   
           // Fetch currency id based on name (e.g., 'USD')
           $currenyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
           $currenyId = $currenyId[0] ?? false;
   
           $countryId = $models->execute_kw($db, $uid, $password, 'res.country', 'search', [[['code', '=', $country]]]);
           $countryId = $countryId[0] ?? false;
         
           $companyData = [
               'x_uuid' => $uuid, // Name of the company
               'name' => $companyName, // Name of the company
               'email' => $companyEmail,
               'phone' => $companyPhone,
               'currency_id' => intval($currenyId), // ID of the currency used in the company
               'account_fiscal_country_id' => $countryId, // ID of the country where the company is located
   
           ];
           
           $newCompanyId = $models->execute_kw($db, $uid, $password, 'res.company', 'create', [$companyData]);
           $responseC = [ 'statusCode' => 200, 'company_id_created' => $newCompanyId];
   
           if (is_int($newCompanyId)) {
               // Get the current year
               $currentYear = date('Y');
   
               // Set the month and day to get the first day of January
               $firstMonth = '01';
               $firstDay = '01';
   
               // Create the date string in the format "YYYY-MM-DD"
               $lockDate = $currentYear . '-' . $firstMonth . '-' . $firstDay;
          
               $chartTemplateId = 1;
               // Configure fiscal period
               $settingsData = [
                   'company_id' => $newCompanyId,
                   'fiscalyear_last_day' => $fiscalLastDay, // Last day of the fiscal year
                   'fiscalyear_last_month' => "12", // Last month of the fiscal year
                   'fiscalyear_lock_date' => $lockDate, // Lock date for fiscal year
                   // Other fiscal period configuration...
               ];
               $configSettingsId = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'create', [$settingsData]);
               $response = [ 'statusCode' => 200, 'id_created' => $configSettingsId];
               // echo json_encode($response, JSON_PRETTY_PRINT);
   
               // Update the 'res.config.settings' record to apply fiscal localization
               $updateSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'write', [
                   [$configSettingsId],
                   [
                       'chart_template_id' => $chartTemplateId, // Replace with the ID of the desired chart template
                   ],
               ]);
               $response = [ 'statusCode' => 200, 'settings_updated' => $updateSettings];
               // echo json_encode($response, JSON_PRETTY_PRINT);
   
               // Update the company's chart template
               // echo ' compID: '.$newCompanyId;
               // echo ' chartID: '.$chartTemplateId;
               // $applyChartTemp = $models->execute_kw($db, $uid, $password, 'res.company', 'write', [[$newCompanyId], ['chart_template_id' => $chartTemplateId]]);
               // $response = ['statusCode' => 200, 'chart_template_applied' => $applyChartTemp];
               // echo json_encode($response, JSON_PRETTY_PRINT);
   
               // Apply fiscal localization and load chart of accounts
               $localizationApplied = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
               $response = ['statusCode' => 200, 'localization_applied' => $localizationApplied];
               // echo json_encode($response, JSON_PRETTY_PRINT);
               
               // $updatedSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
               // $response = [ 'statusCode' => 200, 'is_exe' => $updatedSettings];
               // echo json_encode($response, JSON_PRETTY_PRINT);
           }else{
            $response = ['statusCode' => 400, 'company_id_created' => $newCompanyId['faultString']];
            return $response;
          }

           return $responseC;

        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to create an invoice
    function createInvoice($invoiceInfo) {
        try {

            //posted fields
            $uuid = $invoiceInfo['uuid'] ?? false;
            $invoiceNumber = $invoiceInfo['name'] ?? false;
            $customer_uuid = $invoiceInfo['customer_id'];
            $company_uuid = $invoiceInfo['company_id'];
            $invoiceDate = $invoiceInfo['invoice_date'];
            $invoiceDateDue = $invoiceInfo['invoice_date_due'];
            $currency = $invoiceInfo['currency'] ?? 'JMD';
            $paymentTerm = $invoiceInfo['payment_term'] ?? false;
            $invoiceLines = $invoiceInfo['invoice_lines'];
                //connect to odoo
            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());

            //load odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");

            // Fetch company id based on VM UUID
            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customer_uuid]]]);
            $partnerId = $partnerId[0] ?? false;

            if (!is_int($partnerId)) {
                $response = [ 'statusCode' => 400, 'partner' => 'User not found' ];
                return $response;
            }
            //    echo 'PARTID:'.$partnerId;

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
            $companyId = $companyId[0] ?? false;

            if (!is_int($companyId)) {
                $response = [ 'statusCode' => 400, 'company' => 'Company not found' ];
                return $response;
            }
            //    echo 'COMP:'.($companyId);

            // Fetch account id based on VM UUID
            $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Product Sales'], ['company_id', '=', intval($companyId)]]]);
            $accountId = $accountId[0] ?? false;
            // echo 'ACID:'.($accountId);

            if (!is_int($accountId)) {
                $response = [ 'statusCode' => 400, 'account_id' => 'Product Sales not found. Check accounting package' ];
                return $response;
            }
            
            //create invoice with associated partner_id/customer
            $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [['move_type' => 'out_invoice', 'partner_id' => $partnerId, 'company_id' => intval($companyId)]]); 
            $response = [ 'statusCode' => 200, 'id_created' => $invoiceId ];
            // echo json_encode($response);

            //if ID is created 
            if (is_int($invoiceId)) {
                // Fetch currency id based on name (e.g., 'USD')
                $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
                $currencyId = $currencyId[0] ?? false;
                //invoice data
                $invoiceLineIds = [];
                foreach ($invoiceLines as $line) {
                    $tax = null;
                
                    // Fetch currency id based on name (e.g., 'USD')
                    $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['product_id']]]]);
                    $productId = $productId[0] ?? false;

                    if($line['tax']){
                        $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                        // echo('TAXID:'.$tax[0]);
                    }

                    $invoiceLineIds[] = [0, false, [
                        'product_id' => $productId,
                        'name' => $line['name'] ?? false,
                        'quantity' => $line['quantity'],
                        'price_unit' => $line['price']/100 ?? false,
                        'account_id' => $accountId,
                        'tax_ids' => (!empty($tax)) ? [$tax[0]] : []
                    ]];
                }

                $invoiceData = [
                    'x_uuid' => $uuid,
                    'name' => $invoiceNumber,
                    'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                    'invoice_date_due' =>  $invoiceDateDue,
                    'company_id' => intval($companyId), // Company associated with the invoice
                    'company_currency_id' => $currencyId,
                    'currency_id' => $currencyId, // Currency used in the invoice
                    'invoice_payment_term_id' => $paymentTerm, //payment terms
                    'invoice_line_ids' => $invoiceLineIds
                ];
                //update created invoice with journal details
                $newInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$invoiceId],$invoiceData]);
        
                $response = [ 'statusCode' => 200, 'is_updated' => $newInvoiceId ];
                // echo json_encode($response);
                
                //POST drafted invoice if information provided is valid
                if ($newInvoiceId) {
                    $postedInvoice = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$invoiceId]);

                    if($postedInvoice == false){
                        $response = [ 'statusCode' => 200, 'invoice' => 'posted' ];
                        return $response;
                    }else
                    {
                        $response = [ 'statusCode' => 400, 'invoice' => 'failed' ];
                        return $response;
                    }
                }
                else{
                    $response = ['statusCode' => 400, 'invoice' => $newInvoiceId['faultString']];
                    return $response;
                  }
            }
        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to update an invoice
    function updateInvoice($invoiceInfo) {
        try {

            //posted fields
            $uuid = $invoiceInfo['uuid'] ?? false;
            $invoiceNumber = $invoiceInfo['name'] ?? false;
            $customer_uuid = $invoiceInfo['customer_id'];
            $company_uuid = $invoiceInfo['company_id'];
            $invoiceDate = $invoiceInfo['invoice_date'];
            $invoiceDateDue = $invoiceInfo['invoice_date_due'];
            $currency = $invoiceInfo['currency'] ?? 'JMD';
            $paymentTerm = $invoiceInfo['payment_term'] ?? false;
            $invoiceLines = $invoiceInfo['invoice_lines'];
                //connect to odoo
            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());

            //load odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
            $companyId = $companyId[0] ?? false;
            //    echo 'COMP:'.($companyId);

            //search for invoice ID by uuid (search_read)
            $postedInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['x_uuid', '=', $uuid]]]); 
            // $postedInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['name', '=', $invoiceNumber], ['company_id', '=', $companyId] ]]); 
            //echo json_encode($postedInvoiceId);
            if (!is_int(($postedInvoiceId[0]))) {
                $response = [ 'statusCode' => 400, 'posted_invoice' => $postedInvoiceId ];
                return $response;
            }
             // Create a new draft invoice based on the posted invoice
            $draftInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'copy', [$postedInvoiceId], ['context' => ['default_move_type' => 'out_invoice']]);
            // echo json_encode($draftInvoiceId);

            //draft and remove old invoice
            $models->execute_kw($db, $uid, $password, 'account.move', 'button_draft', [[$postedInvoiceId[0]]]);
            $models->execute_kw($db, $uid, $password, 'account.move', 'unlink', [[$postedInvoiceId[0]]]);
            // echo json_encode($draftOldInvoice);

            $invoiceData = $models->execute_kw($db, $uid, $password, 'account.move', 'read', [$draftInvoiceId], ['fields' => ['invoice_line_ids']]);
            $line_ids = $invoiceData[0]['invoice_line_ids'];

            $old_line_ids = [];
            foreach ($line_ids as $line) {
                $old_line_ids[] = [2, $line, false]; 
            }

            // Remove the line item from the invoice.
            $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$draftInvoiceId], [ 'invoice_line_ids' => $old_line_ids]]);
            // echo json_encode($updatedInvoice);

            // Fetch company id based on VM UUID
            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customer_uuid]]]);
            $partnerId = $partnerId[0] ?? false;
            //    echo 'PARTID:'.$partnerId;

            // Fetch account id based on VM UUID
            $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Product Sales'], ['company_id', '=', intval($companyId)]]]);
            $accountId = $accountId[0] ?? false;
            // echo 'ACID:'.($accountId);
            
            // echo json_encode($response);

            // Fetch currency id based on name (e.g., 'USD')
            $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
            $currencyId = $currencyId[0] ?? false;
            //invoice data
            $invoiceLineIds = [];
            foreach ($invoiceLines as $line) {
                $tax = null;
            
                // Fetch currency id based on name (e.g., 'USD')
                $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['product_id']]]]);
                $productId = $productId[0] ?? false;

                if($line['tax']){
                    $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                    //echo('co: '.$companyId.' TAXID:'.json_encode($tax));
                }

                $invoiceLineIds[] = [0, false, [
                    'product_id' => $productId,
                    'name' => $line['name'] ?? false,
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price']/100 ?? false,
                    'account_id' => $accountId,
                    'tax_ids' => (!empty($tax)) ? [$tax[0]] : []
                ]];
            }
            $invoiceData = [
                'x_uuid' => $uuid,
                'name' => $invoiceNumber,
                'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                'invoice_date_due' =>  $invoiceDateDue,
                'company_id' => intval($companyId), // Company associated with the invoice
                'company_currency_id' => $currencyId,
                'currency_id' => $currencyId, // Currency used in the invoice
                'invoice_line_ids' => $invoiceLineIds
            ];
            //update created invoice with journal details
            $newInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$draftInvoiceId],$invoiceData]);
    
            $response = [ 'statusCode' => 200, 'is_updated' => $newInvoiceId ];
            // echo json_encode($response);
            
            //POST drafted invoice if information provided is valid
            if ($newInvoiceId) {
                $postedInvoice = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$draftInvoiceId]);
                if($postedInvoice == false){
                    $response = [ 'statusCode' => 200, 'invoice' => 'posted' ];
                    return $response;
                } else {
                    $response = [ 'statusCode' => 400, 'invoice' => 'fail' ];
                    return $response;
                }
                
            } else{
                $response = ['statusCode' => 400, 'invoice' => $newInvoiceId['faultString']];
                return $response;
              }
          
        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to update an invoice
    function updateExpense($billInfo) {
        try {

            //posted fields
            $uuid = $billInfo['bill_uuid'] ?? false;
            $invoiceNumber = $billInfo['name'] ?? false;
            $vendor_id = $billInfo['vendor_id'];
            $invoiceDate = $billInfo['bill_date'];
            $invoiceDateDue = $billInfo['invoice_date_due'];
            $currency = $billInfo['currency'] ?? 'JMD';
            $invoiceLines = $billInfo['invoice_lines'];
                //connect to odoo
            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());

            //load odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");

            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $vendor_id]]]);
            $partnerId = $partnerId[0] ?? false;

            $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
            $companyId = $partnerData[0]['company_id'][0];
            // $companyId = json_encode($companyId);

            //search for invoice ID by uuid (search_read)
            // $postedBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['ref', '=', $invoiceNumber], ['company_id', '=', $companyId]]]);
            $postedBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['x_uuid', '=', $uuid]]]);
            
            if(!is_int($postedBillId[0])){
                $response = [ 'statusCode' => 400, 'bill' => $postedBillId ];
                return $response;
            }
            // echo json_encode($postedBillId);

            // Create a new draft invoice based on the posted invoice
            $draftBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'copy', [$postedBillId], ['context' => ['default_move_type' => 'in_invoice']]);
            // echo json_encode($draftBillId);
            // exit;
            //draft and remove old invoice
            $models->execute_kw($db, $uid, $password, 'account.move', 'button_draft', [[$postedBillId[0]]]);
            $models->execute_kw($db, $uid, $password, 'account.move', 'unlink', [[$postedBillId[0]]]);
            // echo json_encode($draftOldInvoice);

            $invoiceData = $models->execute_kw($db, $uid, $password, 'account.move', 'read', [$draftBillId], ['fields' => ['invoice_line_ids']]);
            // echo json_encode($invoiceData);
            // exit;
            $line_ids = $invoiceData[0]['invoice_line_ids'];

            $old_line_ids = [];
            foreach ($line_ids as $line) {
                $old_line_ids[] = [2, $line, false]; 
            }

            // Remove the line item from the invoice.
            $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$draftBillId], [ 'invoice_line_ids' => $old_line_ids]]);
            // echo json_encode($updatedInvoice);
            
            
        
            $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Expenses'], ['company_id', '=', $companyId]]]);
            $accountId = $accountId[0] ?? false;

            // echo json_encode($response);

            // Fetch currency id based on name (e.g., 'USD')
            $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
            $currencyId = $currencyId[0] ?? false;
            //invoice data
            $invoiceLineIds = [];
            foreach ($invoiceLines as $line) {
                $tax = null;
            
                // Fetch currency id based on name (e.g., 'USD')
                $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['product_id']]]]);
                $productId = $productId[0] ?? false;

                if($line['tax']){
                    $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                    // echo('co: '.$companyId.' TAXID:'.json_encode($tax));
                }

                $invoiceLineIds[] = [0, false, [
                    'product_id' => $productId,
                    'name' => $line['name'] ?? false,
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price']/100 ?? false,
                    'account_id' => $accountId,
                    'tax_ids' => (!empty($tax)) ? [$tax[0]] : []
                ]];
            }
            $billData = [
                'x_uuid' => $uuid,
                'name' => $invoiceNumber,
                'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format)
                'invoice_date_due' => $invoiceDateDue,
                'currency_id' => $currencyId,
                'ref' => $invoiceNumber,
                'invoice_line_ids' => $invoiceLineIds,
            ];

            // echo json_encode($billData);
            //update created invoice with journal details
            $newBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$draftBillId],$billData]);
    
            $response = [ 'statusCode' => 200, 'is_updated' => $newBillId ];
            // echo json_encode($response);
            
            //POST drafted invoice if information provided is valid
            if ($newBillId) {
                $postedBill = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$draftBillId]);
    
                $response = [ 'statusCode' => 200, 'bill' => $postedBill ];
                return $response;
            }else{
                $response = ['statusCode' => 400, 'bill' => $newBillId['faultString']];
                return $response;
              }
          
        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to reverse an invoice
    function reverseInvoice($invoiceInfo){
        try {
            //posted fields
            $uuid = $invoiceInfo['inv_uuid'] ?? false;
            $company_uuid = $invoiceInfo['company_id'];
            $reverse_reason = $invoiceInfo['reason'];

            //connect to odoo
            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());

            //load odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");
            //get invoice ID
            $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['x_uuid', '=', $uuid]]]);
            
            if(!is_int($invoiceId[0])){
                $response = [ 'statusCode' => 400, 'invoice' => $invoiceId ];
                return $response;
            }

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
            $companyId = $companyId[0] ?? false;
            // Get journal ID 
            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', 'INV'], ['company_id', '=', intval($companyId)]]]);
            $journalId = json_encode($journalId[0]);

            $reverseData = [
                // 'date' => '2023-09-16', // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                'date_mode' =>  'custom',
                'company_id' => $companyId,
                'journal_id' => intval($journalId), // Company associated with the invoice
                'reason' => $reverse_reason,
                'refund_method' => 'cancel', // Currency used in the invoice
                'move_ids' => [[6, false, [$invoiceId[0]]]]
            ];

            //create reversal entry
            $reversalId = $models->execute_kw($db, $uid, $password, 'account.move.reversal', 'create', [$reverseData]);

            if (is_int($reversalId)){
                //reverse invoice
                $reversal = $models->execute_kw($db, $uid, $password, 'account.move.reversal', 'reverse_moves', [$reversalId]);
                $response = [ 'statusCode' => 200, 'is_reversed' => $reversal ];
                return $response;
            }else{
                $response = ['statusCode' => 400, 'is_reversed' => $reversalId['faultString']];
                return $response;
            }
            


        } catch (Exception $e) {
           // Output the error
           header('Content-Type: application/json', true, 500);
           echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to reverse an invoice
    function reverseExpense($billInfo){
        try {
            //posted fields
            $reference = $billInfo['reference'] ?? false;
            $uuid = $billInfo['bill_uuid'];
            $company_uuid = $billInfo['company_id'];
            $reverse_reason = $billInfo['reason'];

            //connect to odoo
            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());

            //load odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");
             // Fetch company id based on VM UUID
             $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
             $companyId = $companyId[0] ?? false;
             // echo json_encode($companyId);
            //get invoice ID
            // $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['ref', '=', $reference], ['company_id', '=', intval($companyId)]]]);
            $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['x_uuid', '=', $uuid]]]);

            if(!is_int($billId[0])){
                $response = [ 'statusCode' => 400, 'bill' => $billId ];
                return $response;
            }
            // echo json_encode($billId);
           
            // Get journal ID 
            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', 'BILL'], ['company_id', '=', intval($companyId)]]]);
            $journalId = json_encode($journalId[0]);
            // echo json_encode($journalId);

            $reverseData = [
                // 'date' => '2023-09-16', // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                'date_mode' =>  'custom',
                'company_id' => $companyId,
                'journal_id' => intval($journalId), // Company associated with the invoice
                'reason' => $reverse_reason,
                'refund_method' => 'cancel', // Currency used in the invoice
                'move_ids' => [[6, false, [$billId[0]]]]
            ];

            //create reversal entry
            $reversalId = $models->execute_kw($db, $uid, $password, 'account.move.reversal', 'create', [$reverseData]);
            // echo json_encode($reversalId);
            if (is_int($reversalId)){
                //reverse invoice
                $reversal = $models->execute_kw($db, $uid, $password, 'account.move.reversal', 'reverse_moves', [$reversalId]);
                $response = [ 'statusCode' => 200, 'is_reversed' => $reversal ];
                return $response;
            }else{
                $response = ['statusCode' => 400, 'is_reversed' => $reversalId['faultString']];
                return $response;
            }
            


        } catch (Exception $e) {
           // Output the error
           header('Content-Type: application/json', true, 500);
           echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to create a contact
    function createContact($contactData) {
         try {

            // Extract invoice fields from the JSON data
            $uuid = $contactData['uuid'];
            $name = $contactData['name'];
            $email = $contactData['email'];
            $phone = $contactData['phone'];
            $company = $contactData['company_uuid'];

            include_once('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');

            $common = ripcord::client("$url/xmlrpc/2/common");
            // authenicate user
            $uid = $common->authenticate($db, $username, $password, array());

            // connect to odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
            $companyId = $companyId[0] ?? false;

            $itemData = 
                    [[
                        'x_uuid' => $uuid,
                        'name' => $name,
                        'phone'  => $phone, //area code required
                        'email'  => $email,
                        'company_id' => $companyId,
                        // 'company_name' => 'My Company (San Francisco)',
                        'is_company' => false
                    ]];

            $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
            $response = [ 'statusCode' => 200, 'id_created' => $new_id];
            return $response;

        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }

    }

    // Function to create a vendor
    function createVendor($contactData) {
        try {

           // Extract invoice fields from the JSON data
           $uuid = $contactData['uuid'];
           $name = $contactData['name'];
           $email = $contactData['email'];
           $phone = $contactData['phone'];
           $company = $contactData['company_uuid'];

           include_once('include/connection/odoo_db.php');
           require_once('include/ripcord/ripcord.php');

           $common = ripcord::client("$url/xmlrpc/2/common");
           // authenicate user
           $uid = $common->authenticate($db, $username, $password, array());

           // connect to odoo models
           $models = ripcord::client("$url/xmlrpc/2/object");

           // Fetch company id based on VM UUID
           $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
           $companyId = $companyId[0] ?? false;

           $itemData = 
                   [[
                       'x_uuid' => $uuid,
                       'name' => $name,
                       'phone'  => $phone, //area code required
                       'email'  => $email,
                       'company_id' => $companyId,
                       'is_company' => true
                   ]];

           $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 

            //Create product with vendor details
            $productCode = generateCode($name, $companyId);
            $productData = [
                'x_uuid' => $uuid, // Name of the product
                'name' => $name, // Name of the product
                'type' => 'service', // Type of the product (e.g., 'product', 'service')
                'list_price' => 1, // Sales price of the product
                'default_code' => $productCode, // Unique code or reference for the product
                'categ_id' => 3, // Category of the product (optional) - default - All
                'company_id' => intval($companyId), // Company associated with the product (optional); Admin user only can apply associated ID
                'taxes_id' => [], // Tax applied to product
                'supplier_taxes_id' => [] //Vender tax applied to product
                
            ];
                
            $newProductId = $models->execute_kw($db, $uid, $password, 'product.product', 'create', [$productData]);
            
            $response = [ 'statusCode' => 200, 'product_id' => $newProductId, 'vendor_id' => $new_id];


           return $response;

       } catch (Exception $e) {
           // Output the error
           header('Content-Type: application/json', true, 500);
           echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
       }

   }

    // Function to update a contact
    function updateContact($contactData) {
        try {

           // Extract invoice fields from the JSON data
           $uuid = $contactData['uuid'];
           $name = $contactData['name'];
           $email = $contactData['email'];
           $phone = $contactData['phone'];
           $company = $contactData['company_uuid'];

           include_once('include/connection/odoo_db.php');
           require_once('include/ripcord/ripcord.php');

           $common = ripcord::client("$url/xmlrpc/2/common");
           // authenicate user
           $uid = $common->authenticate($db, $username, $password, array());

           // connect to odoo models
           $models = ripcord::client("$url/xmlrpc/2/object");

           // Fetch company id based on VM UUID
           $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
           $companyId = $companyId[0] ?? false;

           // Fetch partner data based on VM UUID
           $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $uuid]]]);
           $partnerId = $partnerId[0] ?? false;

           $itemData = 
                   [
                       'x_uuid' => $uuid,
                       'name' => $name,
                       'phone'  => $phone, //area code required
                       'email'  => $email,
                       'company_id' => $companyId,
                       'is_company' => false
                   ];

           $updated_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'write', [[$partnerId], $itemData]); 
           $response = [ 'statusCode' => 200, 'contact_updated' => $updated_id];
           return $response;

       } catch (Exception $e) {
           // Output the error
           header('Content-Type: application/json', true, 500);
           echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
       }

   }

    // Function to update a vendor
    function updateVendor($contactData) {
        try {

           // Extract invoice fields from the JSON data
           $uuid = $contactData['uuid'];
           $name = $contactData['name'];
           $email = $contactData['email'];
           $phone = $contactData['phone'];
           $company = $contactData['company_uuid'];

           include_once('include/connection/odoo_db.php');
           require_once('include/ripcord/ripcord.php');

           $common = ripcord::client("$url/xmlrpc/2/common");
           // authenicate user
           $uid = $common->authenticate($db, $username, $password, array());

           // connect to odoo models
           $models = ripcord::client("$url/xmlrpc/2/object");

           // Fetch company id based on VM UUID
           $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
           $companyId = $companyId[0] ?? false;

           // Fetch partner data based on VM UUID
           $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $uuid]]]);
           $partnerId = $partnerId[0] ?? false;

           $itemData = 
                   [
                       'x_uuid' => $uuid,
                       'name' => $name,
                       'phone'  => $phone, //area code required
                       'email'  => $email,
                       'company_id' => $companyId,
                       'is_company' => true
                   ];

           $updated_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'write', [[$partnerId], $itemData]); 
           $response = [ 'statusCode' => 200, 'vendor_updated' => $updated_id];
            //update vendor product
            $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $uuid]]]);
           $productId = $productId[0] ?? false;

           //Create product with vendor details
           $productCode = generateCode($name, $companyId);
           $productData = [
               'x_uuid' => $uuid, // Name of the product
               'name' => $name, // Name of the product
               'type' => 'service', // Type of the product (e.g., 'product', 'service')
               'list_price' => 1, // Sales price of the product
               'default_code' => $productCode, // Unique code or reference for the product
               'categ_id' => 3, // Category of the product (optional) - default - All
               'company_id' => intval($companyId), // Company associated with the product (optional); Admin user only can apply associated ID
               'taxes_id' => [], // Tax applied to product
               'supplier_taxes_id' => [] //Vender tax applied to product
               
           ];

           $updated_v_id = $models->execute_kw($db, $uid, $password, 'product.product', 'write', [[$productId], $productData]); 
           $response = [ 'statusCode' => 200, 'vendor_prod_updated' => $updated_v_id, 'vendor_updated' => $updated_id];


           return $response;

       } catch (Exception $e) {
           // Output the error
           header('Content-Type: application/json', true, 500);
           echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
       }

   }

    // Function to create an invoice payment
    function invoicePayment($paymentInfo) {
        try {

            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());
        
            $models = ripcord::client("$url/xmlrpc/2/object"); 
           
            //posted fields
            $customerId = $paymentInfo['customer_id'];
            $paymentDate = $paymentInfo['payment_date'];
            $amount = $paymentInfo['amount'];
            $paymentMethod = $paymentInfo['payment_method'];
            $journalId = $paymentInfo['journalId'] ?? 8;
            $invoiceNumber = $paymentInfo['invoice_num'];
            $invoice_uuid = $paymentInfo['invoice_uuid'] ?? false;
            
            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
            $partnerId = $partnerId[0] ?? false;

            if (!is_int($partnerId)) {
                $response = [ 'statusCode' => 400, 'partner' => 'User not found' ];
                return $response;
            }
            // echo (' Partner - '.$partnerId);
            $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
            $companyId = $partnerData[0]['company_id'][0];
            $companyId = json_encode($companyId);
            
            $getJournal = (intval($paymentMethod) == 1) ? 'CSH1' : 'BNK1';
            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', $getJournal], ['company_id', '=', intval($companyId)]]]);
            $journalId = json_encode($journalId[0]);
            // echo ('journalId '. $journalId );
            $paymentMethodId = $models->execute_kw($db, $uid, $password, 'account.payment.method.line', 'search', [[['payment_method_id', '=', intval($paymentMethod)], ['journal_id', '=', intval($journalId)]]]);
            $paymentMethodId = json_encode($paymentMethodId[0]);
              // Fetch invoice ID based on invoice number (e.g., 'INV/2023/00055')
            $invoiceNum = $invoiceNumber;
            // $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['payment_reference', '=', $invoiceNum], ['company_id', '=', intval($companyId)]]]);
            $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['x_uuid', '=', $invoice_uuid]]]);
            $invoiceId = $invoiceId[0] ?? false;

            if (!is_int($invoiceId)) {
                $response = ['statusCode' => 400, 'invoice' => $invoiceId,];
                return $response;
            }
             
              $context = [
                  'active_model' => 'account.move',
                  'active_id' => $invoiceId,
                  'active_ids' => [$invoiceId],
                  'allowed_company_ids' => [intval($companyId)]
              ];
              
              // Create the payment register record
              $paymentRegisterData = [
                  'company_id' => $companyId, // ID of the company associated
                  'partner_id' => $partnerId, // ID of the customer or partner
                  'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
                  'journal_id' => intval($journalId),
                  'payment_method_line_id' => $paymentMethodId,
                  'amount' => $amount/100, // Amount of the payment
                  'payment_type' => 'inbound',
                  'partner_type' => 'customer'
              ];

              $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
              
              // Create the payments based on the payment register
              if (is_int($paymentRegisterId)) {
                $regPayment = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [[$paymentRegisterId]]);
                $response = ['statusCode' => 200, 'payment_registered' => $regPayment,];
                return $response;

              } else {
                $response = ['statusCode' => 400, 'payment_created' => $paymentRegisterId['faultString']];
                return $response;
              }

        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to create an invoice payment
    function billPayment($paymentInfo) {
        try {

            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());
        
            $models = ripcord::client("$url/xmlrpc/2/object"); 
           
            //posted fields
            $uuid = $paymentInfo['bill_uuid'] ?? false;
            $customerId = $paymentInfo['vendor_id'];
            $paymentDate = $paymentInfo['payment_date'];
            $amount = $paymentInfo['amount'];
            $paymentMethod = $paymentInfo['payment_method'];
            $invoiceNumber = $paymentInfo['invoice_num'];
    
            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
            $partnerId = $partnerId[0] ?? false;
    
            $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
            $companyId = $partnerData[0]['company_id'][0];
            
            $getJournal = (intval($paymentMethod) == 1) ? 'CSH1' : 'BNK1';
            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', $getJournal], ['company_id', '=', intval($companyId)]]]);
            $journalId = json_encode($journalId[0]);

            $paymentMethodId = $models->execute_kw($db, $uid, $password, 'account.payment.method.line', 'search', [[['payment_method_id', '=', intval($paymentMethod)], ['journal_id', '=', intval($journalId)]]]);
            $paymentMethodId = json_encode($paymentMethodId[0]);
              // Fetch invoice ID based on invoice number (e.g., 'INV/2023/00055')
            //   $invoiceNum = $invoiceNumber;
            //   $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['ref', '=', $invoiceNum], ['move_type', '=', 'in_invoice'], ['company_id', '=', intval($companyId)]]], );
              $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['x_uuid', '=', $uuid]]], );
              $billId = $billId[0] ?? false;

              if (!is_int($billId)) {
                $response = ['statusCode' => 400, 'bill' => $billId,];
                return $response;
            }

              $context = [
                  'active_model' => 'account.move',
                  'active_ids' => [$billId],
              ];
              
              // Create the payment register record
              $paymentRegisterData = [
                  'x_uuid' => $uuid,
                  'company_id' => intval($companyId), // ID of the company associated
                  'partner_id' => $partnerId, // ID of the customer or partner
                  'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
                  'journal_id' => intval($journalId),
                  'payment_method_line_id' => $paymentMethodId,
                  'amount' => $amount/100, // Amount of the payment
              ];


              $models->execute_kw($db, $uid, $password, 'account.move', 'action_register_payment', [[$billId]]);
            //   echo json_encode($registerPayment);

              $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
              
              // Create the payments based on the payment register
              if (is_int($paymentRegisterId)) {
                  $regPayment = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
                  $response = ['statusCode' => 200, 'payment_registered' => $regPayment];
                  
                return $response;
              }
              else{
                $response = ['statusCode' => 400, 'payment_failed' => $paymentRegisterId['faultString']];
                return $response;
              }
        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to create a bill
    function createBill($billInfo) {
        try {
            
            //posted fields

            $uuid = $billInfo['bill_uuid'] ?? false;
            $customerId = $billInfo['vendor_id'];
            $invoiceDate = $billInfo['bill_date'];
            $invoiceDateDue = $billInfo['invoice_date_due'];
            $accountId = $billInfo['accountId'] ?? 26;
            $invoiceNumber = $billInfo['reference'];
            $currency = $billInfo['currency'];
            $invoiceLines = $billInfo['invoice_lines'];


            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());

            $models = ripcord::client("$url/xmlrpc/2/object");

            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
            $partnerId = $partnerId[0] ?? false;

            if (!is_int($partnerId)) {
                $response = [ 'statusCode' => 400, 'partner' => 'User not found' ];
                return $response;
            }

            $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
            $companyId = $partnerData[0]['company_id'][0];
            // $companyId = json_encode($companyId);
        
            $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Expenses'], ['company_id', '=', $companyId]]]);
            $accountId = $accountId[0] ?? false;

            if (!is_int($accountId)) {
                $response = [ 'statusCode' => 400, 'account_id' => 'Expenses not found. Check accounting package' ];
                return $response;
            }

            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', 'BILL'], ['company_id', '=', intval($companyId)]]]);
            $journalId = $journalId[0] ?? false;

            if (!is_int($journalId)) {
                $response = [ 'statusCode' => 400, 'journal_id' => 'Bill code not found. Check accounting package' ];
                return $response;
            }
     
            //create invoice with associated partner_id/customer
            $billData = [
                'move_type' => 'in_invoice', // Type of the invoice (in_invoice for vendor invoice)
                'partner_id' => $partnerId, // ID of the vendor or partner
                'journal_id' => intval($journalId), // ID of the company for which the invoice is created
            ];
    
            // Create the bill
            $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [$billData]); 
            $response = [ 'statusCode' => 200, 'id_created' => $billId ];
            // echo json_encode($response);

            /***** BILL ******/
            if (is_int($billId)) {

                // Fetch currency id based on name (e.g., 'USD')
                $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
                $currencyId = $currencyId[0] ?? false;

                $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Expenses'], ['company_id', '=', intval($companyId)]]]);
                $accountId = $accountId[0] ?? false;
                
                //invoice lines
                $invoiceLineIds = [];
                foreach ($invoiceLines as $line) {
                    $tax = [];
                    if($line['tax']){
                        $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                        // echo('TAXID:'.$tax[0]);
                    }
                    // Fetch currency id based on name (e.g., 'USD')
                    $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['product_id']]]]);
                    $productId = $productId[0] ?? false;

                    $invoiceLineIds[] = [0, false, [
                        'product_id' => $productId,
                        'name' => $line['name'] ?? false,
                        'quantity' => $line['quantity'],
                        'price_unit' => $line['price']/100 ?? false,
                        'account_id' => $accountId,
                        'tax_ids' => (!empty($tax)) ? [$tax[0]] : []
                    ]];
                }
                
                $billData = [
                    'x_uuid' => $uuid,
                    'name' => $invoiceNumber,
                    'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format)
                    'invoice_date_due' => $invoiceDateDue,
                    'currency_id' => $currencyId,
                    'ref' => $invoiceNumber,
                    'invoice_line_ids' => $invoiceLineIds,
                ];

                //update created bill with journal details
                $newBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$billId],$billData]);

                $response = [ 'statusCode' => 200, 'is_updated' => $newBillId ];
                // echo json_encode($response);
                
                //POST drafted bill if information provided is valid
                if ($newBillId) {
                    $postedBill = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$billId]);

                    $response = [ 'statusCode' => 200, 'bill' => $postedBill ];
                    return $response;
                }else{
                    $response = ['statusCode' => 400, 'bill' => $newBillId['faultString']];
                    return $response;
                    }
            
            }else{
                $response = ['statusCode' => 400, 'bill' => $billId['faultString']];
                return $response;
            }

        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to create a product
    function createProduct($productData) {
        try {
            
            //posted fields
            $productName = $productData['name'];
            $productCode = $productData['code'];
            $productType = $productData['type'];
            $productCat = $productData['category'];
            $productPrice = $productData['price'];
            $productSalesTax = $productData['sales_tax'];
            $productVendorTax = $productData['vendor_tax'];
            $company_uuid = $productData['company_id'];
            $product_uuid = $productData['uuid'];

            include_once('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');

            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());
            
            //connect to odoo models
            $models = ripcord::client("$url/xmlrpc/2/object");

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
            $companyId = $companyId[0] ?? false;

            if (!is_int($companyId)) {
                $response = [ 'statusCode' => 400, 'company' => 'Company not found'];
                return $response;
            }

            $productData = [
                'x_uuid' => $product_uuid, // Name of the product
                'name' => $productName, // Name of the product
                'type' => $productType, // Type of the product (e.g., 'product', 'service')
                'list_price' => $productPrice / 100, // Sales price of the product
                'default_code' => $productCode, // Unique code or reference for the product
                'categ_id' => $productCat, // Category of the product (optional) - default - All
                'company_id' => intval($companyId), // Company associated with the product (optional); Admin user only can apply associated ID
                'taxes_id' => [], // Tax applied to product
                'supplier_taxes_id' => [] //Vender tax applied to product
                
            ];
                
            $newProductId = $models->execute_kw($db, $uid, $password, 'product.product', 'create', [$productData]);

            if (is_int($newProductId)) {
                $response = [ 'statusCode' => 200, 'product_id_created' => $newProductId];
                return $response;
            }else{
                $response = ['statusCode' => 400, 'product_id_created' => $newProductId['faultString']];
                return $response;
            }
            

        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }
///////////////////     ///////////////      //////////////////////      /////////////////////       ////////////////

    // Function to create an invoice and its lines
    function createInvoices($models, $uuid, $companyId, $partnerId, $invoiceNumber, $invoiceDate, $invoiceDateDue, $invoiceLines, $connection)
    {
        $db = $connection['db'];
        $uid = $connection['uid'];
        $password = $connection['password'];

        $invoiceData = [
            'move_type' => 'out_invoice', // Type of the invoice (in_invoice for vendor invoice)
            'partner_id' => $partnerId, // ID of the vendor or partner
            'company_id' => intval($companyId), // ID of the company for which the invoice is created
        ];

        // Create the invoice
        $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [$invoiceData]);

        // Fetch account id based on VM UUID
        $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Product Sales'], ['company_id', '=', intval($companyId)]]]);
        $accountId = $accountId[0] ?? false;
        // echo 'ACID:'.($accountId);

        // Create the invoice lines
        foreach ($invoiceLines as $line) {

            $tax = [];
                
            // Fetch currency id based on name (e.g., 'USD')
            $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['item_uuid']]]]);
            $productId = $productId[0] ?? false;

            if($line['tax_rate'] > 0){
                $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                // echo('TAXID:'.$tax[0]);
            }

            $invoiceLineIds[] = [0, false, [
                        'product_id' => $productId,
                        // 'name' => $line['name'] ?? '',
                        'quantity' => $line['quantity'],
                        'price_unit' => $line['unit_cost']/100 ?? 0,
                        'account_id' => $accountId,
                        'tax_ids' => (!empty($tax)) ? [$tax[0]] : []
                    ]];
        }

        // Fetch currency id based on name (e.g., 'USD')
        $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', 'JMD']]]);
        $currencyId = $currencyId[0] ?? false;

        $invoiceData = [
                    'x_uuid' => $uuid,
                    'name' => $invoiceNumber,
                    'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                    'invoice_date_due' =>  $invoiceDateDue,
                    'company_id' => intval($companyId), // Company associated with the invoice
                    'company_currency_id' => $currencyId,
                    'currency_id' => $currencyId, // Currency used in the invoice
                    'invoice_line_ids' => $invoiceLineIds
                ];
        
                //echo json_encode($invoiceData, JSON_PRETTY_PRINT);
        
        // update created invoice with journal details
        $newInvoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$invoiceId],$invoiceData]);

        $response = [ 'statusCode' => 200, 'is_updated' => $newInvoiceId ];
        //echo json_encode($response);
        
        //POST drafted invoice if information provided is valid
        if ($newInvoiceId) {
            $postedInvoice = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$invoiceId]);
            $response = [ 'statusCode' => 200, 'invoice' => $postedInvoice ];
            return $response;
        }else{
            $response = ['statusCode' => 400, 'invoice' => $newInvoiceId['faultString']];
            return $response;
        }

        return $invoiceId;
    }

    // Function to create a bill and its lines
    function createExpenses($models, $uuid, $partnerId, $invoiceNumber, $invoiceDate, $invoiceDateDue, $invoiceLines, $connection)
    {
        $db = $connection['db'];
        $uid = $connection['uid'];
        $password = $connection['password'];

        $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
        $companyId = $partnerData[0]['company_id'][0];
        // $companyId = json_encode($companyId);
    
        $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Expenses'], ['company_id', '=', $companyId]]]);
        $accountId = $accountId[0] ?? false;


        $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', 'BILL'], ['company_id', '=', intval($companyId)]]]);
        $journalId = $journalId[0] ?? false;


        $billData = [
            'move_type' => 'in_invoice', // Type of the invoice (in_invoice for vendor invoice)
            'partner_id' => $partnerId, // ID of the vendor or partner
            'journal_id' => intval($journalId), // ID of the company for which the invoice is created
        ];

        // Create the bill
        $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [$billData]);

        // Create the invoice lines
        foreach ($invoiceLines as $line) {

            $tax = [];

            // Fetch currency id based on name (e.g., 'USD')
            $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['vendor_uuid']]]]);
            $productId = $productId[0] ?? false;

            if($line['tax_rate'] > 0){
                $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                // echo('TAXID:'.$tax[0]);
            }

            $invoiceLineIds[] = [0, false, [
                'product_id' => $productId,
                'name' => $line['description'] ?? false,
                'quantity' => $line['quantity'],
                'price_unit' => $line['price']/100 ?? false,
                'account_id' => $accountId,
                'tax_ids' => (!empty($tax)) ? [$tax[0]] : []

            ]];

        }

        // Fetch currency id based on name (e.g., 'USD')
        $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', 'JMD']]]);
        $currencyId = $currencyId[0] ?? false;

        
        $billData = [
            'x_uuid' => $uuid,
            'name' => $invoiceNumber,
            'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format)
            'invoice_date_due' =>  $invoiceDateDue,
            'currency_id' => $currencyId,
            'ref' => $invoiceNumber,
            'invoice_line_ids' => $invoiceLineIds,
        ];

        //update created bill with journal details
        $newBillId = $models->execute_kw($db, $uid, $password, 'account.move', 'write', [[$billId],$billData]);

        $response = [ 'statusCode' => 200, 'is_updated' => $newBillId ];
        // echo json_encode($response);
        
        //POST drafted bill if information provided is valid
        if ($newBillId) {
            $postedBill = $models->execute_kw($db, $uid, $password, 'account.move', 'action_post', [$billId]);

            $response = [ 'statusCode' => 200, 'bill' => $postedBill ];
            return $response;
        }else{
            $response = ['statusCode' => 400, 'bill' => $newBillId['faultString']];
            return $response;
          }

        return $billId;

    }

    // Function to load all companies to odoo
    function loadCompanies($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        
        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=companies", false, $context);

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
           
            // Process the data as needed
           foreach ($data as $item) {
                
                  //posted fields
                $companyName = $item['strata_name'];
                $companyEmail = $item['email_address'];
                $companyPhone = $item['contact_number'];
                // $companyAddress = $item['address_line_1'];
                $currency = $item['currency'];
                $country = $item['country'];
                $uuid = $item['uuid'];
                $fiscalLastDay = $item['fiscal_last_day'] ?? 31;


                // // Fetch currency id based on name (e.g., 'USD')
                $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
                $currencyId = $currencyId[0] ?? false;
                // echo ('curr: '.$currency. ' id: '.json_encode($currencyId));

                $countryId = $models->execute_kw($db, $uid, $password, 'res.country', 'search', [[['code', '=', $country]]]);
                $countryId = $countryId[0] ?? false;
              
                $companyData = [
                    'x_uuid' => $uuid, // Name of the company
                    'name' => $companyName, // Name of the company
                    'email' => $companyEmail,
                    'phone' => $companyPhone,
                    'currency_id' => intval($currencyId), // ID of the currency used in the company
                    'account_fiscal_country_id' => $countryId, // ID of the country where the company is located
                    // 'chart_template_id' => 1, // Link the company to an account chart template (fiscal localization)
                    // // Other company data...
                    //journal
                ];
                
                $newCompanyId = $models->execute_kw($db, $uid, $password, 'res.company', 'create', [$companyData]);
                $response = [ 'statusCode' => 200, 'id_created' => $newCompanyId];
                echo json_encode($response, JSON_PRETTY_PRINT);

                if (is_int($newCompanyId)) {
                    // Get the current year
                    $currentYear = date('Y');

                    // Set the month and day to get the first day of January
                    $firstMonth = '01';
                    $firstDay = '01';

                    // Create the date string in the format "YYYY-MM-DD"
                    $lockDate = $currentYear . '-' . $firstMonth . '-' . $firstDay;
            
                    $chartTemplateId = 1;
                    // Configure fiscal period
                    $settingsData = [
                        'company_id' => $newCompanyId,
                        // 'chart_template_id' => 1, // Link the company to an account chart template (fiscal localization)
                        'fiscalyear_last_day' => $fiscalLastDay, // Last day of the fiscal year
                        'fiscalyear_last_month' => "12", // Last month of the fiscal year
                        'fiscalyear_lock_date' => $lockDate, // Lock date for fiscal year
                        // Other fiscal period configuration...
                    ];
                    $configSettingsId = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'create', [$settingsData]);
                    $response = [ 'statusCode' => 200, 'id_created' => $configSettingsId];
                    echo json_encode($response, JSON_PRETTY_PRINT);

                    // Update the 'res.config.settings' record to apply fiscal localization
                    $updateSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'write', [
                        [$configSettingsId],
                        [
                            'chart_template_id' => $chartTemplateId, // Replace with the ID of the desired chart template
                        ],
                    ]);
                    $response = [ 'statusCode' => 200, 'settings_updated' => $updateSettings];
                    echo json_encode($response, JSON_PRETTY_PRINT);

                    // Apply fiscal localization and load chart of accounts
                    $localizationApplied = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
                    $response = ['statusCode' => 200, 'localization_applied' => $localizationApplied];
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    
                 
                }
            }

        } 
    }

    // Function to load all contacts to odoo
    function loadContacts($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=contacts", false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
            //posted fields
            $uuid = $item['uuid'];
            $name = $item['name'];
            $title = ucfirst($item['title']);
            $email = $item['email'];
            $phone = $item['phone'] ?? '';
            $company = $item['company_uuid'];

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
            $companyId = $companyId[0] ?? false;

            $itemData = 
                    [[
                        'x_uuid' => $uuid,
                        'name' => $name,
                        // 'title' => $title,
                        'phone'  => $phone, //area code required
                        'email'  => $email,
                        'company_id' => intval($companyId),
                        'is_company' => false
                    ]];
            
            // echo json_encode($itemData, JSON_PRETTY_PRINT);

            $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
            $response = [ 'statusCode' => 200, 'id_created' => $new_id];
            echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    // Function to load all units as contacts to odoo
    function loadUnits($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=units", false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
                //posted fields
                $uuid = $item['uuid'];
                $name = $item['name'];
                // $title = ucfirst($item['title']);
                $email = $item['email'];
                $street = $item['street'];
                $city = $item['city'];
                $phone = $item['phone'] ?? '';
                $company = $item['company_uuid'];

                // Fetch company id based on VM UUID
                $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
                $companyId = $companyId[0] ?? false;

                $itemData = 
                        [[
                            'x_uuid' => $uuid,
                            'name' => $name,
                            // 'title' => $title,
                            'phone'  => $phone, //area code required
                            'email'  => $email,
                            'street' => $street,
                            'city' => $city,
                            'company_id' => intval($companyId),
                            'is_company' => false
                        ]];
                
                // echo json_encode($itemData, JSON_PRETTY_PRINT);

                $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
                $response = [ 'statusCode' => 200, 'id_created' => $new_id];
                echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    // Function to load all existing invoices to odoo
    function loadInvoices($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=invoices", false, $context);

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $dataset = json_decode($response, true);

            // Group the dataset by invoice_id
            $groupedData = [];
            foreach ($dataset as $row) {
                $invoiceId = $row['inv_uuid'];
                if (!isset($groupedData[$invoiceId])) {

                    // Fetch company id based on VM UUID
                    $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $row['customer_id']]]]);
                    $partnerId = $partnerId[0] ?? false;
                    // echo 'PARTID:'.$partnerId;


                    // Fetch company id based on VM UUID
                    $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $row['company_id']]]]);
                    $companyId = $companyId[0] ?? false;
                    // echo 'COMP:'.($companyId);


                    $groupedData[$invoiceId] = [
                        'inv_uuid' => $invoiceId,
                        'companyId' => $companyId, 
                        'partnerId' => $partnerId, 
                        'invoiceDate' => $row['invoice_date'], 
                        'invoiceDateDue' => $row['due_date'], 
                        'invoiceNumber' => $row['invoice_number'], 
                        'lines' => [],
                    ];
                }
                $groupedData[$invoiceId]['lines'][] = $row;

            }
            // echo json_encode($groupedData, JSON_PRETTY_PRINT);

            // Loop through each invoice group and create invoices
            foreach ($groupedData as $invoiceId => $invoiceData) {
                // Extract companyId and partnerId for the current group
                $uuid = $invoiceData['inv_uuid'];
                $companyId = $invoiceData['companyId'];
                $partnerId = $invoiceData['partnerId'];
                $invoiceDate = $invoiceData['invoiceDate'];
                $invoiceDateDue = $invoiceData['invoiceDateDue'];
                $invoiceNumber = $invoiceData['invoiceNumber'];
                $invoiceLines = $invoiceData['lines'];

                // echo ('companyId: '.$companyId.' partnerId: '. $partnerId.' invoiceDate: '. $invoiceDate.' invoiceDateDue: '. $invoiceDateDue.' invoiceNumber: '. $invoiceNumber);
                // echo json_encode($invoiceLines, JSON_PRETTY_PRINT);

                $connection = [
                    'db' => $db,
                    'uid' => $uid,
                    'password' => $password
                ];

                // Create the invoice using the extracted companyId and partnerId
                $invoiceId = createInvoices($models, $uuid, $companyId, $partnerId, $invoiceNumber, $invoiceDate, $invoiceDateDue, $invoiceLines, $connection);
                echo "Invoice with ID: $invoiceId created." . PHP_EOL;
                
                // sleep(5);
            }

          
           

        } 
    }

    // Function to load all existing initial invoices to odoo
    function loadInitInvoices($apiUrl, $companyData) {

        //posted fields
        $uuid = $companyData['company_id'];

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=initnvoices&u=$uuid", false, $context);

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            echo $response;
            exit;
            // Decode the JSON response into an associative array
            $dataset = json_decode($response, true);

            // Group the dataset by invoice_id
            $groupedData = [];
            foreach ($dataset as $row) {
                $invoiceId = $row['inv_uuid'];
                if (!isset($groupedData[$invoiceId])) {

                    // Fetch company id based on VM UUID
                    $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $row['customer_id']]]]);
                    $partnerId = $partnerId[0] ?? false;
                    // echo 'PARTID:'.$partnerId;


                    // Fetch company id based on VM UUID
                    $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $row['company_id']]]]);
                    $companyId = $companyId[0] ?? false;
                    // echo 'COMP:'.($companyId);


                    $groupedData[$invoiceId] = [
                        'inv_uuid' => $invoiceId,
                        'companyId' => $companyId, 
                        'partnerId' => $partnerId, 
                        'invoiceDate' => $row['invoice_date'], 
                        'invoiceDateDue' => $row['due_date'], 
                        'invoiceNumber' => $row['invoice_number'], 
                        'lines' => [],
                    ];
                }
                $groupedData[$invoiceId]['lines'][] = $row;

            }
            // echo json_encode($groupedData, JSON_PRETTY_PRINT);

            // Loop through each invoice group and create invoices
            foreach ($groupedData as $invoiceId => $invoiceData) {
                // Extract companyId and partnerId for the current group
                $uuid = $invoiceData['inv_uuid'];
                $companyId = $invoiceData['companyId'];
                $partnerId = $invoiceData['partnerId'];
                $invoiceDate = $invoiceData['invoiceDate'];
                $invoiceDateDue = $invoiceData['invoiceDateDue'];
                $invoiceNumber = $invoiceData['invoiceNumber'];
                $invoiceLines = $invoiceData['lines'];

                // echo ('companyId: '.$companyId.' partnerId: '. $partnerId.' invoiceDate: '. $invoiceDate.' invoiceDateDue: '. $invoiceDateDue.' invoiceNumber: '. $invoiceNumber);
                // echo json_encode($invoiceLines, JSON_PRETTY_PRINT);

                $connection = [
                    'db' => $db,
                    'uid' => $uid,
                    'password' => $password
                ];

                // Create the invoice using the extracted companyId and partnerId
                $invoiceId = createInvoices($models, $uuid, $companyId, $partnerId, $invoiceNumber, $invoiceDate, $invoiceDateDue, $invoiceLines, $connection);
                //echo "Invoice with ID: $invoiceId created." . PHP_EOL;
                $response = ['loading' => 200];
                echo json_encode($response);
            }

          
           

        } 
    }

    // Function to load all existing products to odoo
    function loadProducts($apiUrl) {
        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=products", false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
                //posted fields
                $productName = $item['name'];
                $productType = $item['type'];
                $productCat = $item['category'];
                $productPrice = $item['unit_cost_in_cents'];
                $productSalesTax = $item['sales_tax'] ?? false;
                $productVendorTax = $item['vendor_tax'] ?? false;
                $company_uuid = $item['company_id'];
                $product_uuid = $item['uuid'];

                // Fetch company id based on VM UUID
                // Fetch company id based on VM UUID
                $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
                $companyId = $companyId[0] ?? false;

                $productCode = generateCode($productName, $companyId);

                $productData = [
                    'x_uuid' => $product_uuid, // Name of the product
                    'name' => $productName, // Name of the product
                    'type' => $productType, // Type of the product (e.g., 'product', 'service')
                    'list_price' => $productPrice / 100, // Sales price of the product
                    'default_code' => $productCode, // Unique code or reference for the product
                    'categ_id' => $productCat, // Category of the product (optional) - default - All
                    'company_id' => $companyId, // Company associated with the product (optional); Admin user only can apply associated ID
                    'taxes_id' => [], // Tax applied to product
                    'supplier_taxes_id' => [] //Vender tax applied to product
                    
                ];

                $newProductId = $models->execute_kw($db, $uid, $password, 'product.product', 'create', [$productData]);
        
                $response = [ 'statusCode' => 200, 'id_created' => $newProductId];
                echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    // Function to load all vendors to odoo
    function loadVendors($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=vendors", false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
            //posted fields
            $uuid = $item['uuid'];
            $name = $item['name'];
            $email = $item['email_address'];
            $phone = $item['contact_number'] ?? '';
            $company = $item['company_uuid'];

            // Fetch company id based on VM UUID
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
            $companyId = $companyId[0] ?? false;

            $itemData = 
                    [[
                        'x_uuid' => $uuid,
                        'name' => $name,
                        // 'title' => $title,
                        'phone'  => $phone, //area code required
                        'email'  => $email,
                        'company_id' => intval($companyId),
                        'is_company' => true
                    ]];
            
            // echo json_encode($itemData, JSON_PRETTY_PRINT);

            $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
            $response = [ 'statusCode' => 200, 'id_created' => $new_id];
            echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    // Function to load all payments to invoices
    function loadPayments($apiUrl){
        try {

            include('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
            $common = ripcord::client("$url/xmlrpc/2/common");
            $uid = $common->authenticate($db, $username, $password, array());
            // if ($uid)
            //     echo 'Autheniticated';
            // else
            //     echo 'Not authenticated';
            
        
            $models = ripcord::client("$url/xmlrpc/2/object"); 
            $arrContextOptions = array(
                "ssl" => array(
                "verify_peer" => false,
                "verify_peer_name" => false,
                )
            );
            $context = stream_context_create($arrContextOptions);
            $response = file_get_contents("$apiUrl?e=payments", false, $context);
            
            if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
                echo 'Error decoding JSON: ' . json_last_error_msg();
            } else {

                // Decode the JSON response into an associative array
                $data = json_decode($response, true);
            
                // Process the data as needed
                foreach ($data as $item) {
                    //posted fields
                    $customerId = $item['customer_id'];
                    $paymentDate = $item['payment_date'];
                    $amount = $item['amount'];
                    $paymentMethodId = $item['payment_method'];
                    $journalId = $item['journalId'] ?? 8;
                    $invoiceNumber = $item['invoice_num'];
            
                    $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
                    $partnerId = $partnerId[0] ?? false;
            
                    $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
                    $companyId = $partnerData[0]['company_id'][0];
                    $companyId = json_encode($companyId);
                    //   echo ($companyId);
                    
                    $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', 'BNK1'], ['company_id', '=', intval($companyId)]]]);
                    $journalId = json_encode($journalId[0]);
                
                    // Fetch invoice ID based on invoice number (e.g., 'INV/2023/00055')
                    $invoiceNum = $invoiceNumber;
                    $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['payment_reference', '=', $invoiceNum]]], );
                    $invoiceId = $invoiceId[0] ?? false;
                    
                    $context = [
                        'active_model' => 'account.move',
                        'active_ids' => $invoiceId,
                    ];
                    
                    // Create the payment register record
                    $paymentRegisterData = [
                        //   'company_id' => $companyId, // ID of the company associated
                        'partner_id' => $partnerId, // ID of the customer or partner
                        'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
                        'journal_id' => intval($journalId),
                        'payment_method_line_id' => $paymentMethodId,
                        'amount' => $amount/100, // Amount of the payment
                    ];
                    $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
                    $response = ['statusCode' => 200, 'payment_created' => $paymentRegisterId,];
                    //   echo json_encode($response);
                    
                    // Create the payments based on the payment register
                    if (is_int($paymentRegisterId)) {
                        $regPayment = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
                        $response = ['statusCode' => 200, 'payment_registered' => $regPayment,];
                        return $response;
                    }else{
                        $response = ['statusCode' => 400, 'payment_created' => $paymentRegisterId['faultString']];
                        return $response;
                    }
                }

            }
           
            
        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    // Function to load all expenses
    function loadExpenses($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=expenses", false, $context);

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $dataset = json_decode($response, true);

            // Group the dataset by invoice_id
            $groupedData = [];
            foreach ($dataset as $row) {
                $invoiceId = $row['inv_uuid'];
                if (!isset($groupedData[$invoiceId])) {

                    // Fetch company id based on VM UUID
                    $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $row['vendor_uuid']]]]);
                    $partnerId = $partnerId[0] ?? false;
                    echo 'PARTID:'.$partnerId;


                    $groupedData[$invoiceId] = [
                        'inv_uuid' => $invoiceId,
                        'partnerId' => $partnerId, 
                        'invoiceDate' => $row['expense_date'], 
                        'invoiceDateDue' => $row['due_date'], 
                        'invoiceNumber' => $row['expense_no'], 
                        'lines' => [],
                    ];
                }
                $groupedData[$invoiceId]['lines'][] = $row;

            }
            // echo json_encode($groupedData, JSON_PRETTY_PRINT);

            // Loop through each invoice group and create invoices
            foreach ($groupedData as $invoiceId => $invoiceData) {
                // Extract companyId and partnerId for the current group
                $uuid = $invoiceData['inv_uuid'];
                $partnerId = $invoiceData['partnerId'];
                $invoiceDate = $invoiceData['invoiceDate'];
                $invoiceDateDue = $invoiceData['invoiceDateDue'];
                $invoiceNumber = $invoiceData['invoiceNumber'];
                $invoiceLines = $invoiceData['lines'];

                // echo ('companyId: '.$companyId.' partnerId: '. $partnerId.' invoiceDate: '. $invoiceDate.' invoiceDateDue: '. $invoiceDateDue.' invoiceNumber: '. $invoiceNumber);
                // echo json_encode($invoiceLines, JSON_PRETTY_PRINT);

                $connection = [
                    'db' => $db,
                    'uid' => $uid,
                    'password' => $password
                ];

                // Create the invoice using the extracted companyId and partnerId
                $invoiceId = createExpenses($models, $uuid, $partnerId, $invoiceNumber, $invoiceDate, $invoiceDateDue, $invoiceLines, $connection);
                echo "Bill Invoice with ID: $invoiceId created." . PHP_EOL;
                
                // sleep(5);
            }

          
           

        } 
    }

    // Function to load all existing products to odoo
    function loadVendorProducts($apiUrl) {
        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=vendorproducts", false, $context);

        // echo json_encode($response, JSON_PRETTY_PRINT);
        // exit;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
                //posted fields
                $productName = $item['name'];
                $productType = $item['product_type'];
                $productCat = $item['category'];
                $productPrice = $item['price'];
                $productSalesTax = $item['sales_tax'] ?? false;
                $productVendorTax = $item['vendor_tax'] ?? false;
                $company_uuid = $item['company_uuid'];
                $product_uuid = $item['uuid'];

                // Fetch company id based on VM UUID
                // Fetch company id based on VM UUID
                $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
                $companyId = $companyId[0] ?? false;

                $productCode = generateCode($productName, $companyId);

                $productData = [
                    'x_uuid' => $product_uuid, // Name of the product
                    'name' => $productName, // Name of the product
                    'type' => $productType, // Type of the product (e.g., 'product', 'service')
                    'list_price' => $productPrice / 100, // Sales price of the product
                    'default_code' => $productCode, // Unique code or reference for the product
                    'categ_id' => $productCat, // Category of the product (optional) - default - All
                    'company_id' => $companyId, // Company associated with the product (optional); Admin user only can apply associated ID
                    'taxes_id' => [], // Tax applied to product
                    'supplier_taxes_id' => [] //Vender tax applied to product
                    
                ];

                $newProductId = $models->execute_kw($db, $uid, $password, 'product.product', 'create', [$productData]);
        
                $response = [ 'statusCode' => 200, 'id_created' => $newProductId];
                echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    // Function to load all invoice payments to odoo
    function loadInvoicePayments($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=invoicepayments", false, $context);

        // echo $response;
        // exit;
        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
            //posted fields
            $customerId = $item['unit_uuid'];
            $paymentDate = $item['date_paid'];
            $amount = $item['amount'];
            $paymentMethodId = $item['payment_method_id'] ?? false;
            $invoiceNumber = $item['invoice_number'];
            $ref = $item['reference_number'];
            $inv_uuid = $item['inv_uuid'];
    
            $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
            $partnerId = $partnerId[0] ?? false;
    
            $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
            $companyId = $partnerData[0]['company_id'][0];
            $companyId = json_encode($companyId);
            //   echo ($companyId);
            
            $getJournal = (intval($paymentMethodId) == 1) ? 'CSH1' : 'BNK1';
            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', $getJournal], ['company_id', '=', intval($companyId)]]]);
            $journalId = json_encode($journalId[0]);
        
              // Fetch invoice ID based on invoice number (e.g., 'INV/2023/00055')
              $invoiceNum = $invoiceNumber;
              $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['name', '=', $invoiceNum], ['company_id', '=', intval($companyId)]]], );
              $invoiceId = $invoiceId[0] ?? false;
              
            //   echo ('inoviceId '. $invoiceId. 'CompanyId '. $companyId);
            //   exit;
             
              $context = [
                  'active_model' => 'account.move',
                  'active_ids' => $invoiceId,
              ];
              
              // Create the payment register record
              $paymentRegisterData = [
                  'communication' => $ref,
                  'partner_id' => $partnerId, // ID of the customer or partner
                  'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
                  'journal_id' => intval($journalId),
                  'payment_method_line_id' => $paymentMethodId,
                  'amount' => $amount/100, // Amount of the payment
              ];
              $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
              $response = ['statusCode' => 200, 'payment_created' => $paymentRegisterId,];
              echo json_encode($response);
              
              // Create the payments based on the payment register
              if (is_int($paymentRegisterId)) {
                  $regPayment = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
                  $response = ['statusCode' => 200, 'payment_registered' => $regPayment,];
                  echo json_encode($response);
              }

            }

        } 
    }

    // Function to load all bill payments to odoo
    function loadExpensePayments($apiUrl) {

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        $listing = $common->version();
        // echo (json_encode($listing, JSON_PRETTY_PRINT));

        //authenicate user
        $uid = $common->authenticate($db, $username, $password, array());
        

        //connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");
        $arrContextOptions = array(
            "ssl" => array(
            "verify_peer" => false,
            "verify_peer_name" => false,
            )
        );
        $context = stream_context_create($arrContextOptions);
        $response = file_get_contents("$apiUrl?e=expensepayments", false, $context);

        // echo $response;
        // exit;
        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
            //posted fields
            $customerId = $item['unit_uuid'];
            $paymentDate = $item['date_paid'];
            $amount = $item['amount'];
            $paymentMethodId = $item['payment_method_id'] ?? false;
            $invoiceNumber = $item['invoice_number'];
            $ref = $item['reference_code'];
    
            $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $customerId]]]);
            $companyId = $companyId[0] ?? false;
            // echo json_encode($companyId);
            // exit;
            // $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$companyId], ['fields' => ['id']]);
            // // echo json_encode($partnerData);
            // // exit;
            // $partnerId = $partnerData[0]['id'];
            // $partnerId = json_encode($partnerId);
            //   echo ($partnerId);
            //   exit;
            
            $getJournal = (intval($paymentMethodId) == 1) ? 'CSH1' : 'BNK1';
            $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', $getJournal], ['company_id', '=', intval($companyId)]]]);
            $journalId = json_encode($journalId[0]);
        
              // Fetch invoice ID based on invoice number (e.g., 'INV/2023/00055')
              $invoiceNum = $invoiceNumber;
              $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'search', [[['name', '=', $invoiceNum], ['company_id', '=', intval($companyId)]]], );
              $invoiceId = $invoiceId[0] ?? false;
              
            //   echo ('inoviceId '. $invoiceId. 'CompanyId '. $companyId);
            //   exit;
             
              $context = [
                  'active_model' => 'account.move',
                  'active_ids' => $invoiceId,
              ];
              
              // Create the payment register record
              $paymentRegisterData = [
                //   'company_id' => $companyId, // ID of the company associated
                  'communication' => $ref,
                  'partner_id' => $companyId, // ID of the customer or partner
                  'payment_date' => $paymentDate, // Date of the payment (YYYY-MM-DD format)
                  'journal_id' => intval($journalId),
                  'payment_method_line_id' => $paymentMethodId,
                  'amount' => $amount/100, // Amount of the payment
              ];
              $paymentRegisterId = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'create', [$paymentRegisterData], ['context' => $context]);
              $response = ['statusCode' => 200, 'payment_created' => $paymentRegisterId,];
              echo json_encode($response);
              
              // Create the payments based on the payment register
              if (is_int($paymentRegisterId)) {
                  $regPayment = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
                  $response = ['statusCode' => 200, 'payment_registered' => $regPayment,];
                  echo json_encode($response);
              }

            }

        } 
    }

    // Function to create a payment method
    function createPaymentMethod($paymentMethodData) {
        try {

           // Extract invoice fields from the JSON data
           $uuid = $paymentMethodData['uuid'];
           $name = $paymentMethodData['name'];
           $code = $paymentMethodData['code'];
           $paymentType = $paymentMethodData['payment_type'];

           include_once('include/connection/odoo_db.php');
           require_once('include/ripcord/ripcord.php');

           $common = ripcord::client("$url/xmlrpc/2/common");
           // authenicate user
           $uid = $common->authenticate($db, $username, $password, array());

           // connect to odoo models
           $models = ripcord::client("$url/xmlrpc/2/object");

        //    // Fetch company id based on VM UUID
        //    $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
        //    $companyId = $companyId[0] ?? false;

           $paymentMethodData = [
            'x_uuid' => $uuid, // Name of the payment method
            'name' => $name, // Name of the payment method
            'code' => $code, // Code for the payment method
            'payment_type' => $paymentType, // Type of payment (inbound or outbound)
            'active' => true, // Whether the payment method is active
            'payment_icon' => false, // You can provide an icon for the payment method here
            ];
    
        // Create the payment method
        $newPaymentMethodId = $models->execute_kw($db, $uid, $password, 'account.payment.method', 'create', [$paymentMethodData]);
    
        $response = ['statusCode' => 200, 'id_created' => $newPaymentMethodId];
        return $response;

       } catch (Exception $e) {
           // Output the error
           header('Content-Type: application/json', true, 500);
           echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
       }

   }

   // Function to create a payment terms
   function createPaymentTerms($paymentTermsData) {
        try {

        // Extract invoice fields from the JSON data
        $uuid = $paymentTermsData['uuid'];
        $name = $paymentTermsData['name'];
        $code = $paymentTermsData['note'];
        $days = $paymentTermsData['days'];

        include_once('include/connection/odoo_db.php');
        require_once('include/ripcord/ripcord.php');

        $common = ripcord::client("$url/xmlrpc/2/common");
        // authenicate user
        $uid = $common->authenticate($db, $username, $password, array());

        // connect to odoo models
        $models = ripcord::client("$url/xmlrpc/2/object");

            $paymentTermsData = [
                'name' => 'Net 30 Days', // Name of the payment terms
                'active' => true, // Whether the payment terms are active
                'note' => 'Payment due within 30 days', // Additional notes for the payment terms
                'line_ids' => [
                    // Numeric value to represent the days
                    ['days' => 30, 'value' => 'balance']
                ]
            ];

            // Create the payment terms
            $newPaymentTermsId = $models->execute_kw($db, $uid, $password, 'account.payment.term', 'create', [$paymentTermsData]);

            $response = ['statusCode' => 200, 'id_created' => $newPaymentTermsId];
            return $response;

    } catch (Exception $e) {
        // Output the error
        header('Content-Type: application/json', true, 500);
        echo json_encode(['statusCode' => 500, 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
   }

}