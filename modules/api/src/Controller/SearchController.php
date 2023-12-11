<?php

namespace Drupal\api\Controller;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Form\SearchForm;
use Drupal\api\Formatter;
use Drupal\api\Traits\RouteElementsTrait;
use Drupal\api\Utilities;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Path\PathValidatorInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Search pages.
 *
 * @package Drupal\api\Controller
 */
class SearchController extends ControllerBase {

  use RouteElementsTrait;

  /**
   * Utilities service.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * The path validator.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Construct method.
   *
   * @param \Drupal\api\Utilities $utilities
   *   Utilities service.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The path validator.
   */
  public function __construct(Utilities $utilities, PathValidatorInterface $path_validator) {
    $this->utilities = $utilities;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('api.utilities'),
      $container->get('path.validator')
    );
  }

  /**
   * If this is actually a not found exception, get the term from the URI.
   *
   * @param string $term
   *   Original term passed to the route, most likely empty.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return string
   *   Original term or URI.
   */
  protected function extractTermIfNotFoundRequest($term, Request $request) {
    if (empty($term) && !empty($request->attributes->get('exception'))) {
      if ($request->attributes->get('exception') instanceof NotFoundHttpException) {
        // Let the search decide the destination, so remove the param.
        $request->query->remove('destination');
        // Term will be the URI now.
        $term = trim($request->getRequestUri(), '/');
      }
    }

    return $term;
  }

  /**
   * Global search route.
   *
   * @param string $term
   *   Search term.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\Response
   *   Response object.
   */
  public function globalSearchRedirect($term, Request $request) {
    // In case this route is hooked up to the 404 handler.
    $term = $this->extractTermIfNotFoundRequest($term, $request);

    $term = Xss::filter($term);
    if (empty($term)) {
      return $this->redirect('api.search.default');
    }

    // Try exact match.
    $matches = DocBlock::searchByTitle($term, NULL, 1, TRUE);
    if (empty($matches)) {
      // Try partial match.
      $matches = DocBlock::searchByTitle($term, NULL, 1);
    }

    if (!empty($matches)) {
      $match_id = array_shift($matches);
      $match = DocBlock::load($match_id);

      return $this->redirect('api.search.project.branch.term', [
        'branch' => $match->getBranch()->getSlug(),
        'project' => $match->getBranch()->getProject()->getSlug(),
        'term' => $term,
      ]);
    }

    // If none found then redirect to default branch search page.
    $this->messenger()->addMessage($this->t('Sorry, %term cannot be found.', [
      '%term' => $term,
    ]));
    return $this->defaultsRedirect($request);
  }

  /**
   * Endpoint with no project or branch, so just redirect to defaults.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the right page.
   */
  public function defaultsRedirect(Request $request) {
    $branch = $this->utilities->getDefaultBranchProject();
    if (!$branch) {
      $this->messenger()->addMessage($this->t('Default project and branch is not set.'));
      return $this->redirect('api.projects');
    }
    $project = $branch->getProject();
    return $this->redirect('api.search.project.branch', [
      'branch' => $branch->getSlug(),
      'project' => $project->getSlug(),
    ]);
  }

  /**
   * Endpoint with branch and no project, so just redirect to the right route.
   *
   * @param string $branch
   *   Branch slug.
   * @param string $term
   *   Search term.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the right page.
   */
  public function branchSearchRedirect($branch, $term, Request $request) {
    $branch_ids = Branch::findBySlug($branch);
    if ($branch_ids) {
      $branch_id = array_shift($branch_ids);
      $branch = Branch::load($branch_id);
      $project = $branch->getProject();
      $term = Xss::filter($term);

      return $this->redirect('api.search.project.branch.term', [
        'branch' => $branch->getSlug(),
        'project' => $project->getSlug(),
        'term' => $term,
      ]);
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirects to either the module-defined search or the global search.
   *
   * @param string $term
   *   Term to search.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the right place.
   */
  public function moduleSearchRedirect($term, Request $request) {
    $term = Xss::filter($term);

    // Check if "/search/api" is a valid path (defined by sub-modules).
    if ($this->pathValidator->isValid('/search/api')) {
      $url = 'internal:/search/api?keys=' . $term;
      return new RedirectResponse(Url::fromUri($url)->toString());
    }

    // Otherwise redirect to the global redirect.
    return $this->redirect('api.search.global', [
      'term' => $term,
    ]);
  }

  /**
   * Search route given a project and branch that displays the search form.
   *
   * @param string $project
   *   Project slug.
   * @param string $branch
   *   Branch slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array
   *   Render array.
   */
  public function searchFormPage($project, $branch, Request $request) {
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project, TRUE);

    return [
      '#title' => $this->t('@project @branch', [
        '@project' => $project->getTitle(),
        '@branch' => $branch->getTitle(),
      ]),
      'form' => $this->formBuilder()->getForm(SearchForm::class, $branch),
    ];
  }

  /**
   * Search route given a project and branch that displays the search form.
   *
   * @param string $project
   *   Project slug.
   * @param string $branch
   *   Branch slug.
   * @param string $term
   *   Search term.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render array.
   */
  public function searchListingPage($project, $branch, $term, Request $request) {
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project, TRUE);
    $term = Xss::filter($term);

    // Try exact match and redirect if found.
    $matches = DocBlock::searchByTitle($term, $branch, 10, TRUE);
    if ($matches) {
      // Check for case sensitivity.
      $matches = DocBlock::loadMultiple($matches);
      foreach ($matches as $match) {
        if ($match->getObjectName() == $term) {
          $url = Formatter::objectUrl($match);
          if ($url) {
            return new RedirectResponse(Url::fromUri($url)->toString());
          }
        }
      }
    }

    // If we get here, there were multiple or zero matches.
    return [
      '#title' => $this->t('Search for <em>@term</em>', [
        '@term' => $term,
      ]),
      'search_links' => Formatter::searchLinks($term, $branch),
      'view' => views_embed_view('api_search', 'block_search_results', $branch->id(), $term),
    ];
  }

}
