<?php
// filepath: c:\Users\HP\JewelEntry2.01\config.php
return [
    'db_host' => 'localhost',
    'db_user' => 'root',
    'db_pass' => '',
    'db_name' => 'jewelentryApp',
    
    'pagination' => [
        'items_per_page' => 20
    ],
    
    'uploads' => [
        'path' => 'uploads/jewelry/',
        'allowed_types' => ['jpg', 'jpeg', 'png'],
        'max_size' => 5242880 // 5MB
    ]
];
?>