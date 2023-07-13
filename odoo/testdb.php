<?php
include('../include/connection/odoo_db.php');
require_once('../include/ripcord/ripcord.php');
//$info = ripcord::client('https://demo.odoo.com/start')->start();
//list($url, $db, $username, $password) = array($info['host'], $info['database'], $info['user'], $info['password']);

$common = ripcord::client("$url/xmlrpc/2/common");
$listing = $common->version();
// echo (json_encode($listing, JSON_PRETTY_PRINT));

$uid = $common->authenticate($db, $username, $password, array());
// echo($uid);

if ($uid) {
    echo 'Autheniticated';
} else {
    echo 'Not authenticated';
}
echo '<br>';
//connect to odoo models
$models = ripcord::client("$url/xmlrpc/2/object");
//list records - returns IDs
$query = [[['is_company', '=', true]]];
$ids = $models->execute_kw($db, $uid, $password, 'res.partner', 'search', $query);
//echo json_encode($ids, JSON_PRETTY_PRINT);
//list pulled fields
$flds = $models->execute_kw($db, $uid, $password, 'res.partner', 'read', [$ids], ['fields' => ['name', 'country_id', 'comment']]);
// echo json_encode($flds, JSON_PRETTY_PRINT);

//insert data 
$data = [
    [
        'name'  => 'External APi Contact',
        'email' => 'externalapicontact@mailer.net'
    ]
];
    
//$new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $data);    
//echo json_encode($new_id, JSON_PRETTY_PRINT);

$id = [85];
$new_data = 
    [
        'phone'  => '785524871',
        'mobile'  => '8547235156',
        'function' => 'Developer'
    ];
//update data
//$updated = $models->execute_kw($db, $uid, $password, 'res.partner', 'write', [$id, $new_data]); 
//echo json_encode($updated, JSON_PRETTY_PRINT);
// -- API CALL -- //

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
        [
            'name' => $item['name'],
            'phone'  => '555' . str_replace("-", "", $item['phone']), //area code required
            'email'  => str_replace(".com", ".extapi.com", $item['email']),
            'function' => 'Developer'
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

