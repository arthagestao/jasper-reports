<?php
// Set timezone
date_default_timezone_set('America/Sao_Paulo');

// Prevent session cookies
ini_set('session.use_cookies', 0);

// Class loader
$autoload = require dirname(__DIR__) . '/vendor/autoload.php';

// Register test classes
$autoloader->addPsr4('JasperPHP\Tests\\', __DIR__ . '/src');