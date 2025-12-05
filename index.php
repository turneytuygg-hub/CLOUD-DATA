<script>
let selectedPackage = null;
let selectedPrice = 0;
let currentTransactionId = null;
let checkInterval = null;

// API Endpoints
const API_BASE_URL = window.location.origin;
const API_ENDPOINTS = {
    token: 'mpesa_token.php?action=get_token',
    stkpush: 'mpesa_stkpush.php',
    checkStatus: 'transactions.php'
};

function selectPackage(packageName, price) {
    selectedPackage = packageName;
    selectedPrice = price;
    
    // Update UI
    document.getElementById('packageName').textContent = packageName;
    document.getElementById('packageAmount').textContent = price;
    
    // Show payment section with animation
    const paymentSection = document.getElementById('paymentSection');
    paymentSection.style.display = 'block';
    setTimeout(() => {
        paymentSection.style.opacity = '1';
        paymentSection.style.transform = 'translateY(0)';
    }, 10);
    
    // Scroll to payment section
    paymentSection.scrollIntoView({ behavior: 'smooth' });
    
    // Reset form
    document.getElementById('paymentForm').reset();
}

function cancelPayment() {
    if (checkInterval) {
        clearInterval(checkInterval);
        checkInterval = null;
    }
    
    const paymentSection = document.getElementById('paymentSection');
    paymentSection.style.opacity = '0';
    paymentSection.style.transform = 'translateY(20px)';
    setTimeout(() => {
        paymentSection.style.display = 'none';
    }, 300);
    
    selectedPackage = null;
    selectedPrice = 0;
    currentTransactionId = null;
}

function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    const notificationText = document.getElementById('notificationText');
    const icon = notification.querySelector('i');
    
    notificationText.textContent = message;
    
    if (isError) {
        notification.style.background = '#ff5252';
        icon.className = 'fas fa-exclamation-circle';
    } else {
        notification.style.background = '#00c853';
        icon.className = 'fas fa-check-circle';
    }
    
    notification.classList.add('show');
    
    setTimeout(() => {
        notification.classList.remove('show');
    }, 5000);
}

// Form submission handler
document.getElementById('paymentForm').addEventListener('submit', async function(e) {
    e.preventDefault();
    
    if (!selectedPackage) {
        showNotification('Please select a package first!', true);
        return;
    }
    
    const phone = document.getElementById('phone').value;
    const confirmPhone = document.getElementById('confirmPhone').value;
    
    // Validate phone numbers
    if (phone.length !== 10 || !phone.startsWith('07')) {
        showNotification('Please enter a valid Kenyan phone number (07XXXXXXXX)', true);
        return;
    }
    
    if (phone !== confirmPhone) {
        showNotification('Phone numbers do not match!', true);
        return;
    }
    
    // Validate terms
    if (!document.getElementById('terms').checked) {
        showNotification('Please agree to the terms and conditions', true);
        return;
    }
    
    // Disable pay button
    const payButton = document.getElementById('payButton');
    payButton.disabled = true;
    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Initiating Payment...';
    
    try {
        // Initiate M-Pesa STK Push
        const response = await initiateMpesaPayment(phone, selectedPackage, selectedPrice);
        
        if (response.success) {
            showNotification('Payment initiated! Check your phone for STK prompt and enter your M-PESA PIN.');
            currentTransactionId = response.transaction_id;
            
            // Start checking transaction status
            startTransactionPolling(response.transaction_id);
            
            payButton.innerHTML = '<i class="fas fa-mobile-alt"></i> Enter PIN on Phone';
            payButton.style.background = '#ff9800';
            
        } else {
            showNotification(response.message || 'Payment initiation failed. Please try again.', true);
            payButton.disabled = false;
            payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
            payButton.style.background = '';
        }
    } catch (error) {
        console.error('Payment error:', error);
        showNotification('An error occurred. Please try again.', true);
        payButton.disabled = false;
        payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
        payButton.style.background = '';
    }
});

// M-Pesa Payment Functions
async function initiateMpesaPayment(phone, packageName, amount) {
    const payload = {
        phone: phone,
        package: packageName,
        amount: amount
    };
    
    const response = await fetch(API_ENDPOINTS.stkpush, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify(payload)
    });
    
    return await response.json();
}

async function checkTransactionStatus(transactionId) {
    const response = await fetch(API_ENDPOINTS.checkStatus, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({ transaction_id: transactionId })
    });
    
    return await response.json();
}

function startTransactionPolling(transactionId) {
    if (checkInterval) {
        clearInterval(checkInterval);
    }
    
    checkInterval = setInterval(async () => {
        try {
            const result = await checkTransactionStatus(transactionId);
            
            if (result.success) {
                const status = result.status;
                const payButton = document.getElementById('payButton');
                
                if (status === 'completed') {
                    // Payment successful
                    clearInterval(checkInterval);
                    
                    showNotification(`Payment successful! Receipt: ${result.mpesa_receipt}. ${selectedPackage} data will be delivered shortly.`);
                    
                    payButton.innerHTML = '<i class="fas fa-check"></i> Payment Successful';
                    payButton.style.background = '#00c853';
                    
                    // Reset form after successful payment
                    setTimeout(() => {
                        cancelPayment();
                        payButton.disabled = false;
                        payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
                        payButton.style.background = '';
                    }, 5000);
                    
                } else if (status === 'failed') {
                    // Payment failed
                    clearInterval(checkInterval);
                    
                    showNotification(`Payment failed: ${result.result_desc}`, true);
                    
                    payButton.disabled = false;
                    payButton.innerHTML = '<i class="fas fa-lock"></i> Try Again';
                    payButton.style.background = '';
                }
                // If status is 'pending', continue polling
            }
        } catch (error) {
            console.error('Error checking transaction:', error);
        }
    }, 5000); // Check every 5 seconds
    
    // Stop polling after 5 minutes
    setTimeout(() => {
        if (checkInterval) {
            clearInterval(checkInterval);
            checkInterval = null;
            
            const payButton = document.getElementById('payButton');
            payButton.disabled = false;
            payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
            payButton.style.background = '';
            
            showNotification('Payment timeout. Please try again.', true);
        }
    }, 300000); // 5 minutes
}

// Real-time phone number validation
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.substring(0, 10);
    if (value.length > 2 && !value.startsWith('07')) {
        value = '07' + value.substring(2);
    }
    e.target.value = value;
});

document.getElementById('confirmPhone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.substring(0, 10);
    if (value.length > 2 && !value.startsWith('07')) {
        value = '07' + value.substring(2);
    }
    e.target.value = value;
});

// Initialize payment section
document.addEventListener('DOMContentLoaded', function() {
    const paymentSection = document.getElementById('paymentSection');
    paymentSection.style.opacity = '0';
    paymentSection.style.transform = 'translateY(20px)';
    paymentSection.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
    
    // Load packages from database (optional)
    // loadPackagesFromServer();
});

// Optional: Load packages from server
async function loadPackagesFromServer() {
    try {
        const response = await fetch('get_packages.php');
        const packages = await response.json();
        
        if (packages && packages.length > 0) {
            // Update UI with packages from database
            // This would dynamically create package cards
        }
    } catch (error) {
        console.error('Failed to load packages:', error);
    }
}
</script>
