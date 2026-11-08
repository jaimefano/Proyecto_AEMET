// JavaScript propio de la interfaz. Las comprobaciones importantes se repiten siempre en el servidor.
document.addEventListener('DOMContentLoaded', function () {
  // Formularios con data-confirmar: piden confirmación antes de enviarse (bajas y eliminaciones).
  document.querySelectorAll('form[data-confirmar]').forEach(function (form) {
    form.addEventListener('submit', function (evento) {
      if (!window.confirm(form.dataset.confirmar)) {
        evento.preventDefault();
      }
    });
  });

  // Avisa en el propio formulario si las dos contraseñas no coinciden.
  document.querySelectorAll('form').forEach(function (form) {
    var principal = form.querySelector('[data-password]');
    var repetida = form.querySelector('[data-password-repetir]');
    if (!principal || !repetida) {
      return;
    }
    var comprobar = function () {
      repetida.setCustomValidity(principal.value === repetida.value ? '' : 'Las contraseñas no coinciden.');
    };
    principal.addEventListener('input', comprobar);
    repetida.addEventListener('input', comprobar);
  });
});
