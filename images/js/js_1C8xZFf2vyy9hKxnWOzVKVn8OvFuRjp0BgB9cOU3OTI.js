(function ($) {
  Drupal.behaviors.customLoginForm = {
    attach: function (context, settings) {
      // Ваш код для открытия всплывающего окна.
      $('#user-login-form').once('user-login-form').click(function () {
        // Ваш код для открытия всплывающего окна.
        // Например, использование CSS класса для отображения блока.
        $('.user-login-form').fadeIn();
      });

      // Закрытие всплывающего окна при клике на крестик или другую область.
      $('.close-button, .overlay').click(function () {
        // Ваш код для закрытия всплывающего окна.
        // Например, использование CSS класса для скрытия блока.
        $('.user-login-form').fadeOut();
      });
    }
  };
})(jQuery);
;
