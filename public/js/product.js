(function () {
  'use strict';

  // Все потенциально разрушительные формы получают единый браузерный вопрос.
  document.querySelectorAll('[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm') || 'Продолжить?')) {
        event.preventDefault();
      }
    });
  });

  document.querySelectorAll('[data-toggle-panel]').forEach(function (button) {
    button.addEventListener('click', function () {
      var panel = document.querySelector(button.getAttribute('data-toggle-panel'));
      if (panel) {
        panel.hidden = false;
        panel.classList.toggle('show');
        button.setAttribute('aria-expanded', panel.classList.contains('show') ? 'true' : 'false');
      }
    });
  });

  function rubles(value) {
    return new Intl.NumberFormat('ru-RU', { maximumFractionDigits: 0 }).format(value) + ' ₽';
  }

  // Предварительный итог помогает гостю, но окончательная сумма всегда считается PHP-сервисом.
  function updateCheckout() {
    var accommodationNode = document.querySelector('[data-accommodation-total]');
    var servicesNode = document.querySelector('[data-services-total]');
    var grandNode = document.querySelector('[data-grand-total]');
    if (!accommodationNode || !servicesNode || !grandNode) return;

    var servicesTotal = 0;
    document.querySelectorAll('[data-price]:not(:disabled)').forEach(function (input) {
      servicesTotal += Number(input.dataset.price || 0) * Number(input.dataset.multiplier || 1) * Number(input.value || 0);
    });
    var accommodation = Number(accommodationNode.dataset.accommodationTotal || 0);
    servicesNode.textContent = rubles(servicesTotal);
    grandNode.textContent = rubles(accommodation + servicesTotal);
  }

  document.querySelectorAll('[data-service-toggle]').forEach(function (checkbox) {
    var input = document.getElementById(checkbox.dataset.serviceToggle);
    if (!input) return;
    checkbox.addEventListener('change', function () {
      input.disabled = !checkbox.checked;
      input.value = checkbox.checked ? Math.max(1, Number(input.value || 0)) : 0;
      updateCheckout();
    });
    input.addEventListener('input', updateCheckout);
  });

  // Flash-сообщение исчезает после чтения и не занимает место до следующего перехода.
  window.setTimeout(function () {
    document.querySelectorAll('.site-flash').forEach(function (message) {
      message.style.opacity = '0';
      window.setTimeout(function () { message.remove(); }, 350);
    });
  }, 5000);
}());
