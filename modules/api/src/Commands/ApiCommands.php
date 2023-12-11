<?php

namespace Drupal\api\Commands;

use Consolidation\OutputFormatters\StructuredData\RowsOfFields;
use Drupal\api\Entity\Branch;
use Drupal\api\Entity\ExternalBranch;
use Drupal\api\Entity\PhpBranch;
use Drupal\api\Entity\Project;
use Drupal\api\Interfaces\ProjectInterface;
use Drupal\api\Parser;
use Drupal\api\Utilities;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\UrlHelper;
use Drush\Commands\DrushCommands;
use Symfony\Component\Console\Input\Input;
use Symfony\Component\Console\Output\Output;

/**
 * A Drush commandfile for some additional checks on the api module.
 */
class ApiCommands extends DrushCommands {

  /**
   * Creates a project and branch or updates them if existing.
   *
   * Given all the details of these via arguments and having uploaded
   * the source code to the given "directory" beforehand, it will create
   * and/or update a project and branch.
   *
   * @param string $project
   *   Project value. If not provided, user will be prompted.
   * @param string $project_title
   *   Project title value. If not provided, user will be prompted.
   * @param string $project_type
   *   Project type value. If not provided, user will be prompted.
   * @param string $branch
   *   Branch value. If not provided, user will be prompted.
   * @param string $branch_title
   *   Branch title value. If not provided, user will be prompted.
   * @param string $directory
   *   Directory where files are. If not provided, user will be prompted.
   * @param string $core_compatibility
   *   Core compatibility value. If not provided, user will be prompted.
   * @param int $update_frequency
   *   Update frequency value. If not provided, user will be prompted.
   *
   * @command api:upsert-branch
   * @aliases apiub
   * @usage drush apiub
   */
  public function upsertBranch($project, $project_title, $project_type, $branch, $branch_title, $directory, $core_compatibility, $update_frequency) {
    if (!Project::exists($project)) {
      $project_slug = $project;
      /** @var \Drupal\api\Interfaces\ProjectInterface $project */
      $project = Project::create();
      $project
        ->setTitle($project_title)
        ->setType($project_type)
        ->setSlug($project_slug)
        ->save();
    }
    else {
      $project = Project::getBySlug($project);
      $project
        ->setTitle($project_title)
        ->setType($project_type);
      $project->save();
    }

    $branch_slug = $branch;
    $branch = NULL;
    if ($project->id()) {
      $branch = Branch::getBySlug($branch_slug, $project);
      if (empty($branch)) {
        /** @var \Drupal\api\Interfaces\BranchInterface $branch */
        $branch = Branch::create();
        $branch
          ->setTitle($branch_title)
          ->setSlug($branch_slug)
          ->setCoreCompatibility($core_compatibility)
          ->setProject($project)
          ->setDirectories($directory)
          ->save();
      }
      else {
        $branch
          ->setTitle($branch_title)
          ->setDirectories($directory)
          ->setCoreCompatibility($core_compatibility)
          ->setUpdateFrequency($update_frequency);
        $branch->save();
      }
    }

    if (!empty($branch)) {
      $this->logger()->success(dt('The branch @branch was created.', [
        '@branch' => $branch->getTitle(),
      ]));
    }
    else {
      if (!empty($project)) {
        $this->logger()->warning(dt('The project @project was created, but not the branch.', [
          '@project' => $project->getTitle(),
        ]));
      }
      else {
        $this->logger()->error(dt('The project and branch could not be created.'));
      }
    }
  }

  /**
   * Prompt for parameters for the api:upsert-branch command.
   *
   * @hook interact api:upsert-branch
   */
  public function interactUpsertBranch(Input $input, Output $output) {
    if (!$input->getArgument('project')) {
      $project = $this->io()->ask('Project machine_name');
      $input->setArgument('project', $project);
    }

    if (!$input->getArgument('project_title')) {
      $project_title = $this->io()->ask('Project title');
      $input->setArgument('project_title', $project_title);
    }

    if (!$input->getArgument('project_type')) {
      $project_type = $this->io()->ask('Project type (ie: core, module)');
      $input->setArgument('project_type', $project_type);
    }

    if (!$input->getArgument('branch')) {
      $branch = $this->io()->ask('Branch machine_name');
      $input->setArgument('branch', $branch);
    }

    if (!$input->getArgument('branch_title')) {
      $branch_title = $this->io()->ask('Branch title');
      $input->setArgument('branch_title', $branch_title);
    }

    if (!$input->getArgument('directory')) {
      $directory = $this->io()->ask('Directory where the code is (no validation will be done)');
      $input->setArgument('directory', $directory);
    }

    if (!$input->getArgument('core_compatibility')) {
      $core_compatibility = $this->io()->ask('Core compatibility');
      $input->setArgument('core_compatibility', $core_compatibility);
    }

    if (!$input->getArgument('update_frequency')) {
      $update_frequency = $this->io()->ask('Update frequency in seconds (ie: 604800 for 1 week). Defaults to one month', Utilities::ONE_MONTH);
      $input->setArgument('update_frequency', $update_frequency);
    }
  }

  /**
   * Reset the API parsing queue.
   *
   * @command api:reset-queue
   * @aliases apirq
   */
  public function resetQueue() {
    $this->emptyParsingQueue();
    $this->logger()->success(dt('The contents of the queue "@queue" were deleted.', [
      '@queue' => Parser::QUEUE_PARSE,
    ]));
  }

  /**
   * Empties parsing queue.
   */
  protected function emptyParsingQueue() {
    $queue_factory = \Drupal::service('queue');
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queue_factory->get(Parser::QUEUE_PARSE);
    $queue->deleteQueue();
  }

  /**
   * Mark all branches to be reparsed in the next run.
   *
   * @command api:re-parse
   * @aliases apirp
   */
  public function reParse() {
    // Remove everything in the queue first.
    $this->emptyParsingQueue();

    // And now go through branches and clean the queued value.
    $counter = 0;
    $all = $this->getAllBranches();
    foreach ($all as $branches) {
      foreach ($branches as $branch) {
        if (method_exists($branch, 'reParse')) {
          $branch->reParse();
          $counter++;
        }
      }
    }

    $this->logger()->success(dt('@count branches were set to be re-parsed.', [
      '@count' => $counter,
    ]));
  }

  /**
   * Create a project and branches from a git URL.
   *
   * @param string $repo_url
   *   Url of the repo.
   * @param string $type
   *   Type of project.
   *
   * @command api:quick-wizard
   * @aliases apiqw
   */
  public function quickWizard($repo_url, $type = 'module') {
    /** @var \Drupal\api\Utilities $utilities */
    $utilities = \Drupal::service('api.utilities');

    if (!UrlHelper::isValid($repo_url)) {
      $this->logger()->error(dt('The given URL is not valid. Use "https" format.'));
    }

    // Check if project is already there.
    $project_name = $utilities->gitProjectName($repo_url);
    $exists = Project::exists($project_name);
    if ($exists) {
      $project = Project::getBySlug($project_name);
      $results = $utilities->gitFetchBranches($project);
      if (empty($results)) {
        $this->logger()->error(dt('Branches could not be pulled or checked out.'));
      }
    }
    else {
      $results = $utilities->gitClone($repo_url);
      if (empty($results)) {
        $this->logger()->error(dt('There was an error cloning the repository.'));
      }
      else {
        // Repo was cloned to the filesystem, create the project now.
        $formatted_name = Unicode::ucwords(str_replace(['-', '_'], [' ', ' '], $project_name));

        /** @var \Drupal\api\Interfaces\ProjectInterface $project */
        $project = Project::create();
        $project
          ->setTitle($formatted_name)
          ->setType($type)
          ->setSlug($project_name)
          ->save();
        $this->logger()->success(dt('Project ' . $project_name . ' was created.'));
      }
    }

    if (!empty($results) && ($project instanceof ProjectInterface)) {
      foreach ($results as $branch_info) {
        $base_folder = substr($branch_info['folder'], 0, -1 * (strlen($branch_info['branch']) + 1));

        $branch_folder = $utilities->createBranchFolder(
          $branch_info['branch'],
          $base_folder,
          $branch_info['repo']
        );

        if ($branch_folder == $branch_info['folder']) {
          $core_compatibility = $project->isCore() ?
            $branch_info['branch'] :
            $utilities->getCoreCompatibility($branch_info['folder'], $project->getSlug());

          if ($project->isCore()) {
            $utilities->addExtraDocumentation($branch_info['folder'], $branch_info['branch'], $project);
          }

          /** @var \Drupal\api\Interfaces\BranchInterface $branch */
          $branch = Branch::getBySlug($branch_info['branch'], $project) ?? Branch::create();
          $branch
            ->setTitle($branch_info['branch'])
            ->setSlug($branch_info['branch'])
            ->setCoreCompatibility($core_compatibility)
            ->setProject($project)
            ->setDirectories($branch_info['folder'])
            ->save();
          $this->logger()->success(dt('* Branch ' . $branch_info['branch'] . ' was created.'));
        }
      }
    }
  }

  /**
   * Gets all branches from all types defined in the api module.
   *
   * @return array
   *   Arrays of branches organised per type.
   */
  protected function getAllBranches() {
    return [
      'branch' => Branch::loadMultiple(),
      'php_branch' => PhpBranch::loadMultiple(),
      'external_branch' => ExternalBranch::loadMultiple(),
    ];
  }

  /**
   * List all branches available and their type.
   *
   * @param array $options
   *   Options for the output.
   *
   * @field-labels
   *   branch: Branch
   *   type: Type
   * @default-fields branch,type
   *
   * @command api:list-branches
   * @aliases apilb
   *
   * @filter-default-field branch
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Rows to be rendered as output.
   */
  public function listBranches(array $options = ['format' => 'table']) {
    $all = $this->getAllBranches();
    foreach ($all as $type => $branches) {
      foreach ($branches as $branch) {
        $project = '';
        if (method_exists($branch, 'getProject')) {
          $project = ' @ ' . $branch->getProject()->label();
        }
        $rows[] = [
          'branch' => $branch->label() . $project,
          'type' => $type,
        ];
      }
    }
    return new RowsOfFields($rows);
  }

  /**
   * Count contents of API queues.
   *
   * @param array $options
   *   Options for the output.
   *
   * @field-labels
   *   queue: Queue
   *   count: Count
   * @default-fields queue,count
   *
   * @command api:count-queues
   * @aliases apicq
   *
   * @filter-default-field queue
   *
   * @return \Consolidation\OutputFormatters\StructuredData\RowsOfFields
   *   Rows to be rendered as output.
   */
  public function countQueues(array $options = ['format' => 'table']) {
    $queue_factory = \Drupal::service('queue');
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $queue_factory->get(Parser::QUEUE_PARSE);

    $rows[] = [
      'queue' => Parser::QUEUE_PARSE,
      'count' => $queue->numberOfItems(),
    ];

    return new RowsOfFields($rows);
  }

}
