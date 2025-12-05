<?php
// M-Pesa Daraja API Configuration
define('MPESA_ENVIRONMENT', 'sandbox'); // 'sandbox' or 'production'

// Sandbox Credentials (for testing)
define('MPESA_CONSUMER_KEY', 'YOUR_SANDBOX_CONSUMER_KEY_HERE');
define('MPESA_CONSUMER_SECRET', 'YOUR_SANDBOX_CONSUMER_SECRET_HERE');
define('MPESA_PASSKEY', 'YOUR_SANDBOX_PASSKEY_HERE');
define('MPESA_SHORTCODE', '174379'); // Sandbox shortcode

// Production Credentials (when live)
// define('MPESA_CONSUMER_KEY', 'YOUR_PRODUCTION_CONSUMER_KEY');
// define('MPESA_CONSUMER_SECRET', 'YOUR_PRODUCTION_CONSUMER_SECRET');
// define('MPESA_PASSKEY', 'YOUR_PRODUCTION_PASSKEY');
// define('MPESA_SHORTCODE', 'YOUR_BUSINESS_SHORTCODE'); // Your actual shortcode

// Callback URLs
define('MPESA_CALLBACK_URL', 'https://yourdomain.com/mpesa_callback.php');
define('MPESA_TIMEOUT_URL', 'https://yourdomain.com/mpesa_callback.php');

// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_username');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'cloud_data_mpesa');

// Your phone number to receive payments
define('MERCHANT_PHONE', '0714330593');

// Security
define('ENCRYPTION_KEY', 'your-secure-encryption-key-here'); // Change this to a random string

// Base URL
define('BASE_URL', 'https://yourdomain.com/');

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Force HTTPS in production
if (MPESA_ENVIRONMENT === 'production' && empty($_SERVER['HTTPS'])) {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}
?>
