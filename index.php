<?php

    include 'include/connection/mysql_db.php';

    function generateCode($string, $endDigit) {
        // Extract the first two characters of the string
        $prefix = substr($string, 0, 2);
    
        // Concatenate with the supplied end digit
        $code = strtoupper($prefix . str_pad($endDigit, 4, '0', STR_PAD_LEFT));
    
        return $code;
    }

    // API endpoint to retrieve data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        
        
        // Establish a connection to your MySQL database
        $connection = mysqli_connect($host, $username, $password, $database);

        if (!$connection) {
            echo "Failed to connect to the database.";
            exit;
        }
    
        // Query to fetch data from the database
        $queryContacts = "select u.uuid, concat(u.first_name, ' ', u.last_name) name, u.email, u.contact_number phone, rt.key title, ur.is_active, s.uuid company_uuid, ur.strata_id company_id, s.strata_name, s.email_address, a.* 
        from user_roles ur
        join users u on u.id = ur.user_id
        join role_types rt on rt.id = ur.role_type_id
        join stratas s on s.id = ur.strata_id
        join addresses a on a.id = s.address_id
        where ur.is_active = 1 and u.id > 2";

        $queryCompanies = "select s.uuid, s.id, s.strata_name, s.email_address, s.contact_number, 'JM' country, 'JMD' currency, a.* from stratas s
        join addresses a on a.id = s.address_id";

        $queryProducts = "select i.*, s.uuid company_id, 'service' type, '2' category from items i
                        join stratas s on s.id = i.strata_id where i.is_active = 1";

        $queryUnits = "select u.uuid, concat(u.short_description, ' / Lot ' ,u.lot_number) name, s.uuid company_uuid, a.address_line_1 street, p.name city, pr.user_id, us.email, us.contact_number phone from units u
        join proprietors pr on pr.unit_id = u.id and pr.is_proxy = 1
        join users us on pr.user_id = us.id
        join stratas s on u.strata_id = s.id
        join addresses a on u.address_id = a.id
        join parishes p on a.parish_id = p.id";

        $result = mysqli_query($connection, $queryUnits);
    
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


        $flds = ($data);

        
    
        // Close the database connection
        mysqli_close($connection);

        exit;
       
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'loadcompany') {
        

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
        $response = file_get_contents('https://odoophpapi.test/', false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            echo "inside";
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
            //echo ($response);

            // $responseArray = json_decode($data, true);

            // Loop through each element
            // foreach ($data as $element) {
            //     $uuid = $element['uuid'];
            //     $id = $element['id'];
            //     $strataName = $element['strata_name'];
            //     $emailAddress = $element['email_address'];
            //     // ... and so on for other fields
                
            //     // Output or process each element as needed
            //     echo "UUID: $uuid, ID: $id, Strata Name: $strataName, Email Address: $emailAddress\n";
            // }
            // //print_r($data);
            // exit;
            
            // Process the data as needed
           foreach ($data as $item) {
                
            // $uuid = $item['uuid'];
            // $name = $item['name'];
            // $title = $item['title'];
            // $email = $item['email'];
            // $phone = $item['phone'];
            // $company = $item['company_uuid'];

            // // Fetch company id based on VM UUID
            // $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company]]]);
            // $companyId = $companyId[0] ?? false;

            // $itemData = 
            //         [[
            //             'x_uuid' => $uuid,
            //             'name' => $name,
            //             'title' => $title,
            //             'phone'  => $phone, //area code required
            //             'email'  => $email,
            //             'company_id' => $companyId,
            //             // 'commercial_partner_id' => 52,
            //             // 'company_name' => 'My Company (San Francisco)',
            //             'is_company' => false
            //         ]];

            // $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
            // $response = [ 'status' => 'success', 'id_created' => $new_id];
            // echo json_encode($response, JSON_PRETTY_PRINT);

                // $itemData = 
                //     [
                //         'name' => $item['first_name'].' '. $item['last_name'],
                //         'phone'  => $item['contact_number'], //area code required
                //         'email'  => $item['email'],
                //         // 'function' => 'Developer'
                //     ];

                // $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', [$itemData]); 
                
                
                  //posted fields
                $companyName = $item['strata_name'];
                $companyEmail = $item['email_address'];
                $companyPhone = $item['contact_number'];
                $companyAddress = $item['address_line_1'];
                $currency = $item['currency'];
                $country = $item['country'];
                $uuid = $item['uuid'];
                $fiscalLastDay = $item['fiscal_last_day'] ?? 31;


                // // Fetch currency id based on name (e.g., 'USD')
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
                    // 'chart_template_id' => 1, // Link the company to an account chart template (fiscal localization)
                    // // Other company data...
                    //journal
                ];
                
                $newCompanyId = $models->execute_kw($db, $uid, $password, 'res.company', 'create', [$companyData]);
                $response = [ 'status' => 'success', 'id_created' => $newCompanyId];
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
                    $response = [ 'status' => 'success', 'id_created' => $configSettingsId];
                    echo json_encode($response, JSON_PRETTY_PRINT);

                    // Update the 'res.config.settings' record to apply fiscal localization
                    $updateSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'write', [
                        [$configSettingsId],
                        [
                            'chart_template_id' => $chartTemplateId, // Replace with the ID of the desired chart template
                        ],
                    ]);
                    $response = [ 'status' => 'success', 'settings_updated' => $updateSettings];
                    echo json_encode($response, JSON_PRETTY_PRINT);

                    // Apply fiscal localization and load chart of accounts
                    $localizationApplied = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
                    $response = ['status' => 'success', 'localization_applied' => $localizationApplied];
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    
                 
                }
            }

        } 
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'loadcontacts') {
        

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
        $response = file_get_contents('https://odoophpapi.test/', false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            echo "inside";
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
            $response = [ 'status' => 'success', 'id_created' => $new_id];
            echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'loadunits') {
        

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
        $response = file_get_contents('https://odoophpapi.test/', false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            echo "inside";
            // Decode the JSON response into an associative array
            $data = json_decode($response, true);
          
            // Process the data as needed
           foreach ($data as $item) {
            //posted fields
            $uuid = $item['uuid'];
            $name = $item['name'];
            $title = ucfirst($item['title']);
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
            $response = [ 'status' => 'success', 'id_created' => $new_id];
            echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'loadproducts') {
        

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
        $response = file_get_contents('https://odoophpapi.test/', false, $context);

        //echo $response;

        // Process the API response
        if ($response === null && json_last_error() !== JSON_ERROR_NONE) {
            echo 'Error decoding JSON: ' . json_last_error_msg();
        } else {
            echo "inside";
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
        
                $response = [ 'status' => 'success', 'id_created' => $newProductId];
                echo json_encode($response, JSON_PRETTY_PRINT);

            }

        } 
    }
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_REQUEST['e'] == 'contact') {
        //posted fields
        $postData = file_get_contents('php://input');
        $contactData = json_decode($postData, true);

        // Extract invoice fields from the JSON data
        $uuid = $contactData['uuid'];
        $name = $contactData['name'];
        $email = $contactData['email'];
        $phone = $contactData['phone'];
        $company = $contactData['company_uuid'];

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
        $uuid = $companyData['uuid'];
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

        // First, create the partner with the mailing address details
        $partnerData = [
            'name' => $companyName,
            'email' => $companyEmail,
            'phone' => $companyPhone,
            // Other partner data...
            'street' => 'address_line_1',
            'city' => 'city',
            'country_id' => $countryId, // ID of the country for the mailing address
            'is_company' => true
        ];

        $newPartnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', [$partnerData]);

        // Next, create the company and link it to the partner with the mailing address

      
        $companyData = [
            'x_uuid' => $uuid, // Name of the company
            'name' => $companyName, // Name of the company
            'email' => $companyEmail,
            'phone' => $companyPhone,
            'currency_id' => intval($currenyId), // ID of the currency used in the company
            'account_fiscal_country_id' => $countryId, // ID of the country where the company is located
            // 'chart_template_id' => 1, // Link the company to an account chart template (fiscal localization)
            // // Other company data...
            //journal
        ];
        
        $newCompanyId = $models->execute_kw($db, $uid, $password, 'res.company', 'create', [$companyData]);
        $response = [ 'status' => 'success', 'id_created' => $newCompanyId];
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
            $response = [ 'status' => 'success', 'id_created' => $configSettingsId];
            echo json_encode($response, JSON_PRETTY_PRINT);

            // Update the 'res.config.settings' record to apply fiscal localization
            $updateSettings = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'write', [
                [$configSettingsId],
                [
                    'chart_template_id' => $chartTemplateId, // Replace with the ID of the desired chart template
                ],
            ]);
            $response = [ 'status' => 'success', 'settings_updated' => $updateSettings];
            echo json_encode($response, JSON_PRETTY_PRINT);

            // Update the company's chart template
            // echo ' compID: '.$newCompanyId;
            // echo ' chartID: '.$chartTemplateId;
            // $applyChartTemp = $models->execute_kw($db, $uid, $password, 'res.company', 'write', [[$newCompanyId], ['chart_template_id' => $chartTemplateId]]);
            // $response = ['status' => 'success', 'chart_template_applied' => $applyChartTemp];
            // echo json_encode($response, JSON_PRETTY_PRINT);

            // Apply fiscal localization and load chart of accounts
            $localizationApplied = $models->execute_kw($db, $uid, $password, 'res.config.settings', 'execute', [$configSettingsId]);
            $response = ['status' => 'success', 'localization_applied' => $localizationApplied];
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
       $customer_uuid = $invoiceInfo['customer_id'];
       $company_uuid = $invoiceInfo['company_id'];
       $invoiceDate = $invoiceInfo['invoice_date'];
       $invoiceDateDue = $invoiceInfo['invoice_date_due'];
       $currency = $invoiceInfo['currency'];
       $paymentTerm = $invoiceInfo['payment_term'];
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

      

       // Fetch company id based on VM UUID
       $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customer_uuid]]]);
       $partnerId = $partnerId[0] ?? false;
       echo 'PARTID:'.$partnerId;


       // Fetch company id based on VM UUID
       $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
       $companyId = $companyId[0] ?? false;
       echo 'COMP:'.($companyId);


        // Fetch account id based on VM UUID
        $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Product Sales'], ['company_id', '=', intval($companyId)]]]);
        $accountId = $accountId[0] ?? false;
        echo 'ACID:'.($accountId);
        
        // exit;
       //create invoice with associated partner_id/customer
       $invoiceId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [['move_type' => 'out_invoice', 'partner_id' => $partnerId, 'company_id' => intval($companyId)]]); 
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
                $tax = null;
            
                // Fetch currency id based on name (e.g., 'USD')
                $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['product_id']]]]);
                $productId = $productId[0] ?? false;

                if($line['tax']){
                    $tax =  $models->execute_kw($db, $uid, $password, 'account.tax', 'search', [[['type_tax_use', '=', 'sale'], ['company_id', '=', intval($companyId)]]]);
                    echo('TAXID:'.$tax[0]);
                }

                $invoiceLineIds[] = [0, false, [
                    'product_id' => $productId,
                    'name' => $line['name'] ?? false,
                    'quantity' => $line['quantity'],
                    'price_unit' => $line['price']/100 ?? false,
                    'account_id' => $accountId,
                    'tax_ids' => [$tax[0]]
                ]];
            }

            $invoiceData = [
                'name' => $invoiceNumber,
                'invoice_date' => $invoiceDate, // Date of the invoice (YYYY-MM-DD format) //default to today's date if not set
                //   'invoice_date_due' =>  $invoiceDateDue,
                'company_id' => intval($companyId), // Company associated with the invoice
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
     $customerId = $paymentInfo['customer_id'];
     $paymentDate = $paymentInfo['payment_date'];
     $amount = $paymentInfo['amount'];
     $paymentMethodId = $paymentInfo['payment_method'];
     $journalId = $paymentInfo['journalId'] ?? 8;
     $invoiceNumber = $paymentInfo['invoice_num'];

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
      
      echo ('inoviceId '. $invoiceId );
      // exit;
     
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
      $response = ['status' => 'success', 'payment_created' => $paymentRegisterId,];
      echo json_encode($response);
      
      // Create the payments based on the payment register
      if (is_int($paymentRegisterId)) {
          $regPayment = $models->execute_kw($db, $uid, $password, 'account.payment.register', 'action_create_payments', [$paymentRegisterId]);
          $response = ['status' => 'success', 'payment_registered' => $regPayment,];
        echo json_encode($response);
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
         $customerId = $_POST['partnerId'];
         $paymentDate = $_POST['paymentDate'];
         $amount = $_POST['amount'];
         $paymentMethodId = $_POST['paymentMethodId'];
         $journalId = $_POST['journalId'];
         $invoiceNumber = $_POST['invoiceNumber'];

         $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
        $partnerId = $partnerId[0] ?? false;
         
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
        $customerId = $billInfo['customer_id'];
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

       $partnerId = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', [[['x_uuid', '=', $customerId]]]);
       $partnerId = $partnerId[0] ?? false;

       $partnerData = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$partnerId], ['fields' => ['company_id']]);
        $companyId = $partnerData[0]['company_id'][0];
        $companyId = json_encode($companyId);
       
        $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Expenses'], ['company_id', '=', $companyId]]]);
        $accountId = $accountId[0] ?? false;


        $journalId = $models->execute_kw($db, $uid, $password, 'account.journal', 'search', [[['code', '=', 'BILL'], ['company_id', '=', intval($companyId)]]]);
        $journalId = $journalId[0] ?? false;
        // echo ($journalId);
        // exit;
        //create invoice with associated partner_id/customer
        $billId = $models->execute_kw($db, $uid, $password, 'account.move', 'create', [['move_type' => 'in_invoice', 'partner_id' => $partnerId, 'journal_id' => $journalId]]); 
        $response = [ 'status' => 'success', 'id_created' => $billId ];
        echo json_encode($response);

       /***** BILL ******/
       if (is_int($billId)) {

        // Fetch currency id based on name (e.g., 'USD')
        $currencyId = $models->execute_kw($db, $uid, $password, 'res.currency', 'search', [[['name', '=', $currency]]]);
        $currencyId = $currencyId[0] ?? false;

        $accountId = $models->execute_kw($db, $uid, $password, 'account.account', 'search', [[['name', '=', 'Expenses'], ['company_id', '=', intval($companyId)]]]);
        $accountId = $accountId[0] ?? false;
        // echo $accountId;
        // exit;
        //invoice lines
        $invoiceLineIds = [];
        foreach ($invoiceLines as $line) {
             // Fetch currency id based on name (e.g., 'USD')
             $productId = $models->execute_kw($db, $uid, $password, 'product.product', 'search', [[['x_uuid', '=', $line['product_id']]]]);
             $productId = $productId[0] ?? false;

            $invoiceLineIds[] = [0, false, [
                'product_id' => $productId,
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
        $company_uuid = $productData['company_id'];
        $product_uuid = $productData['uuid'];



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

        // Fetch company id based on VM UUID
       $companyId = $models->execute_kw($db, $uid, $password, 'res.company', 'search', [[['x_uuid', '=', $company_uuid]]]);
       $companyId = $companyId[0] ?? false;

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
        
        $response = [ 'status' => 'success', 'id_created' => $newProductId];
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
   
    // Handle other endpoints similarly based on the HTTP method

    // Return a 404 response for unsupported endpoints
    http_response_code(404);