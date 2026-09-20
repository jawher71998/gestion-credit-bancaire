document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.toggle-password').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const input = document.getElementById(btn.dataset.target);
      if (!input) return;
      const vaDevenirVisible = input.type === 'password';
      input.type = vaDevenirVisible ? 'text' : 'password';
      btn.classList.toggle('is-showing', vaDevenirVisible);
      btn.setAttribute('aria-label', vaDevenirVisible ? 'Masquer le mot de passe' : 'Afficher le mot de passe');
    });
  });
});
