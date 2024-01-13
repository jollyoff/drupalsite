document.addEventListener('DOMContentLoaded', function () {
  var mainItem = document.getElementById('mainItem');
  var popup = document.getElementById('popup');

  // Обработчик события при наведении на главный элемент
  mainItem.addEventListener('mouseover', function () {
    // Показываем всплывающее окно
    popup.style.display = 'block';
  });

  // Обработчик события при уходе курсора с главного элемента
  mainItem.addEventListener('mouseout', function () {
    // Скрываем всплывающее окно
    popup.style.display = 'none';
  });
});
