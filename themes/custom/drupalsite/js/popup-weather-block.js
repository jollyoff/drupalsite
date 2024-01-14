document.addEventListener('DOMContentLoaded', function () {
  var popup = document.getElementById('weather-block-city-form');

  // Проверка наличия элемента перед добавлением слушателя событий
  var mainItem = document.getElementById('block-weatherblock');

  if (mainItem) {
    mainItem.addEventListener('mouseover', function () {
      // ваш код обработчика события здесь
    });
  } else {
    console.error('Элемент с id "mainItem" не найден.');
  }


  // Обработчик события при уходе курсора с главного элемента
  mainItem.addEventListener('mouseout', function () {
    // Скрываем всплывающее окно
    popup.style.display = 'none';
  });
});
