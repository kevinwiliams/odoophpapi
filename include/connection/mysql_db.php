<?php
    
    $connections = [
        'test' => [
            'host' => '127.0.0.1',
            'port' => '3306',
            'db' => '',
            'username' => 'root',
            'password' => ''
        ],
        'vm_dev' => [
            'host' => '127.0.0.1',
            'port' => '3306',
            'db' => '',
            'username' => '',
            'password' => ''
        ]
    ];

    // Check if the selected connection exists
    if (array_key_exists($selectedConnection, $connections)) {
        $connection = $connections[$selectedConnection];
        
        $host = $connection['host'];
        $port = $connection['port'];
        $database = $connection['db'];
        $username = $connection['username'];
        $password = $connection['password'];

        // Now you can use the connection details
        // ...
    } else {
        echo "Selected connection doesn't exist.";
    }