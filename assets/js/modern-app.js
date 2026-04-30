/**
 * Modern App Interactions
 * Sidebar toggle, dark mode, toasts, confirmations
 */
(function() {
  'use strict';

  /* ==============================
     SIDEBAR TOGGLE
     ============================== */
  function initSidebar() {
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.querySelector('.sidebar-overlay');
    const toggleBtn = document.querySelector('.topbar-toggle');

    if (!sidebar) return;

    function openSidebar() {
      sidebar.classList.add('open');
      if (overlay) overlay.classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeSidebar() {
      sidebar.classList.remove('open');
      if (overlay) overlay.classList.remove('active');
      document.body.style.overflow = '';
    }

    if (toggleBtn) {
      toggleBtn.addEventListener('click', function() {
        if (sidebar.classList.contains('open')) {
          closeSidebar();
        } else {
          openSidebar();
        }
      });
    }

    if (overlay) {
      overlay.addEventListener('click', closeSidebar);
    }

    // Close on ESC key
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') closeSidebar();
    });

    // Close sidebar on resize to desktop
    window.addEventListener('resize', function() {
      if (window.innerWidth > 1024) {
        closeSidebar();
      }
    });
  }

  /* ==============================
     DARK MODE TOGGLE
     ============================== */
  function initDarkMode() {
    const toggle = document.getElementById('theme-toggle-modern');
    const html = document.documentElement;
    
    // Load saved theme
    const savedTheme = localStorage.getItem('theme') || 'light';
    html.setAttribute('data-theme', savedTheme);
    updateToggleIcon(savedTheme);

    if (toggle) {
      toggle.addEventListener('click', function() {
        const current = html.getAttribute('data-theme');
        const next = current === 'dark' ? 'light' : 'dark';
        html.setAttribute('data-theme', next);
        localStorage.setItem('theme', next);
        updateToggleIcon(next);
        
        // Also set for old theme compatibility
        if (next === 'dark') {
          document.body.classList.add('dark-mode');
        } else {
          document.body.classList.remove('dark-mode');
        }
      });
    }

    function updateToggleIcon(theme) {
      if (!toggle) return;
      const icon = toggle.querySelector('i');
      if (icon) {
        icon.className = theme === 'dark' ? 'bi bi-sun-fill' : 'bi bi-moon-fill';
      }
    }
  }

  /* ==============================
     TOAST NOTIFICATIONS
     ============================== */
  window.showToast = function(message, type = 'info', duration = 4000) {
    let container = document.querySelector('.toast-container-modern');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container-modern';
      document.body.appendChild(container);
    }

    const icons = {
      success: 'bi-check-circle-fill',
      danger: 'bi-x-circle-fill',
      warning: 'bi-exclamation-triangle-fill',
      info: 'bi-info-circle-fill'
    };

    const toast = document.createElement('div');
    toast.className = 'toast-modern ' + type;
    toast.innerHTML = `
      <i class="bi ${icons[type] || icons.info}" style="font-size:18px;color:var(--${type === 'danger' ? 'danger' : type})"></i>
      <span style="flex:1">${message}</span>
      <button onclick="this.parentElement.remove()" style="background:none;border:none;color:var(--text-secondary);cursor:pointer;font-size:16px;padding:0 0 0 8px">
        <i class="bi bi-x"></i>
      </button>
    `;

    container.appendChild(toast);

    if (duration > 0) {
      setTimeout(function() {
        toast.style.opacity = '0';
        toast.style.transform = 'translateX(100%)';
        toast.style.transition = 'all 0.3s ease';
        setTimeout(function() { toast.remove(); }, 300);
      }, duration);
    }
  };

  // Expose scToast for backward compatibility
  window.scToast = function(message, type) {
    const typeMap = { success: 'success', error: 'danger', warning: 'warning', info: 'info' };
    window.showToast(message, typeMap[type] || type || 'info');
  };

  /* ==============================
     CONFIRM DIALOGS
     ============================== */
  function initConfirmDialogs() {
    document.addEventListener('click', function(e) {
      const el = e.target.closest('[data-confirm]');
      if (el) {
        e.preventDefault();
        const msg = el.getAttribute('data-confirm') || 'Are you sure?';
        if (confirm(msg)) {
          if (el.tagName === 'A') {
            window.location.href = el.href;
          } else if (el.form) {
            el.form.submit();
          }
        }
      }
    });
  }

  /* ==============================
     FORM LOADING STATES
     ============================== */
  function initFormLoading() {
    document.addEventListener('submit', function(e) {
      const form = e.target;
      const btn = form.querySelector('[data-loading]');
      if (btn) {
        const loadingText = btn.getAttribute('data-loading');
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>' + loadingText;
      }
    });
  }

  /* ==============================
     SMOOTH TABLE ROW HOVER
     ============================== */
  function initTableEnhancements() {
    // Add search filtering to tables
    document.querySelectorAll('[data-table-search]').forEach(function(input) {
      const tableId = input.getAttribute('data-table-search');
      const table = document.getElementById(tableId);
      if (!table) return;

      input.addEventListener('input', function() {
        const filter = this.value.toLowerCase();
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(function(row) {
          const text = row.textContent.toLowerCase();
          row.style.display = text.includes(filter) ? '' : 'none';
        });
      });
    });
  }

  /* ==============================
     INIT ALL
     ============================== */
  document.addEventListener('DOMContentLoaded', function() {
    initSidebar();
    initDarkMode();
    initConfirmDialogs();
    initFormLoading();
    initTableEnhancements();
  });
})();
