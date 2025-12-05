# Cloud Data Packages with Real M-Pesa Integration

A complete one-page website for selling cloud data packages with real M-Pesa STK Push integration.

## Features
- Real M-Pesa STK Push integration
- Dark theme with purple color scheme
- Responsive design
- Transaction tracking
- Database logging
- Real-time payment status updates

## Prerequisites

### 1. M-Pesa Daraja API Credentials
You need to register for:
- **Sandbox**: https://developer.safaricom.co.ke/
- **Production**: Contact Safaricom for live credentials

### 2. Web Hosting Requirements
- PHP 7.4 or higher
- MySQL 5.7 or higher
- SSL Certificate (HTTPS required for M-Pesa)
- cURL enabled

### 3. Database Setup
Create a MySQL database and user:
```sql
CREATE DATABASE cloud_data_mpesa;
CREATE USER 'clouddata_user'@'localhost' IDENTIFIED BY 'strong_password';
GRANT ALL PRIVILEGES ON cloud_data_mpesa.* TO 'clouddata_user'@'localhost';
FLUSH PRIVILEGES;
