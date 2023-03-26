<?php

namespace Drupal\api;

use Composer\Semver\Semver;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitRepository;
use CzProject\GitPhp\Helpers;
use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\Project;
use Drupal\api\Entity\DocBlock\DocFile;
use Drupal\api\Interfaces\ProjectInterface;
use Drupal\Component\Utility\UrlHelper;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystemInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Logger\RfcLogLevel;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StreamWrapper\PublicStream;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Utilities needed across the module.
 *
 * @package Drupal\api
 */
class Utilities {

  use StringTranslationTrait;

  /**
   * Variable representing one week.
   *
   * @const int
   */
  const ONE_WEEK = 604800;

  /**
   * Variable representing one month(ish).
   *
   * @const int
   */
  const ONE_MONTH = 2592000;

  /**
   * URL of the git repo containing the documentation.
   */
  const DOCUMENTATION_REPO_GIT_URL = 'https://git.drupalcode.org/project/documentation.git';

  /**
   * Base path to store git repositories.
   *
   * @var string
   */
  protected $gitBasePath;

  /**
   * FileSystemInterface definition.
   *
   * @var \Drupal\Core\File\FileSystemInterface
   */
  protected $fileSystem;

  /**
   * Logger instance for the api module.
   *
   * @var \Drupal\Core\Logger\LoggerChannelInterface
   */
  protected $logger;

  /**
   * Logger service.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected $loggerService;

  /**
   * ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Config instance.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $apiConfig;

  /**
   * Current route match service.
   *
   * @var \Drupal\Core\Routing\RouteMatchInterface
   */
  protected $currentRouteMatch;

  /**
   * Utilities constructor.
   *
   * @param \Drupal\Core\File\FileSystemInterface $file_system
   *   File system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_service
   *   Logger service.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Config factory.
   * @param \Drupal\Core\Routing\RouteMatchInterface $current_route_match
   *   Route match service.
   */
  public function __construct(FileSystemInterface $file_system, LoggerChannelFactoryInterface $logger_service, ConfigFactoryInterface $config_factory, RouteMatchInterface $current_route_match) {
    $this->fileSystem = $file_system;
    $this->loggerService = $logger_service;
    $this->configFactory = $config_factory;
    $this->currentRouteMatch = $current_route_match;

    $this->logger = $this->loggerService->get('api');
    $this->apiConfig = $this->configFactory->get('api.settings');
    $this->gitBasePath = rtrim($this->apiConfig->get('git_base_path'), '/') ?? \DRUPAL_ROOT . '/' . PublicStream::basePath();
    $this->gitBasePath .= '/api_git_repositories';
    $this->fileSystem->prepareDirectory($this->gitBasePath, FileSystemInterface:: CREATE_DIRECTORY | FileSystemInterface::MODIFY_PERMISSIONS);
  }

  /**
   * Gets the core compatibility of a project.
   *
   * @param string $project_folder
   *   Folder where the project is.
   * @param string $project_name
   *   Name of the project.
   * @param bool $lowest_available
   *   Return the lowest available match or the highest (default to highest).
   *
   * @return string
   *   Core compatibility of the project.
   */
  public function getCoreCompatibility($project_folder, $project_name, $lowest_available = FALSE) {
    $core_compatibility = '';
    $info_file = $project_folder . '/' . $project_name . '.info';

    if (file_exists($info_file)) {
      // D7, D6...
      $file = file_get_contents($info_file);
      $lines = explode(PHP_EOL, $file) ?? [];
      foreach ($lines as $line) {
        $line = str_replace(' ', '', $line);
        if (str_starts_with($line, 'core=')) {
          $core_compatibility = str_replace('core=', '', $line);
        }
      }
    }
    elseif (file_exists($info_file . '.yml')) {
      // D8, D9...
      $file = file_get_contents($info_file . '.yml');
      $lines = explode(PHP_EOL, $file) ?? [];
      foreach ($lines as $line) {
        $line = str_replace(' ', '', $line);
        if (str_starts_with($line, 'core_version_requirement:')) {
          // https://git.drupalcode.org/project/drupalorg/-/blob/207a3b24c8bd61fede8c8a8b9173189a46a1f029/drupalorg/drupalorg.module#L8389-8413
          $core_versions = $this->getRangeCoreVersions();
          $core_version_requirement = str_replace('core_version_requirement:', '', $line);
          foreach ($core_versions as $core_version => $semver_version) {
            try {
              if (Semver::satisfies($semver_version, $core_version_requirement)) {
                $core_compatibility = $core_version . '.x';
                if ($lowest_available) {
                  return $core_compatibility;
                }
              }
            }
            catch (\Exception $e) {
              // Malformed core_version_requirement. It isn’t worth attempting
              // to make sense of.
            }
          }
        }
        elseif (str_starts_with($line, 'core:') && empty($core_compatibility)) {
          $core_compatibility = str_replace('core:', '', $line);
        }
      }
    }

    return $core_compatibility;
  }

  /**
   * Gets array of possible core versions.
   *
   * @return array
   *   Possible core versions with semver notation.
   */
  protected function getRangeCoreVersions() {
    $core_versions = [];
    // Review when Drupal is a teenager...
    foreach (range(8, 15) as $core_major) {
      $core_versions[$core_major] = $core_major . '.9999.9999';
    }

    return $core_versions;
  }

  /**
   * Determines whether the server running the project has `git` or not.
   *
   * @return bool
   *   Whether git command is available or not.
   */
  public function hasGit() {
    return (function_exists('exec') && exec('git'));
  }

  /**
   * Gets the project name of a git project.
   *
   * Valid formats are:
   * - https://git.drupalcode.org/project/project_name.git
   * - git@git.drupal.org:project/project_name.git
   * "https" format preferred.
   *
   * @param string $git_url
   *   URL of the git repository.
   *
   * @return string
   *   Name of the project.
   */
  public function gitProjectName($git_url) {
    return Helpers::extractRepositoryNameFromUrl($git_url);
  }

  /**
   * Cleans up the branch names coming from remote.
   *
   * @param array $branches
   *   Branch names to clean up.
   *
   * @return array
   *   Branch names cleaned up.
   */
  public function gitBranchNamesFromRemote(array $branches) {
    foreach ($branches as $i => $branch) {
      $branches[$i] = $branch = str_replace('origin/', '', $branch);
      if (str_starts_with($branch, 'HEAD')) {
        unset($branches[$i]);
      }
    }

    return $branches;
  }

  /**
   * Gets the remote sanitized branch names of a repo object.
   *
   * @param \CzProject\GitPhp\GitRepository $repo_object
   *   Repository object.
   *
   * @return array
   *   Array of branch names.
   */
  public function getRepoBranches(GitRepository $repo_object) {
    $branches = $repo_object->getRemoteBranches();
    return $branches ? $this->gitBranchNamesFromRemote($branches) : [];
  }

  /**
   * Creates a branch folder within a repo folder.
   *
   * @param string $branch
   *   Branch to create.
   * @param string $folder
   *   Folder where the original repo is.
   * @param string $repo_url
   *   Url of the repo for the fallback.
   *
   * @return string
   *   Path of the branch folder.
   */
  public function createBranchFolder($branch, $folder, $repo_url = NULL) {
    $origin_folder = $folder . '/origin';
    $branch_folder = $folder . '/' . $branch;
    $git = new Git();

    try {
      $fileSystemHelper = new Filesystem();

      // Change branches and copy/replace files.
      $repo_object = $git->open($origin_folder);
      $repo_object->checkout($branch);
      $fileSystemHelper->mirror($origin_folder, $branch_folder, NULL, ['delete' => TRUE]);
    }
    catch (IOExceptionInterface $e) {
      if ($repo_url) {
        // Fallback to clone per branch.
        $branch_repo = $git->cloneRepository($repo_url, $branch_folder);
        $branch_repo->checkout($branch);
        $this->fileSystem->deleteRecursive($branch_folder . '/.git');
      }
      else {
        $this->logger->error('Could not create branch. Fallback Git URL not provided. Error: ' . $e->getMessage());
        $branch_folder = FALSE;
      }
    }
    catch (\Throwable $e) {
      $this->logger->error('Could not create branch. Error: ' . $e->getMessage());
      $branch_folder = FALSE;
    }

    return $branch_folder;
  }

  /**
   * Fetch new branches or updates existing ones.
   *
   * @param \Drupal\api\Interfaces\ProjectInterface $project
   *   Project to fetch branches from.
   * @param array $branches
   *   Branches to checkout or pull.
   * @param bool $create_branches
   *   Whether to create the branches in one operation or not.
   *
   * @return array|null
   *   Information about the created branches or NULL if it failed.
   */
  public function gitFetchBranches(ProjectInterface $project, array $branches = [], $create_branches = FALSE) {
    if (!$this->hasGit()) {
      return NULL;
    }

    // Try to determine the $base_folder value from the existing branches.
    $base_folder = FALSE;
    $projectBranches = $project->getBranches(TRUE);
    if (empty($projectBranches)) {
      // If we don't have any branches we can't guess anything.
      return NULL;
    }

    /** @var \Drupal\api\Interfaces\BranchInterface $anyBranch */
    $anyBranch = array_shift($projectBranches);
    $directories = $anyBranch->getDirectories(TRUE);
    foreach ($directories as $directory) {
      if (str_starts_with($directory, $this->gitBasePath)) {
        $base_folder = str_replace('/' . $anyBranch->getSlug(), '', $directory);
      }
    }

    if (!$base_folder) {
      // Maybe the paths were altered.
      return NULL;
    }

    // Create folders per branch.
    $folder_structure = [];
    foreach ($branches as $branch) {
      $branch_folder = $create_branches ?
        $this->createBranchFolder($branch, $base_folder) :
        $base_folder . '/' . $branch;
      if ($branch_folder) {
        $folder_structure[] = [
          'repo' => '',
          'branch' => $branch,
          'folder' => $branch_folder,
          'created' => $create_branches,
        ];
      }
    }

    return $folder_structure;
  }

  /**
   * Clones the repository into the folder for the given branches.
   *
   * @param string $repository
   *   Git URL of the repo.
   * @param array $branches
   *   Branches to clone.
   * @param bool $create_branches
   *   Whether to create the branches in one operation or not.
   *
   * @return array|null
   *   Information about the created branches or NULL if it failed.
   */
  public function gitClone($repository, array $branches = [], $create_branches = FALSE) {
    if (!$this->hasGit() || !UrlHelper::isValid($repository)) {
      return NULL;
    }

    $base_folder = $this->gitBasePath . '/' . uniqid();
    if (!$this->fileSystem->mkdir($base_folder)) {
      // Permissions?
      return NULL;
    }

    $folder_structure = [];
    $git = new Git();
    $origin_folder = $base_folder . '/origin';
    try {
      // Used to get the branches and to test the clone command.
      $origin_repo = $git->cloneRepository($repository, $origin_folder);
    }
    catch (\Throwable $e) {
      return NULL;
    }

    // Check branches if none given.
    if (empty($branches)) {
      $branches = $this->getRepoBranches($origin_repo);
      if (empty($branches)) {
        return NULL;
      }
    }

    // Create folders per branch.
    foreach ($branches as $branch) {
      $branch_folder = $create_branches ?
        $this->createBranchFolder($branch, $base_folder, $repository) :
        $base_folder . '/' . $branch;
      if ($branch_folder) {
        $folder_structure[] = [
          'repo' => $repository,
          'branch' => $branch,
          'folder' => $branch_folder,
          'created' => $create_branches,
        ];
      }
    }

    return $folder_structure;
  }

  /**
   * Gets a list of available update frequencies.
   *
   * @return array
   *   Values available for update frequencies.
   */
  public static function updateFrequencyValues() {
    return [
      1 => t('1 second'),
      3600 => t('1 hour'),
      10800 => t('3 hours'),
      21600 => t('6 hours'),
      43200 => t('12 hours'),
      86400 => t('1 day'),
      self::ONE_WEEK => t('1 week'),
      self::ONE_MONTH => t('30 days'),
    ];
  }

  /**
   * Writes a message to the logger based on the result given.
   *
   * @param array $result
   *   Results from parsing array.
   * @param string $type
   *   Type of the entity that was parsed.
   */
  public function logResultFromParsing(array $result, $type) {
    $watchdog = [
      'log' => FALSE,
      'message' => '',
      'type' => RfcLogLevel::INFO,
      'data' => [
        '@entity' => $result['label'],
        '@entitytype' => $type,
      ],
    ];

    if ($result['parsed']) {
      $watchdog['message'] = 'Parsed @count entries for @entity (type @entitytype).';
      $watchdog['data']['@count'] = $result['docblock_count'];
      $watchdog['log'] = TRUE;
    }
    elseif (!empty($result['limit_exceeded']) && $result['limit_exceeded']) {
      $watchdog['message'] = '@entity (type @entitytype) will be parsed at a later run, limit was exceeded.';
      $watchdog['type'] = RfcLogLevel::NOTICE;
      $watchdog['log'] = TRUE;
    }
    elseif ($result['needs_parsing']) {
      $watchdog['message'] = 'Could not parse any entries for @entity (type @entitytype).';
      $watchdog['type'] = RfcLogLevel::ERROR;
      $watchdog['log'] = TRUE;
    }

    if ($watchdog['log']) {
      $this->logger->log($watchdog['type'], $watchdog['message'], $watchdog['data']);
    }
  }

  /**
   * Adds external documentation to a folder.
   *
   * Older versions of Drupal (7 and below) do no include a "mainpage" tag in
   * the source code, so this external repo adds those additional pages to
   * provide more structured information and a nice welcoming page.
   *
   * All old core versions are reflected in the CORE.VERSION-1.x branch. So
   * 7.x would map to 7.x-1.x, 6.x to 6.x-1.x, etc.
   *
   * @param string $folder
   *   Path to the folder.
   * @param string $branch_name
   *   Name of the branch.
   * @param \Drupal\api\Interfaces\ProjectInterface $project
   *   Project where this folder is.
   */
  public function addExtraDocumentation($folder, $branch_name, ProjectInterface $project) {
    if ($project->isCore()) {
      $branch_name .= '-1.x';
      $folder .= '/external_documentation';
      $git = new Git();
      try {
        $documentation_repo = $git->cloneRepository(self::DOCUMENTATION_REPO_GIT_URL, $folder);
        $documentation_repo->checkout($branch_name);
      }
      catch (\Throwable $e) {
        // Maybe the branch does not exist.
      }
    }
  }

  /**
   * Returns the default branch for the whole site.
   *
   * @return \Drupal\api\Interfaces\BranchInterface|null
   *   Returns the default branch or null.
   */
  public function getDefaultBranchProject() {
    $default = $this->apiConfig->get('default_branch_project') ?? NULL;
    if ($default) {
      [, $id] = explode('|', $default);
      if ($id && ($branch = Branch::load($id))) {
        return $branch;
      }
    }

    return NULL;
  }

  /**
   * Types of pages available and their descriptions.
   *
   * @return array
   *   Associative array containing type => description.
   */
  public function getPageTypesAndDescriptions() {
    return [
      'branch' => $this->t('Branch landing pages'),
      'listing' => $this->t('Listing pages for functions, classes, topics, etc.'),
      'item' => $this->t('Single item pages for a function, class, topic, etc.'),
      'references' => $this->t('Pages listing references such as function calls'),
      'search' => $this->t('Search forms and search results'),
      'special' => $this->t('Miscellaneous API module pages'),
    ];
  }

  /**
   * Determines the type of page we are viewing based on the route.
   *
   * @return string
   *   Type of page we are in.
   */
  public function getPageTypeFromRoute() {
    $route_name = $this->currentRouteMatch->getRouteName();
    if (strpos($route_name, 'api.') !== 0 && $route_name !== 'entity.project.canonical') {
      return '';
    }

    // Some routes might actually cover two types of page, so we are returning
    // the default route type that is processed. If we need more granular
    // control we would need to read and process the parameters and then see
    // which type is correct, as done in OutputController, but that seems like
    // overkill for this case.
    $type = '';
    switch ($route_name) {
      case 'api.search.autocomplete':
      case 'api.full_list':
      case 'api.full_list_project':
      case 'api.function_dump':
      case 'api.projects':
        $type = 'special';
        break;

      case 'entity.project.canonical':
      case 'api.default':
      case 'api.branch_default_route':
        $type = 'branch';
        break;

      case 'api.search.project.branch':
      case 'api.search.project.branch.term':
      case 'api.search.global':
      case 'api.search.default':
      case 'api.search.branch':
      case 'api.opensearch':
      case 'api.opensearch.suggest':
        $type = 'search';
        break;

      case 'api.branch_explicit_route':
      case 'api.file_references_route':
        $type = 'listing';
        break;

      case 'api.namespace_route':
      case 'api.detail_page_type_route':
      case 'api.legacy_file_route':
        $type = 'item';
        break;

      case 'api.detail_page_type_subtype_route':
        $type = 'references';
        break;
    }

    return $type;
  }

  /**
   * Extract the project and branch from the current loaded route.
   *
   * @return array
   *   Project and branch found.
   */
  public function getProjectAndBranchFromRoute() {
    $results = [
      'project' => NULL,
      'branch' => NULL,
    ];

    $project = $this->currentRouteMatch->getParameter('project');

    // Project canonical route will load the whole object.
    $project = ($project instanceof ProjectInterface) ? $project : Project::getBySlug($project);
    if ($project) {
      $branch = $this->currentRouteMatch->getParameter('branch') ?? $this->currentRouteMatch->getParameter('argument');
      $branch = ($branch) ? Branch::getBySlug($branch, $project) : $project->getDefaultBranch(TRUE);

      $results['project'] = $project;
      $results['branch'] = $branch;
    }

    return $results;
  }

  /**
   * Extract the DocBlock item from the current loaded route.
   *
   * @param bool $fallback_to_default
   *   Try to fallback to defaults in case branch and project aren't found.
   *
   * @return array
   *   Best guesses for docblock and file.
   */
  public function getElementsFromRoute($fallback_to_default = FALSE) {
    [
      'project' => $project,
      'branch' => $branch,
    ] = $this->getProjectAndBranchFromRoute();
    if (!$branch && $fallback_to_default) {
      $branch = $this->getDefaultBranchProject();
      if ($branch) {
        $project = $branch->getProject();
      }
    }

    $elements = [
      'docblock' => NULL,
      'file' => NULL,
      'project' => $project,
      'branch' => $branch,
    ];

    if ($branch) {
      $item = $this->currentRouteMatch->getParameter('item') ?? FALSE;
      $type = $this->currentRouteMatch->getParameter('type') ?? FALSE;
      $filename = $this->currentRouteMatch->getParameter('filename') ?? FALSE;

      $file = FALSE;
      if ($filename) {
        $filename = urldecode($filename);
        $filename = Formatter::getReplacementName($filename, 'file', TRUE);
        $file_id = DocBlock::findFileByFileName($filename, $branch);
        if ($file_id) {
          $file = DocBlock::load($file_id);
        }
      }

      if ($file) {
        if (!$item || !$type) {
          // We can't check any further, so the item is the file.
          $elements += [
            'docblock' => $file,
            'file' => $file,
          ];
        }
        else {
          $item = urldecode($item);
          $docBlock_ids = DocBlock::findByNameAndType($item, $type, $branch);
          $docBlocks = DocBlock::loadMultiple($docBlock_ids);
          foreach ($docBlocks as $docBlock) {
            if ($docBlock->getFileName() == $file->getFileName()) {
              $elements += [
                'docblock' => $docBlock,
                'file' => $file,
              ];
              break;
            }
          }
        }
      }
    }

    return $elements;
  }

  /**
   * Remove DocBlocks that are no longer attached to a branch.
   *
   * Files and branches have timestamp fields which indicate when they were
   * processed. If the difference in days between these two timestamps is bigger
   * than the $remove_orphan_files value then it means that the files are
   * orphan, as the entries are always re-generated when the branch is parsed.
   */
  public function deleteOrphanDocBlocks() {
    $remove_orphan_files = (int) $this->apiConfig->get('remove_orphan_files');
    if ($remove_orphan_files > 0) {
      $branches = Branch::loadMultiple() ?? [];
      foreach ($branches as $branch) {
        $branch_was_parsed = $branch->getQueued();
        if ($branch_was_parsed) {
          $time_to_orphan = (new \DateTime())
            ->setTimestamp($branch_was_parsed)
            ->modify('-' . $remove_orphan_files . ' days');

          // Get files from this branch where the timestamp is smaller than
          // the calculated date.
          $files_to_delete = DocFile::findCreatedBefore($time_to_orphan->getTimestamp());
          if ($files_to_delete) {
            $files_to_delete = DocFile::loadMultiple($files_to_delete);
            foreach ($files_to_delete as $docFile) {
              // Deleting the DocBlock will delete all related entities.
              $this->logger->log(RfcLogLevel::NOTICE, 'DocBlock @docblock deleted.', [
                '@docblock' => $docFile->getDocBlock()->getTitle(),
              ]);
              $docFile->getDocBlock()->delete();
            }
          }
        }
      }
    }
  }

}
