<?php

    $selectedConnection = 'test'; // Change this to switch between different connections

    $connections = [
        'test' => [
            'url' => 'com/',
            'db' => '-9054233',
            'username' => '',
            'password' => '',
        ],
        'test_community' => [
            'url' => '.net/',
            'db' => 'Test',
            'username' => '',
            'password' => '',
        ],
        'vm_dev' => [
            'url' => '.dev/',
            'db' => 'Test',
            'username' => '',
            'password' => '',
        ]
    ];

    // Check if the selected connection exists
    if (array_key_exists($selectedConnection, $connections)) {
        $connection = $connections[$selectedConnection];
        
        $url = $connection['url'];
        $db = $connection['db'];
        $username = $connection['username'];
        $password = $connection['password'];

    } else {
        echo "Selected connection doesn't exist.";
    }