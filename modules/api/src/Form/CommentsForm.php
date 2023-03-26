<?php

namespace Drupal\api\Form;

use Drupal\api\Entity\DocBlock;
use Drupal\comment\CommentInterface;
use Drupal\comment\CommentManagerInterface;
use Drupal\comment\Plugin\Field\FieldType\CommentItemInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Session\AnonymousUserSession;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Comment settings for api.
 */
class CommentsForm extends ConfigFormBase {

  /**
   * Drupal\Core\Extension\ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * Constructs the object.
   *
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   The module handler service.
   */
  public function __construct(ModuleHandlerInterface $module_handler) {
    $this->moduleHandler = $module_handler;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('module_handler')
    );
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'api.comments',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_comments_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    if ($this->moduleHandler->moduleExists('comment')) {
      $this->commentSettings($form);
      $form['actions']['apply_to_all'] = [
        '#type' => 'submit',
        '#value' => 'Save and apply to all',
        '#submit' => ['::applyToAll'],
        '#weight' => 10,
      ];

      $performance_link = Link::createFromRoute(
        $this->t('clear the cache'),
        'system.performance_settings',
        [],
        [
          'attributes' => [
            'target' => '_blank',
          ],
        ]
      )->toString();
      $form['warning'] = [
        '#markup' => '<p class="form-item__description">' .
        $this->t('<em>"Save configuration"</em>: default status will apply to new DocBlocks.') . '<br />' .
        $this->t('<em>"Save and apply to all"</em>: default status will be applied to all existing DocBlocks.') . '<br /><br />' .
        '<strong>Global settings will only be applied after you ' . $performance_link . '.</strong>' .
        '</p>',
      ];
    }
    else {
      $form['intro'] = [
        '#markup' => $this->t('Comments module is disabled.'),
      ];
    }

    $form['#theme'] = 'system_config_form';
    return parent::buildForm($form, $form_state);
  }

  /**
   * Defines the settings for comments.
   *
   * The code is taken from the comment module and adapted for this one.
   *
   * @param array $form
   *   Form object to modify.
   *
   * @see \Drupal\comment\Plugin\Field\FieldType\CommentItem
   * @see \Drupal\comment\Plugin\Field\FieldWidget\CommentWidget
   */
  protected function commentSettings(array &$form) {
    $config = $this->config('api.comments');

    $form['status'] = [
      '#type' => 'radios',
      '#title' => $this->t('Default status'),
      '#default_value' => $config->get('status') ?? CommentItemInterface::OPEN,
      '#options' => [
        CommentItemInterface::OPEN => $this->t('Open'),
        CommentItemInterface::CLOSED => $this->t('Closed'),
        CommentItemInterface::HIDDEN => $this->t('Hidden'),
      ],
      CommentItemInterface::OPEN => [
        '#description' => $this->t('Users with the "Post comments" permission can post comments.'),
      ],
      CommentItemInterface::CLOSED => [
        '#description' => $this->t('Users cannot post comments, but existing comments will be displayed.'),
      ],
      CommentItemInterface::HIDDEN => [
        '#description' => $this->t('Comments are hidden from view.'),
      ],
    ];

    $form['divider'] = [
      '#markup' => '<hr /><p><strong>' . $this->t('Global settings') . '</strong></p>',
    ];
    $form['default_mode'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Threading'),
      '#default_value' => $config->get('default_mode') ?? CommentManagerInterface::COMMENT_MODE_THREADED,
      '#description' => $this->t('Show comment replies in a threaded list.'),
    ];
    $form['per_page'] = [
      '#type' => 'number',
      '#title' => $this->t('Comments per page'),
      '#default_value' => $config->get('per_page') ?? 50,
      '#required' => TRUE,
      '#min' => 1,
      '#max' => 1000,
    ];
    $form['anonymous'] = [
      '#type' => 'select',
      '#title' => $this->t('Anonymous commenting'),
      '#default_value' => $config->get('anonymous') ?? CommentInterface::ANONYMOUS_MAYNOT_CONTACT,
      '#options' => [
        CommentInterface::ANONYMOUS_MAYNOT_CONTACT => $this->t('Anonymous posters may not enter their contact information'),
        CommentInterface::ANONYMOUS_MAY_CONTACT => $this->t('Anonymous posters may leave their contact information'),
        CommentInterface::ANONYMOUS_MUST_CONTACT => $this->t('Anonymous posters must leave their contact information'),
      ],
      '#access' => (new AnonymousUserSession())->hasPermission('post comments'),
    ];
    $form['form_location'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show reply form on the same page as comments'),
      '#default_value' => $config->get('form_location') ?? CommentItemInterface::FORM_BELOW,
    ];
    $form['preview'] = [
      '#type' => 'radios',
      '#title' => $this->t('Preview comment'),
      '#default_value' => $config->get('preview') ?? DRUPAL_DISABLED,
      '#options' => [
        DRUPAL_DISABLED => $this->t('Disabled'),
        DRUPAL_OPTIONAL => $this->t('Optional'),
        DRUPAL_REQUIRED => $this->t('Required'),
      ],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->saveValues($form, $form_state);
  }

  /**
   * Save settings and apply them to all DocBlocks.
   *
   * @param array $form
   *   Form object.
   * @param \Drupal\Core\Form\FormStateInterface $form_state
   *   Form state object.
   */
  public function applyToAll(array &$form, FormStateInterface $form_state) {
    self::submitForm($form, $form_state);
    DocBlock::applyCommentsStatusToAll();
  }

  /**
   * Save form submitted values.
   */
  public function saveValues(array &$form, FormStateInterface $form_state) {
    $status = $form_state->getValue('status', CommentItemInterface::CLOSED);
    $default_mode = $form_state->getValue('default_mode');
    $anonymous = $form_state->getValue('anonymous');
    $per_page = $form_state->getValue('per_page', 50);
    $form_location = $form_state->getValue('form_location');
    $preview = $form_state->getValue('preview', DRUPAL_DISABLED);

    $this->config('api.comments')
      ->set('status', $status)
      ->set('default_mode', $default_mode)
      ->set('anonymous', $anonymous)
      ->set('per_page', $per_page)
      ->set('form_location', $form_location)
      ->set('preview', $preview)
      ->save();
  }

}
