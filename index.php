<?php

    header('Content-Type: application/json; charset=UTF-8');

    include 'functions.php';

    // API endpoint to retrieve data
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {

        include 'include/connection/mysql_db.php';
        
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

        $queryInvoices = "select s.uuid company_id, inv.uuid inv_uuid, inv.invoice_number, inv.invoice_date, inv.due_date, i.uuid item_uuid, id.quantity, id.unit_cost_in_cents unit_cost, id.tax_rate, u.uuid customer_id from invoices inv
        join units u on inv.unit_id = u.id
        join stratas s on s.id = inv.strata_id
        join invoice_details id on id.invoice_id = inv.id
        join items i on i.id = id.item_id where inv.id > 160 and inv.id < 166 ";

        $result = mysqli_query($connection, $queryContacts);
    
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
                
                case 'loadcompanies':
                    loadCompanies();
                    break;
                
                case 'loadcontacts':
                    loadContacts();
                    break;
                
                case 'loadunits':
                    loadUnits();
                    break;
                
                case 'loadinvoices':
                    loadInvoices();
                    break;

                case 'loadproducts':
                    loadProducts();
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
    