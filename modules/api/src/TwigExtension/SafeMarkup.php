<?php

namespace Drupal\api\TwigExtension;

use Drupal\Component\Utility\Xss;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * SafeMarkup twig extension to filter out html coming from the database.
 *
 * Filter '|raw' is not recommended so we at least do some filtering to prevent
 * XSS attacks.
 */
class SafeMarkup extends AbstractExtension {

  /**
   * {@inheritdoc}
   */
  public function getFilters() {
    return [new TwigFilter('safe_markup', [$this, 'filterMarkup'], ['is_safe' => ['html']])];
  }

  /**
   * {@inheritdoc}
   */
  public function getName() {
    return 'api.twig_extension.safe_markup';
  }

  /**
   * Filters the string through the Xss filter.
   *
   * @param string $string
   *   String to check.
   *
   * @return string
   *   Filtered string.
   */
  public static function filterMarkup($string) {
    return Xss::filterAdmin($string);
  }

}
