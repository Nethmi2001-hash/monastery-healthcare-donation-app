(function () {
  function showToast(message, type = 'info', timeout = 2600) {
    const toast = document.createElement('div');
    toast.className = `sc-toast ${type}`;
    toast.textContent = message;
    document.body.appendChild(toast);

    setTimeout(() => {
      toast.style.opacity = '0';
      toast.style.transform = 'translateY(-8px)';
      setTimeout(() => toast.remove(), 220);
    }, timeout);
  }

  window.scToast = showToast;

  document.addEventListener('click', (event) => {
    const target = event.target.closest('[data-confirm]');
    if (!target) return;

    const message = target.getAttribute('data-confirm') || 'Are you sure?';
    if (!window.confirm(message)) {
      event.preventDefault();
      event.stopPropagation();
    }
  });

  const forms = document.querySelectorAll('form[data-loading]');
  forms.forEach((form) => {
    form.addEventListener('submit', () => {
      const submit = form.querySelector('[type="submit"]');
      if (!submit) return;
      submit.dataset.originalText = submit.innerHTML;
      submit.disabled = true;
      submit.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Processing...';
    });
  });
})();
