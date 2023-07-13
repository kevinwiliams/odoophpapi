<?php

include('../include/connection/odoo_db.php');
require_once('../include/ripcord/ripcord.php');
//$info = ripcord::client('https://demo.odoo.com/start')->start();
//list($url, $db, $username, $password) = array($info['host'], $info['database'], $info['user'], $info['password']);

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

exit;

//connect to odoo models
$models = ripcord::client("$url/xmlrpc/2/object");

// -- API CALL -- //
//ignore SSL
$arrContextOptions = array(
    "ssl" => array(
      "verify_peer" => false,
      "verify_peer_name" => false,
    )
);
$context = stream_context_create($arrContextOptions);
$response = file_get_contents('https://propman-api.test', false, $context);

// Process the API response
if ($response) {
    // Decode the JSON response into an associative array
    $data = json_decode($response, true);

    // Process the data as needed
    foreach ($data as $item) {
        $itemData = 
        [[
            'name' => $item['name'],
            'phone'  => '555' . str_replace("-", "", $item['phone']), //area code required
            'email'  => str_replace(".com", ".extapi.com", $item['email']),
            'function' => 'Developer'
        ]];
        
        /// --- Insert records in ODOO db
        /// --- model -> res.partner (contacts) 
        /// --- action -> create (insert new records)
        $new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
        //echo json_encode([$new_id], JSON_PRETTY_PRINT);
        
     }
        $response = [ 'status' => 'success', 'id_created' => $new_id];
        echo json_encode($response, JSON_PRETTY_PRINT);

} else {
    $response = [ 'status' => 'failed'];
    echo json_encode($response, JSON_PRETTY_PRINT);
    // Handle the failure as needed
}