<?php

    header('Content-Type: application/json; charset=UTF-8');

    include 'include/connection/server.php';

    $urls = [
        'test' => [
            'url' => 'https://odoophpapi.test/'
        ],
        'vm_dev' => [
            'url' => 'https://paperless.vminnovations.dev/pm-api/'
        ]
    ];

    // Check if the selected connection exists
    if (array_key_exists($selectedConnection, $urls)) {
        $connection = $urls[$selectedConnection];
        $apiUrl = $connection['url'];
    } else {
        echo "Selected connection doesn't exist.";
    }
    

    include 'include/functions.php';

    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_REQUEST['t'])) {
        $endpoint = $_REQUEST['t'];

        if ($endpoint == 'odoo') {
            include_once('include/connection/odoo_db.php');
            require_once('include/ripcord/ripcord.php');
    
            $common = ripcord::client("$url/xmlrpc/2/common");
            // authenicate user
            $uid = $common->authenticate($db, $username, $password, array());

            
            if ($uid) {
                $response = ['status' => 'success', 'connection' => 'Autheniticated', 'host' => $url];
                echo json_encode($response);
            } else {
                $response = ['status' => 'fail', 'connection' => 'Failed'];
                echo json_encode($response);
            }
        }

        if ($endpoint == 'mysql') {
            include 'include/connection/mysql_db.php';
            // Establish a connection to your MySQL database
            $connection = mysqli_connect($host, $username, $password, $database);

            if ($connection) {
                $response = ['status' => 'success', 'connection' => 'Autheniticated', 'host' => $host];
                echo json_encode($response);
            } else {
                $response = ['status' => 'fail', 'connection' => 'Failed'];
                echo json_encode($response);
            }
        }
       
    }

    // API endpoint to retrieve data
    if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_REQUEST['e'])) {

        include 'include/connection/mysql_db.php';
        
        // Establish a connection to your MySQL database
        $connection = mysqli_connect($host, $username, $password, $database);

        if (!$connection) {
            echo "Failed to connect to the database.";
            exit;
        }

        try {
            
            $endpoint = $_REQUEST['e'];

            switch ($endpoint) {
                case 'contacts':
                    $query =    "select u.uuid, concat(u.first_name, ' ', u.last_name) name, u.email, u.contact_number phone, rt.key title, ur.is_active, s.uuid company_uuid, ur.strata_id company_id, s.strata_name, s.email_address, a.* 
                                from user_roles ur
                                join users u on u.id = ur.user_id
                                join role_types rt on rt.id = ur.role_type_id
                                join stratas s on s.id = ur.strata_id
                                join addresses a on a.id = s.address_id
                                where ur.is_active = 1";
                    break;
                
                case 'companies':
                    $query =    "select s.uuid, s.id, s.strata_name, s.email_address, s.contact_number, 'JM' country, 'JMD' currency, a.* from stratas s
                                join addresses a on a.id = s.address_id";
                    break;
                
                case 'products' :
                    $query =    "select i.*, s.uuid company_id, 'service' type, '2' category from items i
                                join stratas s on s.id = i.strata_id where i.is_active = 1";
                    break;

                case 'invoices':
                    $query =    "select inv.id, s.strata_name, s.uuid company_id, inv.uuid inv_uuid, inv.invoice_number, inv.invoice_date, inv.due_date, i.uuid item_uuid, id.quantity, id.unit_cost_in_cents unit_cost, id.tax_rate, u.uuid customer_id from invoices inv
                                join units u on inv.unit_id = u.id
                                join stratas s on s.id = inv.strata_id
                                join invoice_details id on id.invoice_id = inv.id
                                join items i on i.id = id.item_id
                                order by inv.id";
                    break;
                case 'units':
                    $query =    "select u.uuid, concat(u.short_description, ' / Lot ' ,u.lot_number) name, s.uuid company_uuid, a.address_line_1 street, p.name city, pr.user_id, us.email, us.contact_number phone from units u
                                join proprietors pr on pr.unit_id = u.id and pr.is_proxy = 1
                                join users us on pr.user_id = us.id
                                join stratas s on u.strata_id = s.id
                                join addresses a on u.address_id = a.id
                                join parishes p on a.parish_id = p.id";
                    break;
                case 'vendors':
                    $query =    "select v.*, s.uuid company_uuid from vendors v
                                join stratas s on s.id = v.strata_id
                                where v.is_active = 1";
                    break;
                case 'vendorproducts':
                    $query =    "select v.uuid, v.description name, s.uuid company_uuid,  'service' product_type, 3 'category', 100 price from vendors v
                                join stratas s on s.id = v.strata_id";
                    break;

                case 'expenses':
                    $query =    "select e.uuid inv_uuid, e.expense_no, e.expense_date, e.due_date, e.sub_total_in_cents price, s.uuid company_uuid, s.strata_name, ed.description, ed.quantity, ed.tax_rate, ed.sub_total_in_cents price, v.description label, v.uuid vendor_uuid  
                                from expenses e
                                join vendors v on v.id = e.vendor_id
                                join expense_details ed on ed.expense_id = e.id
                                join stratas s on s.id = e.property_id
                                order by e.id";
                    break;

                case 'invoicepayments':
                    $query =    "select s.uuid strata_uuid, s.strata_name, u.uuid unit_uuid, i.uuid inv_uuid, ip.date_paid, ip.payment_method_id, ip.reference_number, ip.amount_in_cents amount, ip.id, i.invoice_number
                                from invoice_payments ip
                                join invoices i on i.id = ip.invoice_id
                                join stratas s on s.id = i.strata_id
                                join units u on u.id = i.unit_id
                                join payment_methods pm on pm.id = ip.payment_method_id
                                order by ip.id";
                    break;
                    
                case 'expensepayments':
                    $query =    "select ep.date_paid, ep.payment_method_id, ep.amount_in_cents amount, s.uuid unit_uuid, s.strata_name, e.expense_no invoice_number, ep.reference_code from expense_payments ep
                                join expenses e on e.id = ep.expense_id
                                join stratas s on s.id = e.property_id
                                order by ep.id";
                    break;
                default:
                    $query =    "select u.uuid, concat(u.first_name, ' ', u.last_name) name, u.email, u.contact_number phone, rt.key title, ur.is_active, s.uuid company_uuid, ur.strata_id company_id, s.strata_name, s.email_address, a.* 
                                from user_roles ur
                                join users u on u.id = ur.user_id
                                join role_types rt on rt.id = ur.role_type_id
                                join stratas s on s.id = ur.strata_id
                                join addresses a on a.id = s.address_id
                                where ur.is_active = 1";
                    break;
            }

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

        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_REQUEST['e'])) {
        try {
            $endpoint = $_REQUEST['e'];

            switch ($endpoint) {
                case 'company':
                    $postData = file_get_contents('php://input');
                    $companyData = json_decode($postData, true);
                    $response = createCompany($companyData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;

                case 'inv':
                    $postData = file_get_contents('php://input');
                    $invoiceInfo = json_decode($postData, true);
                    $response = createInvoice($invoiceInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;

                case 'contact':
                    $postData = file_get_contents('php://input');
                    $contactData = json_decode($postData, true);
                    $response = createContact($contactData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                
                case 'vendor':
                    $postData = file_get_contents('php://input');
                    $contactData = json_decode($postData, true);
                    $response = createVendor($contactData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;

                case 'updatecontact':
                    $postData = file_get_contents('php://input');
                    $contactData = json_decode($postData, true);
                    $response = updateContact($contactData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'updatevendor':
                    $postData = file_get_contents('php://input');
                    $contactData = json_decode($postData, true);
                    $response = updateVendor($contactData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'updateinv':
                    $postData = file_get_contents('php://input');
                    $invoiceInfo = json_decode($postData, true);
                    $response = updateInvoice($invoiceInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'updatebill':
                    $postData = file_get_contents('php://input');
                    $invoiceInfo = json_decode($postData, true);
                    $response = updateExpense($invoiceInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'reverseinv':
                    $postData = file_get_contents('php://input');
                    $invoiceInfo = json_decode($postData, true);
                    $response = reverseInvoice($invoiceInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'reversebill':
                    $postData = file_get_contents('php://input');
                    $billInfo = json_decode($postData, true);
                    $response = reverseExpense($billInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'product':
                    $postData = file_get_contents('php://input');
                    $productData = json_decode($postData, true);
                    $response = createProduct($productData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;

                case 'bill':
                    $postData = file_get_contents('php://input');
                    $billInfo = json_decode($postData, true);
                    $response = createBill($billInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;

                case 'pay':
                    $postData = file_get_contents('php://input');
                    $paymentInfo = json_decode($postData, true);
                    $response = invoicePayment($paymentInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'paybill':
                    $postData = file_get_contents('php://input');
                    $paymentInfo = json_decode($postData, true);
                    $response = billPayment($paymentInfo);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'paymentmethod':
                    $postData = file_get_contents('php://input');
                    $paymentMethodData = json_decode($postData, true);
                    $response = createPaymentMethod($paymentMethodData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                case 'paymentterm':
                    $postData = file_get_contents('php://input');
                    $paymentTermsData = json_decode($postData, true);
                    $response = createPaymentTerms($paymentTermsData);
                    echo json_encode($response, JSON_PRETTY_PRINT);
                    break;
                
                
                case 'loadcompanies':
                    loadCompanies($apiUrl);
                    break;
                
                case 'loadcontacts':
                    loadContacts($apiUrl);
                    break;
                
                case 'loadunits':
                    loadUnits($apiUrl);
                    break;
                
                case 'loadproducts':
                    loadProducts($apiUrl);
                    break;
                    
                case 'loadvendorproducts':
                    loadVendorProducts($apiUrl);
                    break;
                case 'loadinvoices':
                    loadInvoices($apiUrl);
                    break;

                
                case 'loadvendors':
                    loadVendors($apiUrl);
                    break;

                case 'loadexpenses':
                    loadExpenses($apiUrl);
                    break;

                case 'loadinvpayments':
                    loadInvoicePayments($apiUrl);
                    break;
                case 'loadexpayments':
                    loadExpensePayments($apiUrl);
                    break;
            

                
                default:
                    header('Content-Type: application/json', true, 404);
                    echo json_encode(['status' => 'error', 'message' => 'Invalid endpoint'], JSON_PRETTY_PRINT);
                    break;
            }
           
        } catch (Exception $e) {
            // Output the error
            header('Content-Type: application/json', true, 500);
            echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_PRETTY_PRINT);
        }
    }
    