<?php

/**
 * @file
 * Theme-related functions for the API module.
 */

use Drupal\api\Entity\DocBlock\DocNamespace;
use Drupal\Core\Link;
use Drupal\Core\Template\Attribute;
use Drupal\api\Formatter;
use Drupal\Core\Url;

/**
 * Prepares variables for DL lists templates.
 *
 * Default template: views-view-dl-list.html.twig.
 *
 * @param array $variables
 *   An associative array containing template's variables.
 */
function template_preprocess_views_view_dl_list(array &$variables) {
  $handler = $variables['view']->style_plugin;

  // Fetch classes from handler options.
  if ($handler->options['class']) {
    $class = explode(' ', $handler->options['class']);
    $class = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', $class);

    // Initialize a new attribute class for $class.
    $variables['list']['attributes'] = new Attribute(['class' => $class]);
  }

  // Fetch wrapper classes from handler options.
  if ($handler->options['wrapper_class']) {
    $wrapper_class = explode(' ', $handler->options['wrapper_class']);
    $variables['attributes']['class'] = array_map('\Drupal\Component\Utility\Html::cleanCssIdentifier', $wrapper_class);
  }

  $variables['list']['type'] = $handler->options['type'];

  template_preprocess_views_view_unformatted($variables);
}

/**
 * Prepares variables for project templates.
 *
 * Default template: project.html.twig.
 *
 * @param array $variables
 *   An associative array containing:
 *   - elements: An associative array containing the project information and any
 *     fields attached to the entity.
 *   - attributes: HTML attributes for the containing element.
 */
function template_preprocess_project(array &$variables) {
  $project = $variables['elements']['#project'] ?? NULL;
  if ($project) {
    /** @var \Drupal\api\Entity\Project $project */
    $variables += Formatter::prepareBranchVariables($project->getDefaultBranch(TRUE));
  }
}

/**
 * Implements hook_preprocess_api_function_page().
 */
function api_preprocess_api_function_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_constant_page().
 */
function api_preprocess_api_constant_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_global_page().
 */
function api_preprocess_api_global_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_property_page().
 */
function api_preprocess_api_property_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_class_page().
 */
function api_preprocess_api_class_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_service_page().
 */
function api_preprocess_api_service_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_file_page().
 */
function api_preprocess_api_file_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Implements hook_preprocess_api_group_page().
 */
function api_preprocess_api_group_page(&$variables) {
  Formatter::prepareObjectPage($variables);
}

/**
 * Preprocesses 'api_defined' theme call.
 *
 * Sets up variables for the file link and summary line.
 */
function api_preprocess_api_defined(&$variables) {
  /** @var \Drupal\api\Interfaces\DocBlockInterface $object */
  $object = $variables['object'];
  $branch = $variables['branch'];
  $variables['file_link'] = Formatter::linkFile($object);

  $variables['file_summary'] = '';
  if ($object) {
    $variables['file_summary'] = Formatter::linkDocumentation($object->getSummary(), $branch, $object->id(), NULL, FALSE, FALSE, FALSE);
  }
}

/**
 * Preprocesses 'api_class_section' theme call.
 *
 * Sets up variables for the class link and summary line.
 */
function api_preprocess_api_class_section(&$variables) {
  /** @var \Drupal\api\Interfaces\DocBlockInterface $class */
  $class = $variables['class'];
  $branch = $variables['branch'];

  $variables['class_link'] = Link::fromTextAndUrl($class->getTitle(), Url::fromUri(Formatter::objectUrl($class)))->toString();
  $variables['class_info'] = Formatter::linkDocumentation($class->getSummary(), $branch, $class->id(), ($class->getClass() ? $class->getClass()->id() : NULL), FALSE, FALSE, $class->isDrupal());
}

/**
 * Preprocesses a namespace page.
 */
function api_preprocess_api_namespace_page(&$variables) {
  $namespace = $variables['name'];
  $branch = $variables['branch'];

  $count = 0;
  $alternatives = [
    '#prefix' => '<ol class="api-alternatives">',
    '#suffix' => '</ol>',
  ];
  // Matches by name on any branch.
  $namespace_ids = DocNamespace::getByClassName($namespace);
  if ($namespace_ids) {
    $namespaces = DocNamespace::loadMultiple($namespace_ids);
    foreach ($namespaces as $alternative) {
      $this_branch = $alternative->getDocBlock()->getBranch();
      if ($this_branch->id() != $branch->id()) {
        $label = $this_branch->getSlug() . ' ' . $namespace;
        $alternatives[$this_branch->getProject()->getSlug()][$this_branch->getSlug()] = [
          '#prefix' => '<li>',
          '#markup' => Link::fromTextAndUrl($label, Url::fromUri(Formatter::namespaceUrl($this_branch, $namespace)))->toString(),
          '#weight' => $this_branch->getWeight(),
          '#suffix' => '</li>',
        ];
        $count++;
      }
    }
  }

  if ($count) {
    $variables['alternatives'] = [
      '#prefix' => '<details class="api-alternatives"><summary>' . t('Same name in other branches') . '</summary>',
      [$alternatives],
      '#suffix' => '</details>',
    ];
  }
}
