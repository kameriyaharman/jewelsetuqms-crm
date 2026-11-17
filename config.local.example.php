<?php
// Rename to config.local.php on shared hosting and fill in the database details
// from cPanel → MySQL Databases. Tables are created automatically on first visit.
return [
    'DB_DRIVER' => 'mysql',
    'DB_HOST'   => 'localhost',
    'DB_PORT'   => '3306',
    'DB_NAME'   => 'cpaneluser_qtrack',
    'DB_USER'   => 'cpaneluser_qtrack',
    'DB_PASS'   => 'change-me',
    'APP_URL'   => 'https://qtrack.jewelsetu.com',
    'SEED_DEMO' => '0',   // '1' loads demo clients/vendors/jobs on first install
];
