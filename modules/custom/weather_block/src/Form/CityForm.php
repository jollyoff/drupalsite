<?php

namespace Drupal\weather_block\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\FormSubmitterInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Form\FormError;
use Drupal\Core\Form\FormErrorMessage;


/**
 * Implements a city form.
 */
class CityForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'weather_block_city_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $weather_data = $this->getWeatherData();

    $form['weather_info'] = [
      '#theme' => 'item_list',
      '#items' => $this->formatWeatherData($weather_data),
    ];

    return $form;
  }

  /**
   * Format weather data into an array of list items.
   */
  private function formatWeatherData($weather_data) {
    $formatted_data = [];
    foreach ($weather_data as $city => $temperature) {
      $formatted_data[] = $this->t('@city: @temperature°C', [
        '@city' => $city,
        '@temperature' => $temperature,
      ]);
    }
    return $formatted_data;
  }

  /**
  {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {

  }
  /**
   * private function for getting array with temperature
   */
  private function getWeatherData() {
    $cities = ['Вінниця', 'Дніпро', 'Донецьк', 'Житомир', 'Запоріжжя', 'Івано-Франківськ',
      'Київ', 'Кропивницький', 'Луганськ', 'Луцьк', 'Львів', 'Миколаїв', 'Одеса',
      'Полтава', 'Рівне', 'Суми', 'Тернопіль', 'Ужгород', 'Харків', 'Херсон',
      'Хмельницький', 'Черкаси', 'Чернівці', 'Чернігів'];
    $api_key = '8795cf1702a915bf4f6e1c1ca54fed35';

    $weather_data = [];

    foreach ($cities as $city) {
      $url = 'http://api.openweathermap.org/data/2.5/weather?q=' . $city . '&appid=' . $api_key;
      $json = file_get_contents($url);
      $data = json_decode($json, true);

      if (isset($data['main']['temp'])) {
        $temperature = round($data['main']['temp'] - 273);
        $weather_data += [$city => $temperature];
      }
    }

    return $weather_data;
  }
}



