jQuery(document).ready(function($) {
  var mainItem = $('#block-weatherblock');
  var popup = $('#weather-block-city-form');

  // Обработчик события при наведении на главный элемент
  mainItem.on('mouseover', function () {
    // Показываем всплывающее окно
    popup.show();
  });

  // Обработчик события при уходе курсора с главного элемента
  mainItem.on('mouseout', function () {
    // Скрываем всплывающее окно
    popup.hide();
  });
});

;
(function($){
  // Response slideshow
  $(window).resize(function() {
    var div_height = $('.views_slideshow_cycle_slide').height();
    $('.views_slideshow_cycle_teaser_section').height(div_height);
  });
})(jQuery);
;
(function ($) {
  Drupal.behaviors.superLoginBehavior = {
    attach: function (context, settings) {

        var showMessages = drupalSettings.show_messages;

        if (!showMessages){
            $(".messages").prependTo("#user-login-form");
            $(".alert").prependTo("#user-login-form");
        }

          $('#edit-pass').keypress(function(e) {
              var s = String.fromCharCode( e.which );
              if ( s.toUpperCase() === s && s.toLowerCase() !== s && !e.shiftKey ) {
                $('#capslockdiv p').show();
              }
              else {
                $('#capslockdiv p').hide();
              }
          });
        }
  };
})(jQuery);


;
