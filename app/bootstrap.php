<?php

session_start();

require __DIR__ . '/config.php';
require __DIR__ . '/helpers.php';
require __DIR__ . '/database.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/repositories/ProductRepository.php';
require __DIR__ . '/services/WooImporter.php';
require __DIR__ . '/services/IntegrationService.php';
require __DIR__ . '/controllers/FrontController.php';
require __DIR__ . '/controllers/AdminController.php';
require __DIR__ . '/controllers/VendorController.php';
require __DIR__ . '/controllers/ApiController.php';

db();
