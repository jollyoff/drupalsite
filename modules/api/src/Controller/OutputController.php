<?php

namespace Drupal\api\Controller;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\DocBlock\DocFunction;
use Drupal\api\ExtendedQueries;
use Drupal\api\Formatter;
use Drupal\api\Interfaces\ProjectInterface;
use Drupal\api\Traits\RouteElementsTrait;
use Drupal\api\Utilities;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Output pages.
 *
 * @package Drupal\api\Controller
 */
class OutputController extends ControllerBase {

  use RouteElementsTrait;

  /**
   * Hierarchy of elements and subelements allowed in URLs arguments on routes.
   *
   * @var array[]
   */
  protected $elementsHierarchy = [
    'function' => [
      'calls',
      'implementations',
      'references',
      'invokes',
      'theme_invokes',
      'theme_references',
      'overrides',
    ],
    'constant' => [
      'constants',
    ],
    'global' => [],
    'property' => [],
    'class' => [
      'hierarchy',
      'uses',
      'references',
      'annotations',
      'services',
      'element_invokes',
    ],
    'interface' => [
      'hierarchy',
      'implements',
      'uses',
      'references',
      'services',
    ],
    'trait' => [
      'uses',
      'references',
    ],
    'service' => [
      'use',
    ],
    'group' => [],
  ];

  /**
   * Types of objects allowed in legacy routes.
   *
   * @var string[]
   */
  protected $legacyObjectTypes = [
    'function',
    'constant',
    'global',
    'group',
  ];

  /**
   * Types of listings allowed in legacy routes.
   *
   * @var string[]
   */
  protected $legacyListingTypes = [
    'functions',
    'files',
    'constants',
    'globals',
    'groups',
  ];

  /**
   * Utilities service.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * Construct method.
   *
   * @param \Drupal\api\Utilities $utilities
   *   Utilities service.
   */
  public function __construct(Utilities $utilities) {
    $this->utilities = $utilities;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $utilities = $container->get('api.utilities');
    return new static($utilities);
  }

  /**
   * Outputs a full list of elements for a branch / project.
   *
   * @param string|\Drupal\api\Interfaces\ProjectInterface $project
   *   Project, ID or slug where the branch belongs to.
   * @param string|\Drupal\api\Interfaces\BranchInterface $branch
   *   Branch, ID or slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json output.
   */
  public function fullList($project, $branch, Request $request) {
    $project = $this->getProject($project);
    $branch = ($project instanceof ProjectInterface) ?
      $this->getBranch($branch, $project) :
      NULL;
    $range = $this->getRangeFromParams($request);

    if ($range === FALSE || !$project || !$branch) {
      return new JsonResponse([]);
    }

    $ids = DocBlock::getFullList($branch, $range);
    $output = [];
    foreach ($ids as $id) {
      /** @var \Drupal\api\Interfaces\DocBlockInterface $docBlock */
      $docBlock = DocBlock::load($id);
      $output[] = $docBlock->toBasicArray();
    }

    return new JsonResponse($output);
  }

  /**
   * Checks the parameters for range options.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null|false
   *   Range parameters, null or false if there is an error.
   */
  protected function getRangeFromParams(Request $request) {
    $range = NULL;
    if ($request->query->has('limit') || $request->query->has('page')) {
      $limit = (int) $request->query->get('limit', 50);
      $offset = (int) $request->query->get('page', 0) * $limit;
      if ($limit <= 0 || $offset < 0) {
        $range = FALSE;
      }
      else {
        $range = [
          'limit' => $limit,
          'offset' => $offset,
        ];
      }
    }

    return $range;
  }

  /**
   * Outputs a full list of elements for a project's default branch.
   *
   * @param string $project
   *   Project ID or slug where the branch belongs to.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   Json output.
   */
  public function fullListProject($project, Request $request) {
    $project = $this->getProject($project);
    if (!$project) {
      return new JsonResponse([]);
    }

    $branch = $project->getDefaultBranch();
    return $this->fullList($project, $branch, $request);
  }

  /**
   * Outputs a full dump of function elements for a branch.
   *
   * @param string $branch
   *   Branch to give elements from.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse|\Symfony\Component\HttpFoundation\Response
   *   Json output or plain text, depending on request object.
   */
  public function functionDump($branch, Request $request) {
    $format = $request->query->get('format', FALSE);
    $defaultBranch = $this->utilities->getDefaultBranchProject();
    if (!$defaultBranch) {
      if ($format == 'text') {
        return new Response($this->t('Not found'), Response::HTTP_NOT_FOUND);
      }
      return new JsonResponse([], Response::HTTP_NOT_FOUND);
    }
    $branch = $this->getBranch($branch, $defaultBranch->getProject(), TRUE);
    $dump = DocFunction::getFunctionDumpByBranch($branch);
    if ($format == 'text') {
      $string = '';
      foreach ($dump as $item) {
        $string .= $item['signature'] . ' ### ' . $item['summary'] . PHP_EOL;
      }

      return new Response($string);
    }
    return new JsonResponse($dump);
  }

  /**
   * Gets the default project/branch and redirects to it.
   *
   * @param string $argument
   *   Type of the object user is trying to see.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   Redirect to the branch and project or front page.
   */
  public function default($argument, Request $request) {
    $argument = $this->validateObjectType($argument);
    if ($argument === FALSE) {
      throw new NotFoundHttpException();
    }

    $branch = $this->utilities->getDefaultBranchProject();
    if ($branch) {
      $url = 'internal:/api/' . $branch->getProject()->getSlug();
      if ($argument) {
        $url .= '/' . $argument;
      }
      $url .= '/' . $branch->getSlug();
      return new RedirectResponse(Url::fromUri($url)->toString());
    }

    $this->messenger()->addMessage($this->t('Default project and branch is not set.'));
    return $this->redirect('api.projects');
  }

  /**
   * Validates the parameter and returns it back if is valid.
   *
   * @param string $argument
   *   Type of the object.
   * @param bool $extended
   *   Validate over an extended set of choices.
   *
   * @return string
   *   Correct object_type or empty string if not valid.
   */
  protected function validateObjectType(string $argument, $extended = FALSE) {
    $choices = [
      '',
      'functions',
      'files',
      'constants',
      'globals',
      'groups',
    ];
    if ($extended) {
      $choices = array_merge($choices, [
        'classes',
        'namespaces',
        'deprecated',
        'services',
        'elements',
      ]);
    }
    return in_array($argument, $choices) ? $argument : FALSE;
  }

  /**
   * Page callback: Displays a list of links to projects using a pager.
   *
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array
   *   Render array.
   */
  public function projectList(Request $request) {
    $output = [];

    $links = views_embed_view('api_projects', 'block_project_list');
    if ($links) {
      $output['project_list'] = $links;
    }

    return $output;
  }

  /**
   * Entry point route to determine what to show given the argument.
   *
   * It will take the default branch of the project and redirect to the right
   * place.
   *
   * @param string $project
   *   Project slug.
   * @param string $argument
   *   Rest of the route.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render array for the page, NULL if the page doesn't work or redirect.
   */
  public function branchDefaultRoute($project, $argument, Request $request) {
    $project_argument = $project;
    $project = $this->getProject($project_argument);
    if (!$project) {
      // Some legacy routes could reach this endpoint.
      // ie: api/{listing_type}/{branch}.
      return $this->legacyListing($project_argument, $argument);
    }

    // Try branch first, but don't throw exception.
    $branch = $this->getBranch($argument, $project);
    if ($branch) {
      return $this->branchView($branch, $project, $request);
    }

    // Not a branch, so validate the argument.
    $argument = $this->validateObjectType($argument, TRUE);
    if ($argument === FALSE) {
      throw new NotFoundHttpException();
    }

    // Valid argument and project, so take the default branch for the project.
    $branch = $project->getDefaultBranch();
    if ($branch) {
      $url = 'internal:/api/'
        . $branch->getProject()->getSlug()
        . '/' . $argument
        . '/' . $branch->getSlug();
      return new RedirectResponse(Url::fromUri($url)->toString());
    }

    $this->messenger()->addMessage($this->t('Default branch is not set for %project project.', [
      '%project' => $project->getTitle(),
    ]));
    return $this->redirect('<front>');
  }

  /**
   * Entry point route to determine what to show given the argument and branch.
   *
   * @param string $project
   *   Project slug.
   * @param string $argument
   *   Rest of the route.
   * @param string $branch
   *   Branch slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render array for the page, or NULL if the page doesn't work.
   */
  public function branchExplicitRoute($project, $argument, $branch, Request $request) {
    $project_argument = $project;
    $project = $this->getProject($project_argument);
    if (!$project) {
      // Some legacy routes could reach this endpoint.
      // ie: api/{type}/{object}/{branch}.
      return $this->legacyObject($project_argument, $argument, $branch);
    }
    $branch_argument = $branch;
    $branch = $this->getBranch($branch, $project);
    if (!$branch) {
      if ($argument == 'namespace') {
        return $this->redirect('api.namespace_route', [
          'project' => $project->getSlug(),
          'namespace' => $branch_argument,
          'branch' => $project->getDefaultBranch(TRUE)->getSlug(),
        ]);
      }

      throw new NotFoundHttpException();
    }
    $object_type = $this->validateObjectType($argument, TRUE);
    if ($object_type === FALSE) {
      // It could be a full-file path.
      $file = $this->getFile($argument, $branch, TRUE);
      return Formatter::preparePageFileVariables($file);
    }

    return Formatter::preparePageListingVariables($branch, $object_type);
  }

  /**
   * Process object legacy route and redirect them to the right place.
   *
   * @param string $type
   *   Function, constant, global...
   * @param string $object_name
   *   Name of the object to load.
   * @param string $branch_slug
   *   Slug of the branch.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Either redirect to the right place or throw a not found exception.
   */
  protected function legacyObject($type, $object_name, $branch_slug) {
    if (!in_array($type, $this->legacyObjectTypes)) {
      throw new NotFoundHttpException();
    }
    $branches_ids = Branch::findBySlug($branch_slug);
    if ($branches_ids) {
      $branches = Branch::loadMultiple($branches_ids);
      foreach ($branches as $branch) {
        $object = ExtendedQueries::loadExtendedWithOverrides($object_name, $branch, $type);
        if ($object && !empty($object->id)) {
          $docBlock = DocBlock::load($object->id);
          $url = Formatter::objectUrl($docBlock);
          if ($url) {
            return new RedirectResponse(Url::fromUri($url)->toString());
          }
        }
      }
    }

    throw new NotFoundHttpException();
  }

  /**
   * Redirects legacy URLs for files.
   *
   * The redirect is:
   * api/file/{directory}/{file.php}[/{branch}] ->
   * api/{project}/{directory}{separator}{file.php}/{branch}
   *
   * @param string $file_information
   *   All parametres after the 'file/' part of the URL.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Either redirect to the right place or throw a not found exception.
   */
  public function legacyFileRoute($file_information) {
    $arguments = explode(Formatter::FILEPATH_SEPARATOR_REPLACEMENT, $file_information);

    // Last argument can be:
    // * Branch slug.
    // * Filename and extension.
    // * Fixed string: 'source', 'documentation' (will be ignored)
    $last_argument = array_pop($arguments);
    if (in_array($last_argument, ['source', 'documentation'])) {
      $last_argument = array_pop($arguments);
    }

    if (!$last_argument) {
      throw new NotFoundHttpException();
    }

    // Check if $last_argument is a branch.
    $branches_ids = Branch::findBySlug($last_argument);
    if ($branches_ids) {
      // Next element should be the file basename then.
      $file_basename = array_pop($arguments);
      $branches = Branch::loadMultiple($branches_ids);
    }
    else {
      // No branch found, so it means it should be a file basename.
      // Also, assume default branch.
      $file_basename = $last_argument;
      $branches = [$this->utilities->getDefaultBranchProject()];
    }

    if (!$file_basename || !$branches) {
      throw new NotFoundHttpException();
    }

    // At this point, the rest of the arguments are the path to the file.
    $path = $arguments ? implode(Formatter::FILEPATH_SEPARATOR, $arguments) . '/' : '';

    // Try to find the file now on each of the branches. Settle for first match.
    foreach ($branches as $branch) {
      $file_id = DocBlock::findFileByFileName($path . $file_basename, $branch);
      if ($file_id) {
        $file = DocBlock::load($file_id);
        $url = Formatter::objectUrl($file, TRUE);
        return new RedirectResponse(Url::fromUri($url)->toString());
      }
    }

    // Nothing found.
    throw new NotFoundHttpException();
  }

  /**
   * Process listing legacy route and redirect them to the right place.
   *
   * @param string $type
   *   Functions, constants, globals...
   * @param string $branch_slug
   *   Slug of the branch.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|\Symfony\Component\HttpKernel\Exception\NotFoundHttpException
   *   Either redirect to the right place or throw a not found exception.
   */
  protected function legacyListing($type, $branch_slug) {
    if (in_array($type, $this->legacyObjectTypes)) {
      // Most likely a legacy route WITHOUT the branch_slug, so get defaults
      // and redirect to the right place.
      $default_branch = $this->utilities->getDefaultBranchProject();
      if (!$default_branch) {
        throw new NotFoundHttpException();
      }
      return $this->legacyObject($type, $branch_slug, $default_branch->getSlug());
    }
    elseif (!in_array($type, $this->legacyListingTypes)) {
      throw new NotFoundHttpException();
    }

    $branches_ids = Branch::findBySlug($branch_slug);
    if ($branches_ids) {
      $branch_id = array_shift($branches_ids);
      $branch = Branch::load($branch_id);
      $url = 'internal:/api/' . $branch->getProject()->getSlug() . '/' . $type . '/' . $branch_slug;
      return new RedirectResponse(Url::fromUri($url)->toString());
    }

    throw new NotFoundHttpException();
  }

  /**
   * Entry point route for namespace paths.
   *
   * @param string $project
   *   Project slug.
   * @param string $namespace
   *   Namespace to see.
   * @param string $branch
   *   Branch slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null
   *   Render array for the page, or NULL if the page doesn't work.
   */
  public function namespaceRoute($project, $namespace, $branch, Request $request) {
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project, TRUE);
    $namespace = Formatter::getReplacementName($namespace, 'namespace', TRUE);

    $title = 'namespace ' . $namespace;
    return [
      '#title' => $title,
      '#theme' => 'api_namespace_page',
      '#branch' => $branch,
      '#name' => $namespace,
      '#listing' => views_embed_view(
        'api_namespaces',
        'block_items_namespace',
        $branch->id(),
        $namespace
      ),
    ];
  }

  /**
   * Entry point route when five arguments are given in the URL.
   *
   * Example: api/{project}/{filename}/{type}/{branch}
   *
   * @param string $project
   *   Project slug.
   * @param string $filename
   *   Name of the file including path (url encoded).
   * @param string $type
   *   Type of element to show.
   * @param string $branch
   *   Branch slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render array for the page, or NULL if the page doesn't work.
   */
  public function fileReferencesRoute($project, $filename, $type, $branch, Request $request) {
    $branch_argument = $branch;
    // Bring objects and validated parameters.
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project);
    if (!$branch) {
      // Maybe it's missing the branch, so take the project's default branch and
      // redirect to the best guess.
      return $this->redirect('api.detail_page_type_route', [
        'project' => $project->getSlug(),
        'filename' => $filename,
        'type' => $type,
        'item' => $branch_argument,
        'branch' => $project->getDefaultBranch(TRUE)->getSlug(),
      ]);
    }
    $file = $this->getFile($filename, $branch, TRUE);
    $types_allowed = [
      'theme_invokes',
      'theme_references',
      'yml_config',
      'yml_keys',
    ];
    if (!in_array($type, $types_allowed)) {
      throw new NotFoundHttpException();
    }

    return Formatter::prepareFunctionCallVariables($file, $type);
  }

  /**
   * Entry point route when six arguments are given in the URL.
   *
   * Example: api/{project}/{filename}/{type}/{item}/{branch}
   *
   * @param string $project
   *   Project slug.
   * @param string $filename
   *   Name of the file including path (url encoded).
   * @param string $type
   *   Type of element to show.
   * @param string $item
   *   Element to see.
   * @param string $branch
   *   Branch slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null|\Symfony\Component\HttpFoundation\RedirectResponse
   *   Render array for the page, or NULL if the page doesn't work.
   */
  public function detailPageTypeRoute($project, $filename, $type, $item, $branch, Request $request) {
    $branch_argument = $branch;
    // Bring objects and validated parameters.
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project);
    if (!$branch) {
      // Maybe it's missing the branch, so take the project's default branch and
      // redirect to the best guess.
      return $this->redirect('api.detail_page_type_subtype_route', [
        'project' => $project->getSlug(),
        'filename' => $filename,
        'type' => $type,
        'subtype' => $item,
        'item' => $branch_argument,
        'branch' => $project->getDefaultBranch(TRUE)->getSlug(),
      ]);
    }
    $file = $this->getFile($filename, $branch, TRUE);
    $types_allowed = array_keys($this->elementsHierarchy);
    if (!in_array($type, $types_allowed)) {
      throw new NotFoundHttpException();
    }
    $item = $this->getItem($item, $file, $type, TRUE);

    // Call different output functions depending on $type.
    if (in_array($type, ['constant', 'global', 'property'])) {
      return Formatter::pageSimpleItem($item, $type);
    }
    elseif (in_array($type, ['class', 'interface', 'trait'])) {
      return Formatter::pageClass($item);
    }

    // 'function', 'service' and 'group` are left.
    $function_class = 'page' . ucfirst($type);
    return Formatter::$function_class($item);
  }

  /**
   * Entry point route when seven arguments are given in the URL.
   *
   * Example: api/{project}/{filename}/{type}/{item}/{branch}
   *
   * @param string $project
   *   Project slug.
   * @param string $filename
   *   Name of the file including path (url encoded).
   * @param string $type
   *   Type of element to show.
   * @param string $subtype
   *   Type with the type of element to show.
   * @param string $item
   *   Element to see.
   * @param string $branch
   *   Branch slug.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null
   *   Render array for the page, or NULL if the page doesn't work.
   */
  public function detailPageTypeSubtypeRoute($project, $filename, $type, $subtype, $item, $branch, Request $request) {
    // Bring objects and validated parameters.
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project, TRUE);
    $file = $this->getFile($filename, $branch, TRUE);
    $types_allowed = array_keys($this->elementsHierarchy);
    if (!in_array($type, $types_allowed)) {
      throw new NotFoundHttpException();
    }
    $subtypes_allowed = $this->elementsHierarchy[$type];
    if (!in_array($subtype, $subtypes_allowed)) {
      throw new NotFoundHttpException();
    }
    $item = $this->getItem($item, $file, $type, TRUE);

    // Call different output functions depending on $type and $subtype.
    if ($subtype == 'hierarchy') {
      return Formatter::pageClassHierarchy($item);
    }
    elseif ($type == 'interface' && $subtype == 'implements') {
      return Formatter::pageInterfaceImplements($item);
    }

    // All 19 remaining cases fall into this function call.
    return Formatter::pageFunctionCalls($item, $subtype);
  }

  /**
   * Page callback: Generates the default documentation page for a branch.
   *
   * @param string|\Drupal\api\Interfaces\BranchInterface $branch
   *   Branch giving the branch to display documentation for.
   * @param string|\Drupal\api\Interfaces\ProjectInterface $project
   *   Branch giving the branch to display documentation for.
   * @param \Symfony\Component\HttpFoundation\Request $request
   *   Request object.
   *
   * @return array|null
   *   Render array for the page, or NULL if the page doesn't work.
   */
  public function branchView($branch, $project, Request $request) {
    $project = $this->getProject($project, TRUE);
    $branch = $this->getBranch($branch, $project, TRUE);

    return Formatter::prepareBranchVariables($branch);
  }

}
