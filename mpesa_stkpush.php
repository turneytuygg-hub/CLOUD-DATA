<?php
require_once 'config.php';
require_once 'db.php';

class MpesaSTKPush {
    private $db;
    private $token;
    
    public function __construct($token) {
        $this->db = new Database();
        $this->token = $token;
    }
    
    public function initiateSTKPush($phone, $amount, $package, $transactionId) {
        // Format phone number (remove leading 0, add 254)
        $phone = $this->formatPhoneNumber($phone);
        
        // Get timestamp
        $timestamp = date('YmdHis');
        
        // Generate password
        $password = base64_encode(MPESA_SHORTCODE . MPESA_PASSKEY . $timestamp);
        
        // Prepare request payload
        $payload = [
            'BusinessShortCode' => MPESA_SHORTCODE,
            'Password' => $password,
            'Timestamp' => $timestamp,
            'TransactionType' => 'CustomerPayBillOnline',
            'Amount' => $amount,
            'PartyA' => $phone,
            'PartyB' => MPESA_SHORTCODE,
            'PhoneNumber' => $phone,
            'CallBackURL' => MPESA_CALLBACK_URL,
            'AccountReference' => 'CLOUD_DATA_' . $package,
            'TransactionDesc' => 'Payment for ' . $package . ' data package'
        ];
        
        // Save transaction to database
        $transactionData = [
            'transaction_id' => $transactionId,
            'phone' => $phone,
            'package' => $package,
            'amount' => $amount,
            'merchant_phone' => MERCHANT_PHONE
        ];
        
        $this->db->saveTransaction($transactionData);
        
        // Make API request
        $url = $this->getSTKPushUrl();
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->token,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log request
        $this->db->logRequest('stk_push', json_encode($payload), $response, $httpCode);
        
        return [
            'http_code' => $httpCode,
            'response' => json_decode($response, true)
        ];
    }
    
    private function formatPhoneNumber($phone) {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // If starts with 0, replace with 254
        if (substr($phone, 0, 1) === '0') {
            $phone = '254' . substr($phone, 1);
        }
        
        // If starts with 254, keep as is
        // If starts with anything else, assume it's already formatted
        return $phone;
    }
    
    private function getSTKPushUrl() {
        if (MPESA_ENVIRONMENT == 'sandbox') {
            return 'https://sandbox.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        } else {
            return 'https://api.safaricom.co.ke/mpesa/stkpush/v1/processrequest';
        }
    }
}

// API endpoint for STK Push
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    // Get POST data
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Validate required fields
    $required = ['phone', 'package', 'amount'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            echo json_encode([
                'success' => false,
                'message' => "Missing required field: $field"
            ]);
            exit;
        }
    }
    
    // Validate phone number
    $phone = $data['phone'];
    if (!preg_match('/^07[0-9]{8}$/', $phone)) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid phone number format. Use 07XXXXXXXX'
        ]);
        exit;
    }
    
    // Validate amount
    $amount = $data['amount'];
    if (!is_numeric($amount) || $amount <= 0) {
        echo json_encode([
            'success' => false,
            'message' => 'Invalid amount'
        ]);
        exit;
    }
    
    // Generate transaction ID
    $transactionId = 'CLOUD_' . date('YmdHis') . '_' . rand(1000, 9999);
    
    // Get token
    require_once 'mpesa_token.php';
    $tokenGenerator = new MpesaToken();
    $token = $tokenGenerator->generateToken();
    
    if (!$token) {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to authenticate with M-Pesa'
        ]);
        exit;
    }
    
    // Initiate STK Push
    $mpesaSTK = new MpesaSTKPush($token);
    $result = $mpesaSTK->initiateSTKPush($phone, $amount, $data['package'], $transactionId);
    
    if ($result['http_code'] == 200 && isset($result['response']['ResponseCode'])) {
        $responseCode = $result['response']['ResponseCode'];
        
        if ($responseCode == '0') {
            echo json_encode([
                'success' => true,
                'message' => 'STK Push initiated successfully. Check your phone to enter PIN.',
                'transaction_id' => $transactionId,
                'checkout_request_id' => $result['response']['CheckoutRequestID']
            ]);
        } else {
            $errorMessages = [
                '1' => 'The balance is insufficient for the transaction',
                '1032' => 'Transaction cancelled by user',
                '1037' => 'Timeout, unknown transaction',
                '2001' => 'Insufficient balance',
                '2006' => 'Transaction failed'
            ];
            
            $message = isset($errorMessages[$responseCode]) 
                ? $errorMessages[$responseCode] 
                : 'Transaction failed. Please try again.';
            
            echo json_encode([
                'success' => false,
                'message' => $message,
                'response_code' => $responseCode
            ]);
        }
    } else {
        echo json_encode([
            'success' => false,
            'message' => 'Failed to initiate payment. Please try again.',
            'debug' => $result
        ]);
    }
}
?>
