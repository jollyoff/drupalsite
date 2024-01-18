(function($){
  // Response slideshow
  $(window).resize(function() {
    var div_height = $('.views_slideshow_cycle_slide').height();
    $('.views_slideshow_cycle_teaser_section').height(div_height);
  });
})(jQuery);
