<?php
require_once 'config.php';
require_once 'db.php';

class MpesaToken {
    private $db;
    
    public function __construct() {
        $this->db = new Database();
    }
    
    public function generateToken() {
        // Check if token exists and is still valid
        $token = $this->getStoredToken();
        if ($token) {
            return $token;
        }
        
        // Generate new token
        $url = $this->getTokenUrl();
        $credentials = base64_encode(MPESA_CONSUMER_KEY . ':' . MPESA_CONSUMER_SECRET);
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: Basic ' . $credentials]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // Log request
        $this->db->logRequest('token_generation', json_encode(['url' => $url]), $response, $httpCode);
        
        if ($httpCode == 200) {
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {
                $this->storeToken($result['access_token'], $result['expires_in']);
                return $result['access_token'];
            }
        }
        
        error_log("Failed to generate M-Pesa token. Response: " . $response);
        return false;
    }
    
    private function getTokenUrl() {
        if (MPESA_ENVIRONMENT == 'sandbox') {
            return 'https://sandbox.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        } else {
            return 'https://api.safaricom.co.ke/oauth/v1/generate?grant_type=client_credentials';
        }
    }
    
    private function getStoredToken() {
        $conn = $this->db->getConnection();
        $result = $conn->query("SELECT token, created_at FROM mpesa_tokens WHERE expires_at > NOW() ORDER BY id DESC LIMIT 1");
        
        if ($result && $result->num_rows > 0) {
            $row = $result->fetch_assoc();
            return $row['token'];
        }
        
        return false;
    }
    
    private function storeToken($token, $expiresIn) {
        $conn = $this->db->getConnection();
        
        // Create tokens table if not exists
        $conn->query("
            CREATE TABLE IF NOT EXISTS mpesa_tokens (
                id INT AUTO_INCREMENT PRIMARY KEY,
                token TEXT NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                expires_at DATETIME NOT NULL
            )
        ");
        
        // Clear old tokens
        $conn->query("DELETE FROM mpesa_tokens WHERE expires_at < NOW()");
        
        // Store new token
        $expiresAt = date('Y-m-d H:i:s', time() + $expiresIn - 60); // Subtract 60 seconds for safety
        $stmt = $conn->prepare("INSERT INTO mpesa_tokens (token, expires_at) VALUES (?, ?)");
        $stmt->bind_param("ss", $token, $expiresAt);
        $stmt->execute();
        $stmt->close();
    }
}

// API endpoint to get token
if (isset($_GET['action']) && $_GET['action'] == 'get_token') {
    header('Content-Type: application/json');
    
    $mpesaToken = new MpesaToken();
    $token = $mpesaToken->generateToken();
    
    if ($token) {
        echo json_encode(['success' => true, 'token' => $token]);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to generate token']);
    }
}
?>
