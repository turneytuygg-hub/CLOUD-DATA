let selectedPackage = null;
let selectedPrice = 0;

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
    const paymentSection = document.getElementById('paymentSection');
    paymentSection.style.opacity = '0';
    paymentSection.style.transform = 'translateY(20px)';
    setTimeout(() => {
        paymentSection.style.display = 'none';
    }, 300);
}

function showNotification(message, isError = false) {
    const notification = document.getElementById('notification');
    const notificationText = document.getElementById('notificationText');
    
    notificationText.textContent = message;
    
    if (isError) {
        notification.style.background = '#ff5252';
    } else {
        notification.style.background = '#00c853';
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
    
    // Disable pay button
    const payButton = document.getElementById('payButton');
    payButton.disabled = true;
    payButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
    
    try {
        // Simulate payment processing
        const response = await processPayment(phone, selectedPackage, selectedPrice);
        
        if (response.success) {
            showNotification('Payment initiated! Check your phone for STK prompt.');
            
            // Simulate successful payment after 10 seconds
            setTimeout(() => {
                showNotification(`Payment successful! Ksh ${selectedPrice} received for ${selectedPackage} package. Data will be delivered shortly.`);
                payButton.innerHTML = '<i class="fas fa-check"></i> Payment Successful';
                payButton.style.background = '#00c853';
                
                // Reset form after successful payment
                setTimeout(() => {
                    cancelPayment();
                    selectedPackage = null;
                    selectedPrice = 0;
                    payButton.disabled = false;
                    payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
                    payButton.style.background = '';
                }, 3000);
            }, 10000);
        } else {
            showNotification(response.message || 'Payment failed. Please try again.', true);
            payButton.disabled = false;
            payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
        }
    } catch (error) {
        console.error('Payment error:', error);
        showNotification('An error occurred. Please try again.', true);
        payButton.disabled = false;
        payButton.innerHTML = '<i class="fas fa-lock"></i> Pay Now';
    }
});

// Simulated payment processing function
async function processPayment(phone, packageName, amount) {
    // In a real implementation, this would connect to your backend
    // For now, we'll simulate API call
    
    console.log(`Processing payment:
        Phone: ${phone}
        Package: ${packageName}
        Amount: ${amount}
        Merchant: 0714330593
    `);
    
    // Simulate API delay
    await new Promise(resolve => setTimeout(resolve, 2000));
    
    // Simulate successful payment 90% of the time
    const isSuccess = Math.random() < 0.9;
    
    if (isSuccess) {
        return {
            success: true,
            message: 'STK push sent to your phone',
            transactionId: 'TXN_' + Date.now(),
            amount: amount,
            phone: phone,
            package: packageName
        };
    } else {
        return {
            success: false,
            message: 'Payment failed. Please ensure you have sufficient balance.'
        };
    }
}

// Real-time phone number validation
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.substring(0, 10);
    e.target.value = value;
});

document.getElementById('confirmPhone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, '');
    if (value.length > 10) value = value.substring(0, 10);
    e.target.value = value;
});

// Initialize payment section
document.addEventListener('DOMContentLoaded', function() {
    const paymentSection = document.getElementById('paymentSection');
    paymentSection.style.opacity = '0';
    paymentSection.style.transform = 'translateY(20px)';
    paymentSection.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
});
