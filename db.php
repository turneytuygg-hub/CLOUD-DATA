<?php
require_once 'config.php';

class Database {
    private $conn;
    
    public function __construct() {
        try {
            $this->conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
            
            if ($this->conn->connect_error) {
                throw new Exception("Connection failed: " . $this->conn->connect_error);
            }
            
            // Create tables if they don't exist
            $this->createTables();
            
        } catch (Exception $e) {
            error_log("Database error: " . $e->getMessage());
            die("Database connection error. Please try again later.");
        }
    }
    
    private function createTables() {
        // Transactions table
        $transactionsTable = "
        CREATE TABLE IF NOT EXISTS transactions (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            transaction_id VARCHAR(50) UNIQUE,
            mpesa_receipt VARCHAR(50),
            phone VARCHAR(15),
            package_name VARCHAR(50),
            amount DECIMAL(10,2),
            merchant_phone VARCHAR(15),
            status ENUM('pending', 'completed', 'failed', 'cancelled') DEFAULT 'pending',
            result_code VARCHAR(10),
            result_desc VARCHAR(255),
            transaction_date DATETIME,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_phone (phone),
            INDEX idx_status (status),
            INDEX idx_transaction_id (transaction_id),
            INDEX idx_mpesa_receipt (mpesa_receipt)
        )";
        
        $this->conn->query($transactionsTable);
        
        // Packages table
        $packagesTable = "
        CREATE TABLE IF NOT EXISTS packages (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(50),
            description VARCHAR(255),
            amount DECIMAL(10,2),
            validity_days INT(3),
            is_active BOOLEAN DEFAULT TRUE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->conn->query($packagesTable);
        
        // Insert default packages if not exist
        $checkPackages = $this->conn->query("SELECT COUNT(*) as count FROM packages");
        $row = $checkPackages->fetch_assoc();
        
        if ($row['count'] == 0) {
            $defaultPackages = [
                ['10GB 1DAY', '10GB High-Speed Data for 1 Day', 100, 1],
                ['20GB 1DAY', '20GB High-Speed Data for 1 Day', 150, 1]
            ];
            
            $stmt = $this->conn->prepare("INSERT INTO packages (name, description, amount, validity_days) VALUES (?, ?, ?, ?)");
            
            foreach ($defaultPackages as $package) {
                $stmt->bind_param("ssdi", $package[0], $package[1], $package[2], $package[3]);
                $stmt->execute();
            }
            
            $stmt->close();
        }
        
        // Logs table
        $logsTable = "
        CREATE TABLE IF NOT EXISTS api_logs (
            id INT(11) AUTO_INCREMENT PRIMARY KEY,
            endpoint VARCHAR(100),
            request_data TEXT,
            response_data TEXT,
            status_code INT(5),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )";
        
        $this->conn->query($logsTable);
    }
    
    public function getConnection() {
        return $this->conn;
    }
    
    public function logRequest($endpoint, $request, $response, $statusCode) {
        $stmt = $this->conn->prepare("INSERT INTO api_logs (endpoint, request_data, response_data, status_code) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("sssi", $endpoint, $request, $response, $statusCode);
        $stmt->execute();
        $stmt->close();
    }
    
    public function saveTransaction($data) {
        $stmt = $this->conn->prepare("
            INSERT INTO transactions (
                transaction_id, phone, package_name, amount, merchant_phone, status, transaction_date
            ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
        ");
        
        $stmt->bind_param(
            "sssss",
            $data['transaction_id'],
            $data['phone'],
            $data['package'],
            $data['amount'],
            $data['merchant_phone']
        );
        
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function updateTransaction($mpesaReceipt, $resultCode, $resultDesc) {
        $status = ($resultCode == '0') ? 'completed' : 'failed';
        
        $stmt = $this->conn->prepare("
            UPDATE transactions 
            SET mpesa_receipt = ?, status = ?, result_code = ?, result_desc = ?
            WHERE transaction_id = ? OR mpesa_receipt = ?
            ORDER BY id DESC LIMIT 1
        ");
        
        $transactionId = 'TXN_' . time();
        $stmt->bind_param("ssssss", $mpesaReceipt, $status, $resultCode, $resultDesc, $transactionId, $mpesaReceipt);
        $result = $stmt->execute();
        $stmt->close();
        
        return $result;
    }
    
    public function getTransactionStatus($transactionId) {
        $stmt = $this->conn->prepare("
            SELECT status, result_code, result_desc, mpesa_receipt 
            FROM transactions 
            WHERE transaction_id = ? 
            ORDER BY id DESC LIMIT 1
        ");
        
        $stmt->bind_param("s", $transactionId);
        $stmt->execute();
        $result = $stmt->get_result();
        $transaction = $result->fetch_assoc();
        $stmt->close();
        
        return $transaction;
    }
    
    public function getPackages() {
        $result = $this->conn->query("SELECT * FROM packages WHERE is_active = TRUE ORDER BY amount ASC");
        $packages = [];
        
        while ($row = $result->fetch_assoc()) {
            $packages[] = $row;
        }
        
        return $packages;
    }
    
    public function __destruct() {
        if ($this->conn) {
            $this->conn->close();
        }
    }
}

// Initialize database
$db = new Database();
?>
