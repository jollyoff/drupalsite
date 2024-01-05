document.addEventListener('DOMContentLoaded', function() {
  var sliderContainer = document.querySelector('.main-article-slider');
  var paginationButtons = document.querySelectorAll('.views_block__golovne_block_1 .pagination-button');

  var slides = sliderContainer.querySelectorAll('.slide');

  var currentIndex = 0;
  paginationButtons.forEach(function(button, index) {
    button.addEventListener('click', function() {
      slides[currentIndex].classList.remove('active');

      currentIndex = index;

      slides[currentIndex].classList.add('active');
    });
  });
});

