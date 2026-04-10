/**
 * Dark Mode Theme Toggle
 * Premium first-class feature for theme switching
 */

(function() {
    'use strict';

    // Initialize theme on page load
    const initTheme = () => {
        const savedTheme = localStorage.getItem('theme') || 'light';
        applyTheme(savedTheme);
        updateToggleButton(savedTheme);
    };

    // Apply theme to document
    const applyTheme = (theme) => {
        if (theme === 'dark') {
            document.documentElement.setAttribute('data-theme', 'dark');
            document.body.classList.add('dark-mode');
        } else {
            document.documentElement.removeAttribute('data-theme');
            document.body.classList.remove('dark-mode');
        }
    };

    // Update toggle button icon
    const updateToggleButton = (theme) => {
        const toggleBtn = document.getElementById('theme-toggle');
        const icon = toggleBtn?.querySelector('i');
        
        if (icon) {
            if (theme === 'dark') {
                icon.className = 'bi bi-sun-fill';
                toggleBtn.setAttribute('title', 'Switch to Light Mode');
            } else {
                icon.className = 'bi bi-moon-fill';
                toggleBtn.setAttribute('title', 'Switch to Dark Mode');
            }
        }
    };

    // Toggle theme
    const toggleTheme = () => {
        const currentTheme = localStorage.getItem('theme') || 'light';
        const newTheme = currentTheme === 'light' ? 'dark' : 'light';
        
        localStorage.setItem('theme', newTheme);
        applyTheme(newTheme);
        updateToggleButton(newTheme);
        
        // Add animation effect
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
                toggleBtn.style.pointerEvents = 'none';
                toggleBtn.style.transform = 'rotate(360deg)';
            setTimeout(() => {
                toggleBtn.style.transform = 'rotate(0deg)';
                    toggleBtn.style.pointerEvents = 'auto';
            }, 300);
        }
    };

    // Setup event listeners
    const setupToggleButton = () => {
        const toggleBtn = document.getElementById('theme-toggle');
        if (toggleBtn) {
            toggleBtn.addEventListener('click', toggleTheme);
        }
    };

    // Initialize on DOM ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => {
            initTheme();
            setupToggleButton();
        });
    } else {
        initTheme();
        setupToggleButton();
    }

})();
