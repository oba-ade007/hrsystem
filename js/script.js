// HR Software - Main JavaScript

document.addEventListener('DOMContentLoaded', function() {
    // Add any custom functionality here
    
    // Example: Auto-close alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.display = 'none';
        }, 5000);
    });
});
