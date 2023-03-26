<?php

namespace Drupal\api\Breadcrumb;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\Project;
use Drupal\api\Formatter;
use Drupal\api\Interfaces\ProjectInterface;
use Drupal\Core\Breadcrumb\Breadcrumb;
use Drupal\Core\Breadcrumb\BreadcrumbBuilderInterface;
use Drupal\Core\Link;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Generate breadcrumbs specific to the API module and paths.
 */
class ApiBreadcrumbBuilder implements BreadcrumbBuilderInterface {
  use StringTranslationTrait;

  /**
   * {@inheritdoc}
   */
  public function applies(RouteMatchInterface $route_match) {
    $routes = [
      'api.branch_default_route',
      'api.branch_explicit_route',
      'api.file_references_route',
      'api.namespace_route',
      'api.detail_page_type_route',
      'api.detail_page_type_subtype_route',
      'entity.project.canonical',
    ];

    return in_array($route_match->getRouteName(), $routes);
  }

  /**
   * {@inheritdoc}
   */
  public function build(RouteMatchInterface $route_match) {
    $breadcrumbs = new Breadcrumb();
    $links = [];

    // Project and branch links.
    $parameters = $route_match->getParameters()->all();
    $project_slug = $parameters['project'];
    // Canonical route has the full object.
    if ($project_slug instanceof ProjectInterface) {
      $project = $project_slug;
    }
    else {
      $project = Project::getBySlug($project_slug);
    }
    $branch_slug = $parameters['branch'] ?? $parameters['argument'] ?? '';
    if ($branch_slug) {
      $branch = Branch::getBySlug($branch_slug, $project);
      if ($branch && $route_match->getRouteName() !== 'api.branch_default_route') {
        $links[] = Link::createFromRoute(
          $project->getTitle() . ' ' . $branch->getSlug(),
          'api.branch_default_route',
          [
            'project' => $project->getSlug(),
            'argument' => $branch->getSlug(),
          ]
        );
      }

      // For more complex routes just offer a link to the file.
      if (
        $branch &&
        in_array($route_match->getRouteName(), [
          'api.file_references_route',
          'api.detail_page_type_route',
          'api.detail_page_type_subtype_route',
        ])
      ) {
        $filename = $parameters['filename'];
        $niceFilename = Formatter::getReplacementName($filename, 'file', TRUE);
        if (strpos($niceFilename, '/') !== FALSE) {
          $parts = explode('/', $niceFilename);
          $niceFilename = array_pop($parts);
        }
        $links[] = Link::createFromRoute(
          $niceFilename,
          'api.branch_explicit_route',
          [
            'project' => $project->getSlug(),
            'branch' => $branch->getSlug(),
            'argument' => $filename,
          ]
        );
      }
    }

    $breadcrumbs->setLinks($links);
    $breadcrumbs->addCacheContexts(['route', 'url.path']);

    return $breadcrumbs;
  }

}
