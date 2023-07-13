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

//connect to odoo models
$models = ripcord::client("$url/xmlrpc/2/object");

$itemData = 
        [[
            'name' => $name,
            'phone'  => $phone, //area code required
            'email'  => $email,
            'function' => 'Developer'
        ]];

$new_id = $models->execute_kw($db, $uid, $password, 'res.partner', 'create', $itemData); 
$response = [ 'status' => 'success', 'id_created' => $new_id];
        echo json_encode($response, JSON_PRETTY_PRINT);
