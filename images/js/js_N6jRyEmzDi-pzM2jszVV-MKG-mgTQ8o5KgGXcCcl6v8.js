(function ($, Drupal, window, document, undefined) {
  Drupal.behaviors.customLoginForm = {
    attach: function (context, settings) {
      var triggerElement = '#user-login-form'; // Идентификатор элемента, который вызывает открытие всплывающего окна
      var popupElement = '.user-login-form'; // Селектор всплывающего окна
      var closeButton = '.close-button'; // Селектор кнопки закрытия всплывающего окна
      var overlayElement = '.overlay'; // Селектор оверлея

      // Открытие всплывающего окна при клике на определенный элемент.
      $(triggerElement, context).once('custom-login-form').click(function () {
        $(popupElement).fadeIn();
      });

      // Закрытие всплывающего окна при клике на крестик, оверлей или другую область.
      $(closeButton + ', ' + overlayElement, context).click(function () {
        $(popupElement).fadeOut();
      });
    }
  };
})(jQuery, Drupal, window, document);
;

;
