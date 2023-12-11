<?php

namespace Drupal\api\PathProcessor;

use Drupal\api\Formatter;
use Drupal\Core\PathProcessor\InboundPathProcessorInterface;
use Symfony\Component\HttpFoundation\Request;

/**
 * Path processor for legacy files paths.
 */
class PathProcessorLegacyFiles implements InboundPathProcessorInterface {

  /**
   * Process any inbound URL and if it fits the pattern, transform it.
   *
   * @param string $path
   *   Current path being checked.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return string
   *   Transformed path so the router can consume it without issues.
   */
  public function processInbound($path, Request $request) {
    if (strpos($path, '/api/file/') === 0) {
      // The rest of the URL can be an unlimited number of arguments, so convert
      // them into one argument that can be consumed by the router patterns.
      $arguments = preg_replace('|^\/api\/file\/|', '', $path);
      $arguments = Formatter::getReplacementName($arguments);
      return "/api/file/$arguments";
    }

    return $path;
  }

}
