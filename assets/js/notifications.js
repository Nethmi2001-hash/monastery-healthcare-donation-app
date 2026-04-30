/**
 * Browser Notification System
 * Pure JavaScript - No external dependencies
 * Shows desktop notifications when donations received, appointments made, etc.
 */

class MonasteryNotifications {
    constructor() {
        this.permission = 'default';
        this.checkPermission();
    }

    /**
     * Check and request notification permission
     */
    checkPermission() {
        if (!("Notification" in window)) {
            console.log("This browser does not support notifications");
            return false;
        }

        this.permission = Notification.permission;
        return this.permission === 'granted';
    }

    /**
     * Request notification permission from user
     */
    async requestPermission() {
        if (!("Notification" in window)) {
            return false;
        }

        if (this.permission === 'granted') {
            return true;
        }

        const permission = await Notification.requestPermission();
        this.permission = permission;
        
        if (permission === 'granted') {
            this.showNotification('Notifications Enabled', 'You will now receive monastery updates', 'success');
            return true;
        }
        
        return false;
    }

    /**
     * Show a notification
     * @param {string} title - Notification title
     * @param {string} message - Notification message
     * @param {string} type - Notification type (success, info, warning, error)
     */
    showNotification(title, message, type = 'info') {
        // Check permission
        if (this.permission !== 'granted') {
            // Fallback to browser alert
            this.showToast(title, message, type);
            return;
        }

        // Icon based on type
        const icons = {
            'success': 'https://cdn-icons-png.flaticon.com/512/190/190411.png',
            'info': 'https://cdn-icons-png.flaticon.com/512/189/189664.png',
            'warning': 'https://cdn-icons-png.flaticon.com/512/564/564619.png',
            'error': 'https://cdn-icons-png.flaticon.com/512/753/753345.png',
            'donation': 'https://cdn-icons-png.flaticon.com/512/1076/1076984.png',
            'appointment': 'https://cdn-icons-png.flaticon.com/512/2693/2693507.png'
        };

        const notification = new Notification(title, {
            body: message,
            icon: icons[type] || icons['info'],
            badge: icons['info'],
            tag: 'monastery-notification',
            requireInteraction: false,
            silent: false
        });

        // Auto close after 5 seconds
        setTimeout(() => notification.close(), 5000);

        // Click handler
        notification.onclick = function(event) {
            event.preventDefault();
            window.focus();
            notification.close();
        };

        // Also show toast
        this.showToast(title, message, type);
    }

    /**
     * Show toast notification (fallback)
     */
    showToast(title, message, type = 'info') {
        const colors = {
            'success': '#28a745',
            'info': '#17a2b8',
            'warning': '#ffc107',
            'error': '#dc3545',
            'donation': '#f57c00',
            'appointment': '#6f42c1'
        };

        const icons = {
            'success': 'bi-check-circle-fill',
            'info': 'bi-info-circle-fill',
            'warning': 'bi-exclamation-triangle-fill',
            'error': 'bi-x-circle-fill',
            'donation': 'bi-cash-coin',
            'appointment': 'bi-calendar-check'
        };

        // Create toast container if doesn't exist
        let container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.style.cssText = `
                position: fixed;
                top: 80px;
                right: 20px;
                    z-index: 999;
                max-width: 350px;
                    pointer-events: none;
            `;
            document.body.appendChild(container);
        }

        // Create toast element
        const toast = document.createElement('div');
        toast.style.cssText = `
            background: white;
            border-left: 4px solid ${colors[type] || colors['info']};
            border-radius: 8px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.2);
            padding: 15px 20px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            animation: slideInRight 0.3s ease-out;
            cursor: pointer;
            transition: transform 0.2s;
                pointer-events: auto;
        `;

        toast.innerHTML = `
            <i class="bi ${icons[type] || icons['info']}" style="font-size: 1.5rem; color: ${colors[type] || colors['info']}; margin-right: 15px;"></i>
            <div style="flex: 1;">
                <strong style="display: block; margin-bottom: 5px;">${title}</strong>
                <small style="color: #666;">${message}</small>
            </div>
            <i class="bi bi-x" style="font-size: 1.2rem; color: #999; margin-left: 10px;"></i>
        `;

        // Hover effect
        toast.onmouseover = () => toast.style.transform = 'translateX(-5px)';
        toast.onmouseout = () => toast.style.transform = 'translateX(0)';

        // Click to close
        toast.onclick = () => {
            toast.style.animation = 'slideOutRight 0.3s ease-out';
            setTimeout(() => toast.remove(), 300);
        };

        container.appendChild(toast);

        // Auto remove after 5 seconds
        setTimeout(() => {
            if (toast.parentNode) {
                toast.style.animation = 'slideOutRight 0.3s ease-out';
                setTimeout(() => toast.remove(), 300);
            }
        }, 5000);
    }

    /**
     * Notify about new donation
     */
    notifyDonation(donorName, amount) {
        this.showNotification(
            '💚 New Donation Received',
            `${donorName} donated Rs. ${amount.toLocaleString()}`,
            'donation'
        );
    }

    /**
     * Notify about new appointment
     */
    notifyAppointment(monkName, doctorName, date) {
        this.showNotification(
            '📅 New Appointment',
            `${monkName} with ${doctorName} on ${date}`,
            'appointment'
        );
    }

    /**
     * Notify about bill payment
     */
    notifyBillPayment(vendor, amount) {
        this.showNotification(
            '💸 Bill Paid',
            `Payment of Rs. ${amount.toLocaleString()} to ${vendor}`,
            'warning'
        );
    }

    /**
     * Check for new activities (polling)
     */
    async checkNewActivities() {
        try {
            const response = await fetch('api/check_notifications.php');
            const data = await response.json();

            if (data.newDonations > 0) {
                this.showNotification(
                    '💰 New Donations',
                    `${data.newDonations} new donation(s) received`,
                    'donation'
                );
            }

            if (data.newAppointments > 0) {
                this.showNotification(
                    '📅 New Appointments',
                    `${data.newAppointments} new appointment(s) scheduled`,
                    'appointment'
                );
            }
        } catch (error) {
            console.error('Failed to check notifications:', error);
        }
    }

    /**
     * Start polling for new activities
     */
    startPolling(intervalSeconds = 30) {
        // Check immediately
        this.checkNewActivities();

        // Then check every interval
        setInterval(() => {
            this.checkNewActivities();
        }, intervalSeconds * 1000);
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Initialize global notification system
const monasteryNotifications = new MonasteryNotifications();

// Enable notifications button
function enableNotifications() {
    monasteryNotifications.requestPermission().then(granted => {
        if (granted) {
            monasteryNotifications.showNotification(
                'Notifications Enabled! 🔔',
                'You will now receive updates about donations, appointments, and more',
                'success'
            );
            
            // Start polling for new activities
            monasteryNotifications.startPolling(30);
        }
    });
}
