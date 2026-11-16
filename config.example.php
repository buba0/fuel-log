<?php

return [
    'dsn' => 'mysql:host=127.0.0.1;port=3306;dbname=fuel_log;charset=utf8mb4',
    'username' => 'fuel_log',
    'password' => 'replace-with-the-application-database-password',
    'timezone' => 'Europe/Warsaw',
    'allow_signup' => true,
    // Optional: install freeradius-utils and configure the matching server client.
    // 'radius' => ['server' => 'RADIUS_SERVER_IP', 'secret_file' => '/etc/fuel-log/radius.secret'],
];
