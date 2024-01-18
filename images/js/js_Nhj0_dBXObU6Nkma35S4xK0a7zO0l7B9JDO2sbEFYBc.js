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
