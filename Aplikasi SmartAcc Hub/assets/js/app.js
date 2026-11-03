/**
 * SmartAcc Hub - Main JavaScript
 * Handles Theme Switching (Dark & Light), Modals, Dynamic Calculators, and UI Events
 */

(function() {
    'use strict';

    // Theme Switcher Logic
    const THEME_KEY = 'smartacc_theme';

    function initTheme() {
        const savedTheme = localStorage.getItem(THEME_KEY) || 'dark';
        applyTheme(savedTheme);
    }

    function applyTheme(theme) {
        document.documentElement.setAttribute('data-theme', theme);
        localStorage.setItem(THEME_KEY, theme);
        // Also save to cookie for PHP server-side recognition
        document.cookie = `smartacc_theme=${theme};path=/;max-age=31536000`;

        const themeBtn = document.getElementById('themeToggleBtn');
        if (themeBtn) {
            const icon = themeBtn.querySelector('i');
            const label = themeBtn.querySelector('.theme-label');
            if (theme === 'dark') {
                if (icon) icon.className = 'fas fa-sun text-warning';
                if (label) label.textContent = 'Mode Terang';
            } else {
                if (icon) icon.className = 'fas fa-moon text-indigo';
                if (label) label.textContent = 'Mode Gelap';
            }
        }

        // Trigger custom event so Chart.js can update colors
        window.dispatchEvent(new CustomEvent('themeChanged', { detail: { theme } }));
    }

    function toggleTheme() {
        const currentTheme = document.documentElement.getAttribute('data-theme') || 'dark';
        const nextTheme = currentTheme === 'dark' ? 'light' : 'dark';
        applyTheme(nextTheme);
    }

    // Modal Manager
    window.openModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }
    };

    window.closeModal = function(modalId) {
        const modal = document.getElementById(modalId);
        if (modal) {
            modal.classList.remove('active');
            document.body.style.overflow = '';
        }
    };

    // Close modal on click outside container
    document.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal-overlay')) {
            e.target.classList.remove('active');
            document.body.style.overflow = '';
        }
    });

    // Mobile Sidebar Toggle
    window.toggleSidebar = function() {
        const sidebar = document.querySelector('.app-sidebar');
        if (sidebar) {
            sidebar.classList.toggle('show');
        }
    };

    // Initialize on DOM ready
    document.addEventListener('DOMContentLoaded', function() {
        initTheme();

        const themeToggleBtn = document.getElementById('themeToggleBtn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }

        // Table Quick Filter
        const searchInput = document.getElementById('tableSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', function() {
                const query = this.value.toLowerCase().trim();
                const table = document.querySelector('.custom-table tbody');
                if (table) {
                    const rows = table.querySelectorAll('tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(query) ? '' : 'none';
                    });
                }
            });
        }

        // Auto close alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                alert.style.transition = 'opacity 0.4s ease';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 400);
            });
        }, 5000);
    });

})();
