<?php
require_once 'config.php';
require_once 'db.php';

// Log the callback for debugging
file_put_contents('callback_log.txt', date('Y-m-d H:i:s') . " - " . file_get_contents('php://input') . "\n", FILE_APPEND);

// Get callback data
$callbackJSON = file_get_contents('php://input');
$callbackData = json_decode($callbackJSON, true);

if (isset($callbackData['Body']['stkCallback'])) {
    $stkCallback = $callbackData['Body']['stkCallback'];
    $resultCode = $stkCallback['ResultCode'];
    $resultDesc = $stkCallback['ResultDesc'];
    $checkoutRequestID = $stkCallback['CheckoutRequestID'];
    
    // Initialize database
    $db = new Database();
    
    if ($resultCode == 0) {
        // Successful transaction
        if (isset($stkCallback['CallbackMetadata']['Item'])) {
            $items = $stkCallback['CallbackMetadata']['Item'];
            $mpesaReceipt = '';
            $phone = '';
            $amount = '';
            
            foreach ($items as $item) {
                if ($item['Name'] == 'MpesaReceiptNumber') {
                    $mpesaReceipt = $item['Value'];
                }
                if ($item['Name'] == 'PhoneNumber') {
                    $phone = $item['Value'];
                }
                if ($item['Name'] == 'Amount') {
                    $amount = $item['Value'];
                }
            }
            
            // Update transaction in database
            $db->updateTransaction($mpesaReceipt, $resultCode, $resultDesc);
            
            // Log successful transaction
            error_log("Payment successful: Receipt $mpesaReceipt, Phone $phone, Amount $amount");
            
            // Here you can trigger data bundle purchase from your provider
            // Example: send SMS to customer, update their account, etc.
            
        }
    } else {
        // Failed transaction
        $db->updateTransaction($checkoutRequestID, $resultCode, $resultDesc);
        error_log("Payment failed: $resultDesc (Code: $resultCode)");
    }
    
    // Send response to M-Pesa
    header('Content-Type: application/json');
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Callback processed successfully'
    ]);
    
} else {
    // Invalid callback
    error_log("Invalid callback received: " . $callbackJSON);
    
    header('Content-Type: application/json');
    echo json_encode([
        'ResultCode' => 1,
        'ResultDesc' => 'Invalid callback data'
    ]);
}
?>
