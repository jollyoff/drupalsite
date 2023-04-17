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
    $form['weather_info'] = [
      '#type' => 'markup',
      '#prefix' => '<div id="weather-info">',
      '#suffix' => '</div>',
    ];
    $form['city'] = [
      '#type' => 'textfield',
      '#title' => $this->t('City'),
      '#required' => TRUE,
    ];
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Get weather'),
      '#ajax' => [
        'callback' => '::ajaxSubmitForm',
        'event' => 'click',
      ],
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $city = $form_state->getValue('city');
    if (!preg_match('/^[a-zA-Z\s]+$/', $city)) {
      $form_state->setErrorByName('city', $this->t('City name must contain only letters and spaces.'));
    }
  }

  /**
   * Ajax callback to update the weather information.
   */
  public function ajaxSubmitForm(array &$form, FormStateInterface $form_state) {
    $city = $form_state->getValue('city');
    $api_key = '8795cf1702a915bf4f6e1c1ca54fed35';
    $url = 'http://api.openweathermap.org/data/2.5/weather?q=' . urlencode($city) . '&appid=' . $api_key;
    $json = file_get_contents($url);
    $data = json_decode($json, true);
    $temperature = round($data['main']['temp'] - 273);
    $weather_info = $this->t('Температура в @city: @temperature°C', [
      '@city' => $city,
      '@temperature' => $temperature,
    ]);
    $response = new \Drupal\Core\Ajax\AjaxResponse();
    $response->addCommand(new \Drupal\Core\Ajax\HtmlCommand('#weather-info', $weather_info));
    return $response;
  }
  /**
  {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $form_state->setRedirectUrl(Url::fromRoute('<current>'));
  }
}



