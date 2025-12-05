<?php
require_once 'config.php';
require_once 'db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (empty($data['transaction_id'])) {
        echo json_encode(['success' => false, 'message' => 'Transaction ID is required']);
        exit;
    }
    
    $db = new Database();
    $transaction = $db->getTransactionStatus($data['transaction_id']);
    
    if ($transaction) {
        echo json_encode([
            'success' => true,
            'status' => $transaction['status'],
            'result_code' => $transaction['result_code'],
            'result_desc' => $transaction['result_desc'],
            'mpesa_receipt' => $transaction['mpesa_receipt']
        ]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Transaction not found']);
    }
}
?>
