<?php

namespace Drupal\api;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\PhpBranch;
use Drupal\api\Entity\ExternalBranch;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\Component\Serialization\Json;
use Drupal\Core\Datetime\DateFormatterInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\File\FileSystem;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use GuzzleHttp\Client;
use PhpParser\Comment\Doc as CommentDoc;
use PhpParser\Node\Stmt\Function_ as NodeFunction;
use PhpParser\Node\Stmt\Class_ as NodeClass;
use PhpParser\Node\Stmt\ClassLike as NodeClassLike;
use PhpParser\ParserFactory;
use PhpParser\Error as ParserError;
use PhpParser\PrettyPrinter\Standard as StandardPrettyPrinter;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Finder\SplFileInfo;
use Symfony\Component\Yaml\Parser as YamlParser;
use Symfony\Component\Yaml\Dumper as YamlDumper;

/**
 * Parser service containing utilities to parse the code.
 *
 * The main goal from the parser will be to scan the files, produce docblocks
 * and store that information into queues. This way we don't need to worry about
 * any abstractions of objects, entities, dependencies... and therefore making
 * the logic a bit simpler.
 *
 * @package Drupal\api
 */
class Parser {
  use StringTranslationTrait;

  /**
   * Name of the queue to parse branches.
   *
   * @var string
   */
  const QUEUE_PARSE = 'api_parse_queue';

  /**
   * Regular expression for starting inline \@tags.
   *
   * @var string
   */
  const RE_TAG_START = '(?<!\\\)@';

  /**
   * Regular expression for matching file names with one or more extensions.
   *
   * @var string
   */
  const RE_FILENAME = '([a-zA-Z0-9_-]+(?:\.[a-zA-Z0-9_-]+)+)';

  /**
   * Regular expression for matching PHP functions and methods in text.
   *
   * These are patterns like ClassName::methodName(), or just function_name().
   * Possibly with namespaces. Doesn't include the ().
   *
   * @var string
   */
  const RE_FUNCTION_IN_TEXT = '\\\\*[a-zA-Z_\x7f-\xff][\\\\a-zA-Z0-9_\x7f-\xff:]*';

  /**
   * Regular expression for matching characters interior to function names.
   *
   * @var string
   */
  const RE_FUNCTION_CHARACTERS = '[a-zA-Z0-9_\x7f-\xff]+';

  /**
   * Regular expression to match PHP function names, without delimiters.
   *
   * Former (deprecated) DRUPAL_PHP_FUNCTION_PATTERN.
   *
   * @var string
   */
  const PHP_FUNCTION_PATTERN = '[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*';

  /**
   * Regular expression for word boundary start for API linking.
   *
   * @var string
   */
  const RE_WORD_BOUNDARY_START = '(?<=^|[\s\(@\>|])';

  /**
   * Regular expression for word boundary end for API linking.
   *
   * @var string
   */
  const RE_WORD_BOUNDARY_END = '(?=$|[\s.,:;?!)\<\[\|])';

  /**
   * DateFormatterInterface definition.
   *
   * @var \Drupal\Core\Datetime\DateFormatterInterface
   */
  protected $dateFormatter;

  /**
   * ModuleHandlerInterface definition.
   *
   * @var \Drupal\Core\Extension\ModuleHandlerInterface
   */
  protected $moduleHandler;

  /**
   * ConfigFactoryInterface definition.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Utilities definition.
   *
   * @var \Drupal\api\Utilities
   */
  protected $utilities;

  /**
   * The queue object.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected $time;

  /**
   * Http client.
   *
   * @var \Guzzle\Client
   */
  protected $httpClient;

  /**
   * Parsing queue.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $parseQueue;

  /**
   * File system service.
   *
   * @var \Drupal\Core\File\FileSystem
   */
  protected $fileSystem;

  /**
   * PHP Parser factory object.
   *
   * @var \PhpParser\ParserFactory
   */
  protected $phpParser;

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
   * Parser constructor.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   Configuration service.
   * @param \Drupal\Core\Datetime\DateFormatterInterface $date_formatter
   *   Date formatter service.
   * @param \Drupal\Core\Extension\ModuleHandlerInterface $module_handler
   *   Module handler service.
   * @param \Drupal\api\Utilities $utilities
   *   Utilities service.
   * @param \Drupal\Core\Queue\QueueFactory $queue_factory
   *   Queue factory service to get new/existing queues for use.
   * @param \Drupal\Component\Datetime\TimeInterface $time
   *   Time service.
   * @param \GuzzleHttp\Client $http_client
   *   Http client.
   * @param \Drupal\Core\File\FileSystem $file_system
   *   File system service.
   * @param \Drupal\Core\Logger\LoggerChannelFactoryInterface $logger_service
   *   Logger service.
   */
  public function __construct(
    ConfigFactoryInterface $config_factory,
    DateFormatterInterface $date_formatter,
    ModuleHandlerInterface $module_handler,
    Utilities $utilities,
    QueueFactory $queue_factory,
    TimeInterface $time,
    Client $http_client,
    FileSystem $file_system,
    LoggerChannelFactoryInterface $logger_service
  ) {
    $this->configFactory = $config_factory;
    $this->dateFormatter = $date_formatter;
    $this->moduleHandler = $module_handler;
    $this->utilities = $utilities;
    $this->queueFactory = $queue_factory;
    $this->time = $time;
    $this->httpClient = $http_client;
    $this->fileSystem = $file_system;
    $this->loggerService = $logger_service;
    $this->logger = $this->loggerService->get('api');
    $this->phpParser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
    $this->parseQueue = $this->queueFactory->get(self::QUEUE_PARSE);
    $this->parseQueue->createQueue();
  }

  /**
   * Triggers parsing on all the entities that can be parsed.
   *
   * @return array
   *   Array with the different results for the different entities parsed.
   */
  public function parseAll() {
    $limit = (int) $this->configFactory->get('api.settings')->get('branches_per_cron');
    return [
      'branch' => $this->parseBranches($limit),
      'php_branch' => $this->parsePhpBranches(),
      'external_branch' => $this->parseExternalBranches(),
    ];
  }

  /**
   * Triggers the parsing for branches.
   *
   * @param int $limit
   *   Max number of branches to parse. 0 to parse all (it might time out).
   *
   * @return array
   *   Results from the parsing.
   */
  public function parseBranches(int $limit = 0) {
    $limit = ($limit <= 0) ? 1000 : $limit;
    $results = [];
    $parsed_count = 0;
    $limit_reached = FALSE;

    $branches = Branch::loadMultiple() ?? [];
    foreach ($branches as $branch) {
      /** @var \Drupal\api\Entity\Branch $branch */
      $parsed = FALSE;
      $docblock_counter = 0;
      $needs_parsing = $this->needsParsing($branch);

      if ($needs_parsing && $limit_reached == FALSE) {
        $parse_functions = $this->parseFunctions();
        $files_to_scan = $this->filesToScan($branch);
        if ($files_to_scan->hasResults()) {
          foreach ($files_to_scan as $file) {
            /** @var \Symfony\Component\Finder\SplFileInfo $file */
            $extension = $file->getExtension();
            if (!empty($parse_functions[$extension])) {
              $parseFunction = $parse_functions[$extension];
              $baseFileDocblock = $this->parseFile($file, $branch->getExcludeDrupalismRegexp(TRUE));
              if (!empty($baseFileDocblock)) {
                $docblock_info = [
                  'branch_id' => $branch->id(),
                  'branch_type' => $branch->getEntityTypeId(),
                  'action' => 'parse',
                  'data' => $this->$parseFunction($baseFileDocblock) ?? [],
                ];
                $docblock_counter++;
                $this->parseQueue->createItem($docblock_info);
              }
            }
          }
        }

        $parsed = TRUE;
        $branch
          ->setQueued($this->time->getCurrentTime())
          ->save();
        $parsed_count++;
        $limit_reached = ($parsed_count >= $limit);

        // In the D7 version, a lot was calculated on saving (see "api_shutdown"
        // function). Here, we will just queue the next actions after all the
        // files have been set for parsing.
        $counts_info = [
          'branch_id' => $branch->id(),
          'branch_type' => $branch->getEntityTypeId(),
          'action' => 'class_relations',
        ];
        $this->parseQueue->createItem($counts_info);

        // Branch fully parsed, so we can calculate counts. As the queues are
        // FIFO, we just add it after all the other items have been added.
        $counts_info = [
          'branch_id' => $branch->id(),
          'branch_type' => $branch->getEntityTypeId(),
          'action' => 'calculate_counts',
        ];
        $this->parseQueue->createItem($counts_info);
      }

      $label = $branch->getProject() ?
        $branch->getProject()->label() . ' - ' . $branch->label() :
        $branch->label();
      $results[$branch->id()] = [
        'label' => $label,
        'docblock_count' => $docblock_counter,
        'parsed' => $parsed,
        'needs_parsing' => $needs_parsing,
        'limit_exceeded' => (!$parsed && $limit_reached),
      ];
    }

    return $results;
  }

  /**
   * Triggers the parsing for PHP branches.
   *
   * @return array
   *   Results from the parsing.
   */
  public function parsePhpBranches() {
    $results = [];

    $branches = PhpBranch::loadMultiple() ?? [];
    foreach ($branches as $branch) {
      /** @var \Drupal\api\Entity\PhpBranch $branch */
      $parsed = FALSE;
      $docblock_counter = 0;
      $needs_parsing = $this->needsParsing($branch);
      if ($needs_parsing) {
        $response = $this->httpClient->get($branch->getFunctionList(), [
          'timeout' => 60,
        ]);
        if ($response->getStatusCode() == 200) {
          $data = Json::decode($response->getBody()->getContents()) ?? [];
          foreach ($data as $name => $item) {
            $docblock_info = [
              'branch_id' => $branch->id(),
              'branch_type' => $branch->getEntityTypeId(),
              'action' => 'parse',
              'data' => [
                'object_type' => 'function',
                'object_name' => $name,
                'documentation' => $item['prototype'] . "\n  " . $item['purpose'],
                'member_name' => '',
              ],
            ];
            $docblock_counter++;
            $this->parseQueue->createItem($docblock_info);
          }
          $parsed = TRUE;
          $branch
            ->setQueued($this->time->getCurrentTime())
            ->save();
        }
      }

      $results[$branch->id()] = [
        'label' => $branch->label(),
        'docblock_count' => $docblock_counter,
        'parsed' => $parsed,
        'needs_parsing' => $needs_parsing,
      ];
    }

    return $results;
  }

  /**
   * Triggers the parsing for External branches.
   *
   * @return array
   *   Results from the parsing.
   */
  public function parseExternalBranches() {
    $results = [];

    $branches = ExternalBranch::loadMultiple() ?? [];
    foreach ($branches as $branch) {
      /** @var \Drupal\api\Entity\ExternalBranch $branch */
      $parsed = FALSE;
      $docblock_counter = 0;
      $needs_parsing = $this->needsParsing($branch);
      if ($needs_parsing) {
        $limit = $branch->getItemsPerPage() ?? 0;
        $timeout = $branch->getTimeout() ?? 0;
        if ($limit <= 0) {
          $limit = 2000;
        }
        if ($timeout <= 0) {
          $timeout = 30;
        }

        $reference_url = $branch->getFunctionList();
        $reference_url = $reference_url .
          ((strpos($reference_url, '?') > 0) ? '&' : '?') .
          'limit=' . $limit . '&page=';

        $done = [];
        $page = 0;
        $found = TRUE;

        // Continue in this loop as long as the last response was successful and
        // contained new data, but as a failsafe stop after 1000 pages.
        while ($found && $page < 1000) {
          $found = FALSE;
          try {
            $response = $this->httpClient->get($reference_url . $page, [
              'timeout' => $timeout,
            ]);
          }
          catch (\Throwable $e) {
            // End-point not reachable, or forbidden. Loop will just end.
            $response = FALSE;
          }

          $page++;
          if ($response && $response->getStatusCode() == 200) {
            $data = Json::decode($response->getBody()->getContents()) ?? [];
            foreach ($data as $item) {
              // Only save new items.
              $key = $item['url'] . '..' . $item['object_type'] . '..' . $item['namespaced_name'];
              if (!isset($done[$key])) {
                $found = TRUE;
                $done[$key] = TRUE;
                $docblock_info = [
                  'branch_id' => $branch->id(),
                  'branch_type' => $branch->getEntityTypeId(),
                  'action' => 'parse',
                  'data' => $item,
                ];
                $docblock_counter++;
                $this->parseQueue->createItem($docblock_info);
              }
            }
          }
        }

        $parsed = TRUE;
        $branch
          ->setQueued($this->time->getCurrentTime())
          ->save();
      }

      $results[$branch->id()] = [
        'label' => $branch->label(),
        'docblock_count' => $docblock_counter,
        'parsed' => $parsed,
        'needs_parsing' => $needs_parsing,
      ];
    }

    return $results;
  }

  /**
   * Returns whether a branch needs parsing or not.
   *
   * @param \Drupal\api\Interfaces\PhpBranchInterface|\Drupal\api\Interfaces\ExternalBranchInterface $branch
   *   Branch to check.
   *
   * @return bool
   *   Whether the given branch needs parsing or not.
   */
  public function needsParsing($branch) {
    return (
      empty($branch->getQueued()) ||
      (($branch->getQueued() + $branch->getUpdateFrequency()) < $this->time->getCurrentTime())
    );
  }

  /**
   * Returns the files that can be scanned based on the branch configuration.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object containing path information.
   *
   * @return \Symfony\Component\Finder\Finder
   *   Finder object which can be used to loop through files.
   */
  public function filesToScan(BranchInterface $branch) {
    $paths = $branch->getDirectories(TRUE);
    $excluded_paths = $branch->getExcludedDirectories(TRUE);
    $excluded_regex = $branch->getExcludeFilesRegexp(TRUE);
    $finder = new Finder();
    $finder->in($paths);

    // Exclude param MUST be relative as per Finder documentation:
    // https://symfony.com/doc/current/components/finder.html#location
    // As both paths and excluded could be multiple value we try the excluded
    // paths against the included paths and try to get the relative address.
    // Github issue: https://github.com/symfony/symfony/issues/34894
    foreach ($excluded_paths as $excluded_path) {
      foreach ($paths as $path) {
        if (str_contains($excluded_path, $path)) {
          $relative_path = ltrim(str_replace($path, '', $excluded_path), '/\\');
          $finder->exclude($relative_path);
        }
      }
    }

    $finder->notPath($excluded_regex);

    return $finder->files();
  }

  /**
   * Reads in a file and returns a base object to use in parsing functions.
   *
   * @param \Symfony\Component\Finder\SplFileInfo $file
   *   File to parse.
   * @param array $drupal_regexps
   *   Regular expressions to exclude Drupalisms.
   *
   * @return array
   *   Base docblock structure for the file.
   */
  public function parseFile(SplFileInfo $file, array $drupal_regexps = []) {
    // See if this is a Drupal file or a Drupal-excluded file.
    $is_drupal = TRUE;
    foreach ($drupal_regexps as $regexp) {
      if (preg_match($regexp, $file->getPathname())) {
        $is_drupal = FALSE;
        break;
      }
    }

    $basename = $this->fileSystem->basename($file->getPathname());
    $source = $file->getContents();

    return [
      'object_name' => $file->getFilename(),
      'object_type' => 'file',
      'file_name' => $file->getRelativePathname(),
      'title' => $basename,
      'basename' => $basename,
      'documentation' => '',
      'references' => [],
      'modified' => $file->getMTime(),
      'source' => str_replace(
        ["\r\n", "\r"],
        ["\n", "\n"],
        $source
      ),
      'content' => '',
      'class' => '',
      'namespaced_name' => '',
      'modifiers' => '',
      'is_drupal' => $is_drupal,
      'code' => '',
    ];
  }

  /**
   * Returns the list of parsing functions for file extensions.
   *
   * @return array
   *   An associative array whose keys are file extensions, and whose values are
   *   the functions used to parse files with that extension.
   *
   *   The function should parse the contents of the file, and return
   *   documentation objects.
   */
  public function parseFunctions() {
    $functions = [
      'php' => 'parsePhp',
      'module' => 'parsePhp',
      'inc' => 'parsePhp',
      'install' => 'parsePhp',
      'engine' => 'parsePhp',
      'theme' => 'parsePhp',
      'profile' => 'parsePhp',
      'test' => 'parsePhp',
      'yml' => 'parseYaml',
      'txt' => 'parseText',
      'info' => 'parseText',
      'css' => 'parseText',
      'sh' => 'parseText',
      'twig' => 'parseTwig',
      'htm' => 'parseHtml',
      'html' => 'parseHtml',
    ];

    // Let other modules add/edit parsing options.
    $this->moduleHandler->alter('api_parse_functions', $functions);

    return $functions;
  }

  /**
   * Parses a Php file and returns its docblock information.
   *
   * @param array $docblock
   *   Information about the file to parse.
   *
   * @return array
   *   Docblock array containing the different elements within the file.
   */
  public function parsePhp(array $docblock) {
    $error_logged = FALSE;
    $statements = FALSE;
    try {
      $statements = $this->phpParser->parse($docblock['source']);
    }
    catch (ParserError $e) {
      $error_logged = TRUE;
      $this->logger->error('File @name could not be parsed. Message: %msg',
        [
          '@name' => $docblock['file_name'],
          '%msg' => $e->getMessage(),
        ]);
    }

    if ($statements && is_array($statements)) {
      // Find all the references in the whole file. We will omit the ones that
      // belong to particular functions etc. in the file.
      $references = $this->findPhpReferences($statements, $docblock['is_drupal'], $docblock['file_name']);

      // Make the first doc block be for the file as a whole.
      $docblock['code'] = Formatter::formatStatements($statements, $docblock['is_drupal'], TRUE);
      $docblocks = [$docblock];

      // Set default documentation block array for items other than the file.
      $default_block = [
        'file_name' => $docblock['file_name'],
        'class' => '',
        'object_type' => '',
        'documentation' => '',
        'references' => [],
        'see' => '',
        'deprecated' => '',
        'start_line' => 0,
        'namespaced_name' => '',
        'modifiers' => '',
        'is_drupal' => $docblock['is_drupal'],
        'code' => '',
      ];

      $found_references = $this->buildPhpDocblocks($statements, $default_block, $docblocks);

      // For the file, save only the references not found in parsing the
      // statements within the file. This will only save references to function
      // calls in the global area of the file, excluding those found in
      // functions declared in the file. This doesn't apply to namespace and use
      // references though -- these are found only in ::buildPhpDocblocks().
      foreach ($found_references as $type => $list) {
        // For namespaces and use aliases, merge these into the main references.
        if ($type == 'namespace' || $type == 'use_alias') {
          $refs = [$type => $list];
          $references = $this->mergeReferences($references, $refs, $docblock['file_name']);
        }
        else {
          // For other references, remove them.
          foreach ($list as $name => $info) {
            unset($references[$type][$name]);
          }
        }
      }
      $docblocks[0]['references'] = $references;
    }
    else {
      // We at least want to save the empty docblock so the file record is
      // updated.
      $docblocks = [$docblock];
      if (!$error_logged) {
        $this->logger->warning('File @name had no statements.',
          [
            '@name' => $docblock['file_name'],
          ]);
      }
    }

    return $docblocks;
  }

  /**
   * Parses a php snippet.
   *
   * @param string $code
   *   Code to parse.
   *
   * @return \PhpParser\Node\Stmt[]|null
   *   Array of statements or NULL.
   */
  public function parsePhpCode($code) {
    try {
      $statements = $this->phpParser->parse("<?php " . $code . " ?>");
    }
    catch (ParserError $e) {
      // Not valid PHP code.
      return NULL;
    }

    return $statements;
  }

  /**
   * Parses a Yaml file and returns its docblock information.
   *
   * @param array $docblock
   *   Information about the file to parse.
   *
   * @return array
   *   Docblock array containing the different elements within the file.
   */
  public function parseYaml(array $docblock) {
    // Just use the file name as the documentation, since the file contents
    // are not good documentation.
    $bare_docblock = $docblock;
    $docblock['documentation'] = $docblock['file_name'];

    // Parse the YAML in the file.
    try {
      $parsed = (new YamlParser())->parse($docblock['source']);
    }
    catch (\Exception $e) {
      $parsed = [];
      $this->logger->error('YAML parsing failed for %filename with message %msg',
        [
          '%filename' => $docblock['file_name'],
          '%msg' => $e->getMessage(),
        ]);
    }

    // Find potential references, which are array values that look like they
    // could be callback function names, in addition to some array keys. The
    // level of keys we want depends on (a) if it's a Drupal file, and (b) the
    // file extension.
    $key_level = 0;
    $is_services = FALSE;
    if ($docblock['is_drupal']) {
      $basename = $docblock['basename'];
      $matches = [];
      if (preg_match('|\.([^.]+)\.yml$|', $basename, $matches)) {
        if ($matches[1] == 'services') {
          // We want to keep references to 2nd-level keys in services.yml files.
          $key_level = 2;
          $is_services = TRUE;
        }
        elseif ($matches[1] == 'routing' || $matches[1] == 'local_tasks' || $matches[1] == 'contextual_links') {
          // We want to keep references to 1st-level keys in routing.yml and
          // related files.
          $key_level = 1;
        }
      }
    }

    $references = $this->findYamlReferences($parsed, $key_level, $docblock['file_name']);
    // For services files, we do not want the YML strings references, because
    // really only the services are relevant.
    if ($is_services) {
      unset($references['yaml string']);
    }
    $docblock['references'] = $references;

    // Format the code, number the lines and put into a code block.
    // Escape HTML tags and entities.
    $code = $docblock['source'];
    $code = htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8');
    $code = Formatter::formatYamlCode($code, $references);
    $code = Formatter::numberLines($code);
    $code = Formatter::wrapPhpCode($code);
    $code = Formatter::validateEncoding($code);
    $docblock['code'] = $code;
    $full_references = $references;

    $docblocks = [$docblock];

    // For services files, make docblocks for each service.
    if ($is_services && isset($parsed['services'])) {
      $docblocks = array_merge(
        $docblocks,
        $this->extractServices($parsed, $full_references, $bare_docblock)
      );
    }

    return $docblocks;
  }

  /**
   * Recursively finds potential references in a parsed YAML array.
   *
   * @param mixed $yaml
   *   Parsed YAML object.
   * @param int $key_refs_level
   *   Store references to the keys on this level, where 1 is the current level.
   * @param string $filename
   *   File name for watchdog messages.
   *
   * @return array
   *   Array of references suitable for use in $docblock['references'].
   */
  protected function findYamlReferences($yaml, $key_refs_level, $filename) {
    if (empty($yaml)) {
      return ['potential callback' => []];
    }

    $yaml = (array) $yaml;
    $references = [
      'potential callback' => [],
      'yaml string' => [],
    ];
    foreach ($yaml as $key => $value) {
      if ($key_refs_level == 1 && is_string($key)) {
        $key = trim($key);
        if ($key) {
          $references['yaml string'][$key] = $key;
        }
      }
      if (is_string($value)) {
        $matches = [];
        if (preg_match("|^['\"]*(" . self::RE_FUNCTION_IN_TEXT . ")['\"]*$|", $value, $matches)) {
          // Special case the commonly-found TRUE and FALSE.
          $val = trim($matches[1]);
          if ($val && $val != 'TRUE' && $val != 'FALSE' && !is_numeric($val)) {
            $references['potential callback'][$val] = $val;
          }
        }
      }
      elseif (is_array($value) || is_object($value)) {
        $references = $this->mergeReferences(
          $references,
          $this->findYamlReferences($value, $key_refs_level - 1, $filename),
          $filename
        );
      }
    }

    return $references;
  }

  /**
   * Merges references, with warnings for duplicate namespaces.
   *
   * @param array $master
   *   Master list of references.
   * @param array $new
   *   New references to merge in.
   * @param string $filename
   *   File name for watchdog messages.
   *
   * @return array
   *   Merged references. References in $new are appended to references in
   *   $master, and if there are duplicate namespace or use references, the
   *   $master list is used and a warning is generated.
   */
  protected function mergeReferences(array $master, array $new, $filename) {
    // We're supporting only one namespace declaration per file.
    if (isset($master['namespace']) && isset($new['namespace'])) {
      if ($master['namespace'] != $new['namespace']) {
        $this->logger->warning('Multiple namespace declarations found in file @file. Only first is used.',
          [
            '@file' => $filename,
          ]);
      }
      unset($new['namespace']);
    }

    // We're supporting only non-conflicting use declarations.
    if (isset($new['use_alias']) && isset($master['use_alias'])) {
      foreach ($new['use_alias'] as $alias => $class) {
        if (isset($master['use_alias'][$alias]) && $master['use_alias'][$alias] != $class) {
          $this->logger->warning('Conflicting use declarations for %name found in file @file. Only first is used.',
            [
              '%name' => $alias,
              '@file' => $filename,
            ]);
          unset($new['use_alias'][$alias]);
        }
      }
    }

    // Use array_replace_recursive here so we do not get duplicate references.
    return array_replace_recursive($master, $new);
  }

  /**
   * Parses a Text file and returns its docblock information.
   *
   * @param array $docblock
   *   Information about the file to parse.
   * @param bool $escape_html
   *   If TRUE, escpae HTML characters in the source code listing.
   *
   * @return array
   *   Docblock array containing the different elements within the file.
   */
  public function parseText(array $docblock, $escape_html = FALSE) {
    // See if the file contains an @file block, and use that for the
    // documentation if so; otherwise, just use the file as a whole. This is
    // probably only present for Twig files.
    $matches = [];
    if (preg_match('|/\*\*[\s\*]+@file.+\*/|Us', $docblock['source'], $matches)) {
      $docblock['content'] = Formatter::cleanComment($matches[0]);
    }
    else {
      $tmp = [];
      $docblock['documentation'] = Formatter::formatDocumentation($docblock['source'], FALSE, $tmp);
    }

    // Escape HTML and number the lines.
    $output = $docblock['source'];
    if ($escape_html) {
      $output = htmlspecialchars($docblock['source'], ENT_NOQUOTES, 'UTF-8');
    }
    $output = Formatter::numberLines($output);
    $output = Formatter::wrapPhpCode($output);
    $output = Formatter::validateEncoding($output);
    $docblock['code'] = $output;

    return [
      $docblock,
    ];
  }

  /**
   * Parses a Twig file and returns its docblock information.
   *
   * @param array $docblock
   *   Information about the file to parse.
   *
   * @return array
   *   Docblock array containing the different elements within the file.
   */
  public function parseTwig(array $docblock) {
    // Use the text file function, but escape HTML characters.
    return $this->parseText($docblock, TRUE);
  }

  /**
   * Parses a Html file and returns its docblock information.
   *
   * @param array $docblock
   *   Information about the file to parse.
   *
   * @return array
   *   Docblock array containing the different elements within the file.
   */
  public function parseHtml(array $docblock) {
    $code = $docblock['source'];
    $code = Formatter::validateEncoding($code);
    $docblock['code'] = '<pre>' . htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8') . '</pre>';

    $title_match = [];
    if (preg_match('!<title>([^<]+)</title>!is', $code, $title_match)) {
      $docblock['title'] = trim($title_match[1]);
      $docblock['summary'] = $docblock['title'];
    }
    $documentation_match = [];
    if (preg_match('!<body>(.*?</h1>)?(.*)</body>!is', $code, $documentation_match)) {
      $docblock['documentation'] = $documentation_match[2];
    }

    return [
      $docblock,
    ];
  }

  /**
   * Parses the additional services on a yaml file.
   *
   * @param array $parsed
   *   Parsed information object.
   * @param array $full_references
   *   Full detailed references.
   * @param array $bare_docblock
   *   Bare docblock array.
   *
   * @return array
   *   Additional docblock elements from parsing services.
   */
  protected function extractServices(array $parsed, array $full_references, array $bare_docblock) {
    $docblocks = [];
    $dumper = new YamlDumper();

    foreach ($parsed['services'] as $name => $info) {
      try {
        $code = $dumper->dump($info, 2, 2);
      }
      catch (\Exception $e) {
        // We should be able to dump, but just in case, fall back to printing,
        // which is better than nothing.
        $code = print_r($info, TRUE);
      }
      $code = htmlspecialchars($code, ENT_NOQUOTES, 'UTF-8');
      $code = Formatter::formatYamlCode($code, $full_references);
      $code = Formatter::numberLines($code);
      $code = Formatter::wrapPhpCode($code);
      $code = Formatter::validateEncoding($code);

      $references = ['service_tag' => []];
      $class = '';
      if (isset($info['class']) && $info['class']) {
        $class = $info['class'];
        // Make sure the class name starts with a backslash.
        $ref = $class;
        $pos = strpos($ref, '\\');
        if ($pos !== 0) {
          $ref = '\\' . $ref;
        }

        $references['service_class'] = [$ref];
      }
      $alias = (isset($info['alias']) && $info['alias']) ? $info['alias'] : '';
      $abstract = (isset($info['abstract']) && $info['abstract']);

      if (isset($info['tags'])) {
        foreach ($info['tags'] as $tag) {
          $tag_name = $tag['name'];
          if ($tag_name) {
            $references['service_tag'][$tag_name] = $tag_name;
          }
        }
      }

      $service = [
        'object_name' => $name,
        'title' => $name,
        'object_type' => 'service',
        'code' => $code,
        'source' => '',
        'documentation' => $class ? $class : ($alias ? $this->t('Alias of %alias', ['%alias' => $alias]) : ($abstract ? $this->t('Abstract') : '')),
        'references' => $references,
      ] + $bare_docblock;

      $docblocks[] = $service;
    }

    return $docblocks;
  }

  /**
   * Traverses PHP statements to find references.
   *
   * @param array $statements
   *   Array of statements to traverse from PhpParser parsing.
   * @param bool $is_drupal
   *   TRUE if this is Drupal code; FALSE if not. This turns on recognition of
   *   things like hooks and theme calls.
   * @param string $filename
   *   File name for watchdog messages.
   * @param array $state
   *   (optional) Array to keep track of state for recursive calls.
   *
   * @return array
   *   Array of references found. References are hook invocations, function
   *   calls, etc., and they are put into an associative array where the keys
   *   are the types of references ('function', 'potential hook', etc.), and
   *   the values are arrays of the names of this type that were found.
   */
  public function findPhpReferences(array $statements, $is_drupal, $filename, array $state = []) {
    $references = [];

    $invoke_function_info = PrettyPrinter::invokeFunctions();
    $statement_count = 0;

    foreach ($statements as $statement) {
      $statement_count++;
      if (!$statement || !is_object($statement)) {
        // This could happen if some of the "sub-statements" in a recursive call
        // were actually empty or scalars.
        continue;
      }

      $type = $statement->getType();
      $sub_statements = NULL;
      $sub_state = $state;

      // Find references that are directly in this statement.
      if ($type == 'Expr_FuncCall') {
        // Function call. Only store a reference if it is a directly-
        // named function, not a variable.
        $name = Formatter::asString($statement->name);
        if ($name) {
          $references['function'][$name] = $name;
          if ($is_drupal && isset($invoke_function_info[$name])) {
            $sub_state['invoke_call'] = $invoke_function_info[$name];
          }
        }

        if (!empty($statement->args)) {
          $sub_statements = $statement->args;
        }
      }
      elseif ($type == 'Expr_MethodCall') {
        // Method call. Only store a reference if it is a directly-named method,
        // not a variable.
        $name = Formatter::asString($statement->name);
        // Save as a call reference if it's a method on $this.
        if ($name && $statement->var && isset($statement->var->name) && $statement->var->name == 'this') {
          $references['member-self'][$name] = $name;
        }
        if ($name && $is_drupal && isset($invoke_function_info[$name])) {
          $sub_state['invoke_call'] = $invoke_function_info[$name];
        }

        if (!empty($statement->args)) {
          $sub_statements = $statement->args;
        }
      }
      elseif ($type == 'Expr_StaticCall') {
        // Method call on a static class. Only save if it is a directly-named
        // method, not a variable, on a directly-named class or 'self'.
        $name = Formatter::asString($statement->name);
        $class = self::extractClassName($statement);
        if ($name && $class) {
          if ($class == 'self' || $class == 'static') {
            $references['member-self'][$name] = $name;
          }
          elseif ($class == 'parent') {
            $references['member-parent'][$name] = $name;
          }
          else {
            $references['member-class'][$class . '::' . $name] = $class . '::' . $name;
          }
          if ($is_drupal && isset($invoke_function_info[$name])) {
            $sub_state['invoke_call'] = $invoke_function_info[$name];
          }
        }

        if (!empty($statement->args)) {
          $sub_statements = $statement->args;
        }
      }
      elseif ($type == 'Expr_ConstFetch') {
        // Reference to a constant.
        $name = Formatter::asString($statement->name);
        $references['constant'][$name] = $name;
      }
      elseif ($type == 'Expr_ClassConstFetch') {
        // Reference to a class constant. Only store if it is a directly-named
        // class, not a variable like $myclass.
        $class = self::extractClassName($statement);
        if ($class) {
          $name = Formatter::asString($statement->name);
          $references['constant'][$name] = $class . '::' . $name;
        }
      }
      elseif ($type == 'Scalar_String') {
        $name = $statement->value;
        if ($name) {
          if (!empty($state['invoke_call'])) {
            $references['potential ' . $state['invoke_call'][0]][$name] = $name;
          }
          elseif ($is_drupal && isset($state['array_key']) && $state['array_key'] == '#theme') {
            $references['potential theme'][$name] = $name;
          }
          elseif ($is_drupal && isset($state['array_key']) && $state['array_key'] == '#type') {
            $references['potential element'][$name] = $name;
          }
          elseif (preg_match("|^" . self::PHP_FUNCTION_PATTERN . "$|", $name)) {
            $references['potential callback'][$name] = $name;
          }

          if (preg_match("|^" . self::RE_FILENAME . "$|", $name)) {
            // Some of these may be quite long, so truncate.
            $newname = mb_substr($name, 0, 127);
            $references['potential file'][$newname] = $newname;
          }
        }
      }
      elseif ($type == 'Arg') {
        // Function argument.
        $sub_statements = $statement->value;
      }
      elseif ($type == 'Expr_ArrayItem') {
        // Array item.
        if ($statement->key && $statement->key->getType() == 'Scalar_String') {
          $sub_state['array_key'] = $statement->key->value;
        }
        $sub_statements = $statement->value;
      }
      elseif ($statement instanceof NodeClassLike) {
        $sub_state['class'] = $statement->name;
        $sub_statements = $statement->stmts;
      }
      elseif (strpos($type, 'Scalar') !== 0) {
        // Handle things with sub-statements.
        $sub_types = [
          'expr',
          'left',
          'right',
          'vars',
          'items',
          'value',
          'stmts',
          'args',
          'cond',
          'if',
          'else',
          'elseifs',
          'init',
          'loop',
          'cases',
          'catches',
          'finally',
        ];

        $sub_statements = [];
        foreach ($sub_types as $thing) {
          if (isset($statement->$thing)) {
            $to_add = $statement->$thing;
            if (!is_array($to_add)) {
              $sub_statements[] = $to_add;
            }
            else {
              foreach ($to_add as $item) {
                $sub_statements[] = $item;
              }
            }
          }
        }
      }

      // Recursively find references in sub-statements.
      if (!empty($sub_statements)) {
        if (!is_array($sub_statements)) {
          $sub_statements = [$sub_statements];
        }
        $references = $this->mergeReferences(
          $references,
          $this->findPhpReferences($sub_statements, $is_drupal, $filename, $sub_state),
          $filename
        );
      }

      // After processing the argument of functions where a hook name could be,
      // remove the possibility of finding more matches in later arguments.
      if (isset($state['invoke_call']) &&
        is_array($state['invoke_call']) &&
        $statement_count >= $state['invoke_call'][1]) {
        $state['invoke_call'] = FALSE;
      }
    }

    return $references;
  }

  /**
   * Extracts the class name from a statement.
   *
   * @param object $statement
   *   Statement to extract the class name from.
   *
   * @return string
   *   Class name, if the statement has one; empty string otherwise.
   */
  public static function extractClassName($statement) {
    if (!$statement->class) {
      return '';
    }

    // $statement->class is an object, hopefully some type of a "name".
    $type = $statement->class->getType();
    if ($type != 'Name' && strpos($statement->class->getType(), 'Name_') !== 0) {
      return '';
    }

    $class = $statement->class->toString();
    if ($statement->class->isFullyQualified()) {
      $class = '\\' . $class;
    }

    return $class;
  }

  /**
   * Builds documentation blocks and finds references for parsed PHP code.
   *
   * @param array $statements
   *   An array of PHP parser output statements to look through.
   * @param array $default_block
   *   The default documentation block to use.
   * @param array $docblocks
   *   The array of documentation blocks, passed by reference. Documentation and
   *   code items found in the PHP statements are added to the end of the array.
   *
   * @return array
   *   An array of all the references found while parsing the statements.
   */
  public function buildPhpDocblocks(array $statements, array $default_block, array &$docblocks) {
    // Keep track of all references found.
    $all_references = [];

    // Traverse top-level statement list to gather documentation items.
    $in_class = !empty($default_block['class']);
    $class_prefix = $in_class ? $default_block['class'] . '::' : '';

    /** @var \PhpParser\Node\Stmt $statement */
    foreach ($statements as $statement) {
      $docblock = $default_block;
      $docblock['start_line'] = $statement->getLine();
      $docblock['content'] = '';
      $type = $statement->getType();

      // Process the comments for this statement. The parser makes an array of
      // all the comments that precede the statement; the last one is the doc
      // block (for statements that support doc blocks). Other than the official
      // doc bloc, other ones can be saved as global doc blocks, such as @file
      // and @defgroup doc blocks, but only if we are outside of classes.
      $comments = $statement->getAttribute('comments');
      $types_without_comments = [
        'Stmt_Nop',
        'Stmt_Namespace',
        'Stmt_Use',
      ];

      if ($comments) {
        $count = count($comments);
        foreach ($comments as $index => $comment) {
          if (!$in_class &&
            ($index < $count - 1 || in_array($type, $types_without_comments)) &&
            $comment instanceof CommentDoc) {

            // This is a global comment. Add it to the list of doc blocks.
            $comment_docblock = $default_block;
            $comment_docblock['content'] = Formatter::cleanComment($comment->getText());
            $comment_docblock['start_line'] = $comment->getStartLine();
            $docblocks[] = $comment_docblock;
          }
          elseif ($index == $count - 1 && $comment instanceof CommentDoc) {
            // This is the doc comment for this statement.
            $docblock['content'] = Formatter::cleanComment($comment->getText());
          }
        }
      }

      // Clear out the comments, so that we don't encounter them later.
      $statement->setAttribute('comments', []);

      // Process the actual statement.
      switch ($type) {
        case 'Expr_FuncCall':
        case 'Stmt_Expression':
          // Process this only if it is a call to define(CONST_NAME, value);.
          $name = (!empty($statement->name)) ? $statement->name->toString() : NULL;
          if (is_null($name) && !empty($statement->expr)) {
            $name = (!empty($statement->expr->name)) ? $statement->expr->name->toString() : NULL;
          }

          if ($name == 'define') {
            $args = !empty($statement->args) ? $statement->args : NULL;
            if (is_null($args) && !empty($statement->expr)) {
              $args = !empty($statement->expr->args) ? $statement->expr->args : [];
            }
            if (count($args) > 0) {
              $value = $args[0]->value;
              if (isset($value->value)) {
                $value = $value->value;
              }
              elseif (isset($value->name)) {
                $value = $value->name;
              }

              $docblock['object_type'] = 'constant';
              $docblock['member_name'] = Formatter::asString($value);
              $docblock['object_name'] = $class_prefix . $docblock['member_name'];
              $docblock['title'] = $docblock['object_name'];
              $docblock['code'] = Formatter::formatStatements([$statement], $docblock['is_drupal']);
              $docblocks[] = $docblock;
            }
          }
          break;

        case 'Stmt_ClassConst':
        case 'Stmt_Const':
        case 'Stmt_Property':
        case 'Stmt_Global':
          $sub_objects = [];
          $title_prefix = '';
          if ($type == 'Stmt_ClassConst' || $type == 'Stmt_Const') {
            $sub_objects = $statement->consts;
            $docblock['object_type'] = 'constant';
          }
          elseif ($type == 'Stmt_Property') {
            $sub_objects = $statement->props;
            $docblock['object_type'] = 'property';
            $title_prefix = '$';
          }
          elseif ($type == 'Stmt_Global') {
            $sub_objects = $statement->vars;
            $docblock['object_type'] = 'global';
            $title_prefix = '$';
          }

          if (!empty($sub_objects)) {
            $sub_object = $sub_objects[0];
            $docblock['member_name'] = Formatter::asString($sub_object->name);
            $docblock['object_name'] = $class_prefix . $docblock['member_name'];
            $docblock['title'] = $class_prefix . $title_prefix . $docblock['member_name'];
            $docblock['code'] = Formatter::formatStatements([$statement], $docblock['is_drupal']);
            if ($type != 'Stmt_Global' && $type != 'Stmt_Const') {
              $docblock['modifiers'] = $this->getStatementModifiers($statement);
            }

            $docblocks[] = $docblock;
          }
          break;

        case 'Stmt_Function':
        case 'Stmt_ClassMethod':
          $docblock['object_type'] = 'function';
          $docblock['member_name'] = Formatter::asString($statement->name);
          $docblock['object_name'] = $class_prefix . $docblock['member_name'];
          $docblock['title'] = $docblock['object_name'];
          $docblock['code'] = Formatter::formatStatements([$statement], $docblock['is_drupal']);
          $docblock['references'] = $this->findPhpReferences([$statement], $docblock['is_drupal'], $docblock['file_name']);
          $all_references = $this->mergeReferences($all_references, $docblock['references'], $docblock['file_name']);
          $docblock['signature'] = $this->getFunctionSignature($statement);
          if ($type == 'Stmt_ClassMethod') {
            $docblock['modifiers'] = $this->getStatementModifiers($statement);
          }
          $docblocks[] = $docblock;
          break;

        case 'Stmt_Class':
        case 'Stmt_Interface':
        case 'Stmt_Trait':
          $docblock['object_name'] = Formatter::asString($statement->name);
          $docblock['title'] = $docblock['object_name'];
          $docblock['member_name'] = $docblock['object_name'];
          // Note that we are not finding references here. We use the ones
          // from the child statements instead.
          $docblock['code'] = Formatter::formatStatements([$statement], $docblock['is_drupal']);

          $docblock['extends'] = [];
          $docblock['implements'] = [];

          if ($type == 'Stmt_Class') {
            $docblock['object_type'] = 'class';
            $docblock['modifiers'] = $this->getStatementModifiers($statement);

            if ($statement->extends) {
              $docblock['extends'] = [$statement->extends->toString()];
            }
            if (!empty($statement->implements) && count($statement->implements)) {
              foreach ($statement->implements as $item) {
                $docblock['implements'][] = $item->toString();
              }
            }
          }
          elseif ($type == 'Stmt_Interface') {
            $docblock['object_type'] = 'interface';
            if ($statement->extends) {
              foreach ($statement->extends as $extend) {
                $docblock['extends'][] = $extend->toString();
              }
            }
          }
          else {
            $docblock['object_type'] = 'trait';
          }

          $docblocks[] = $docblock;

          // Process the class's internal/body statements.
          if (!empty($statement->stmts)) {
            $last_index = count($docblocks) - 1;
            $references = $this->buildPhpDocblocks($statement->stmts, array_merge($default_block, ['class' => $docblock['object_name']]), $docblocks);
            $all_references = $this->mergeReferences($all_references, $references, $docblock['file_name']);
            $docblocks[$last_index]['references'] = $references;
          }

          break;

        case 'Stmt_Namespace':
          if ($statement->name) {
            $namespace = $statement->name->toString();
            $references = ['namespace' => $namespace];
            $all_references = $this->mergeReferences($all_references, $references, $docblock['file_name']);
          }
          else {
            $this->logger->warning('Empty namespace declaration in file @name',
              [
                '@name' => $docblock['file_name'],
              ]);
          }

          // The rest of the statements in this file are inside this namespace.
          if (!empty($statement->stmts)) {
            $references = $this->buildPhpDocblocks($statement->stmts, $default_block, $docblocks);
            $all_references = $this->mergeReferences($all_references, $references, $docblock['file_name']);
          }
          break;

        case 'Stmt_Use':
          $references = ['use_alias' => []];
          foreach ($statement->uses as $use) {
            $alias = $use->alias;
            $class = $use->name->toString();
            if (!$alias) {
              // Strip out namespace info from alias part.
              $parts = explode('\\', $class);
              $alias = array_pop($parts);
            }
            $alias = Formatter::asString($alias);
            $references['use_alias'][$alias] = $class;
          }
          $all_references = $this->mergeReferences($all_references, $references, $docblock['file_name']);
          break;

        case 'Stmt_TraitUse':
          $trait = $statement->traits[0]->toString();
          $references = [
            'use_trait' => [
              $trait => [
                'class' => $trait,
                'details' => [],
              ],
            ],
          ];
          foreach ($statement->adaptations as $adaptation) {
            $ad_type_parts = explode('_', $adaptation->getType());
            $ad_type = strtolower(array_pop($ad_type_parts));
            if ($ad_type == 'precedence') {
              foreach ($adaptation->insteadof as $node) {
                $references['use_trait'][$trait]['details'][$ad_type][$node->toString()] = $adaptation->method;
              }
            }
            else {
              $adaptationName = Formatter::asString($adaptation->newName);
              $references['use_trait'][$trait]['details'][$ad_type][$adaptationName] = $adaptation->method;
            }
          }
          $all_references = $this->mergeReferences($all_references, $references, $docblock['file_name']);
          break;
      }
    }

    return $all_references;
  }

  /**
   * Returns the function signature from a PhpParser function node object.
   *
   * @param object $statement
   *   A function statement to get the signature of.
   *
   * @return string
   *   The function signature.
   */
  public function getFunctionSignature($statement) {
    // Make a function with empty body, pretty-print it, and remove the {}.
    $empty_function = new NodeFunction(
      $statement->name,
      [
        'byRef' => $statement->byRef,
        'params' => $statement->params,
        'returnType' => $statement->returnType,
      ]);

    // Note: Use the Standard pretty-printer here, not our class that does
    // HTML formatting.
    $printer = new StandardPrettyPrinter();
    $output = $printer->prettyPrint([$empty_function]);
    $output = preg_replace('|\{.*\}|s', '', $output);
    $output = str_replace('function ', '', $output);
    return trim($output);
  }

  /**
   * Returns the modifiers from a PhpParser statement.
   *
   * @param object $statement
   *   A class, method, property, etc. statement to get the modifiers of. Must
   *   have a flags property.
   *
   * @return string
   *   The modifiers.
   */
  public function getStatementModifiers($statement) {
    $flags = $statement->flags;
    $modifiers = '';
    $modifier_list = [
      // Note: Keep this list in the order that the modifiers should appear.
      NodeClass::MODIFIER_ABSTRACT => 'abstract',
      NodeClass::MODIFIER_FINAL => 'final',
      NodeClass::MODIFIER_PUBLIC => 'public',
      NodeClass::MODIFIER_PROTECTED => 'protected',
      NodeClass::MODIFIER_PRIVATE => 'private',
      NodeClass::MODIFIER_STATIC => 'static',
    ];
    foreach ($modifier_list as $flag => $name) {
      if ($flags & $flag) {
        $modifiers .= $name . ' ';
      }
    }

    return trim($modifiers);
  }

  /**
   * Look for @file block first so $docblocks[0] gets filled in first.
   *
   * @param array $docblocks
   *   Array of docblocks.
   *
   * @return array
   *   Same array with the 'content' property altered if needed.
   */
  public function fileDocblockFirst(array $docblocks) {
    foreach ($docblocks as $docblock) {
      if (
        !empty($docblock['content']) &&
        preg_match('/' . Parser::RE_TAG_START . 'file/', $docblock['content'])
      ) {
        $content = $docblock['content'];
        // Remove @file tag from this docblock.
        $content = str_replace('@file', '', $content);

        // If this docblock contains @mainpage or @defgroup, this will cause
        // problems, because we won't have a @file doc block any more -- it will
        // be co-opted, and then the site will be screwed up. So, remove these
        // tags and save a watchdog message.
        if (
          preg_match('/' . Parser::RE_TAG_START . 'mainpage/', $content) ||
          preg_match('/' . Parser::RE_TAG_START . 'defgroup/', $content)
        ) {
          $content = str_replace('@mainpage', '', $content);
          $content = str_replace('@defgroup', '', $content);

          $this->logger->warning(
            '@file docblock containing @defgroup or @mainpage in %file at line %line. Extraneous tags ignored.',
            [
              '%file' => $docblocks[0]['file_name'],
              '%line' => $docblock['start_line'],
            ]
          );
        }

        $docblocks[0]['content'] = $content;
        break;
      }
    }

    return $docblocks;
  }

  /**
   * Splits certain tags into further docblocks.
   *
   * @param array $docblocks
   *   Original set of docblocks.
   * @param array|string[] $tags
   *   Tags to split.
   *
   * @return array
   *   New set of docblocks with the new elements added.
   */
  public function splitByTags(array $docblocks, array $tags = []) {
    if (empty($tags)) {
      return $docblocks;
    }

    $old_blocks = $docblocks;
    $docblocks = [];
    $tags_regex = implode('|', $tags);
    foreach ($old_blocks as $docblock) {
      if (
        $docblock['code'] && $docblock['content'] &&
        preg_match('/' . Parser::RE_TAG_START . '(' . $tags_regex . ')/', $docblock['content'])
      ) {
        $new_block = $docblock;
        // Make one block have just the code and the other, just the docs.
        $new_block['code'] = '';
        $docblock['content'] = '';
        $docblocks[] = $new_block;
        $docblocks[] = $docblock;

        $this->logger->warning(
          'Item docblock containing @tags tags in %file at line %line. Separated into two blocks.',
          [
            '%file' => $docblocks[0]['file_name'],
            '%line' => $docblock['start_line'],
            '@tags' => $tags_regex,
          ]
        );
      }
      else {
        $docblocks[] = $docblock;
      }
    }

    return $docblocks;
  }

  /**
   * Return the references namespace.
   *
   * @param array $docblock
   *   Docblock array.
   *
   * @return string|null
   *   Namespace or null if nothing was found.
   */
  protected function getReferencesNamespace(array $docblock) {
    return (isset($docblock['references']['namespace']) && !empty($docblock['references']['namespace'])) ?
      $docblock['references']['namespace'] :
      NULL;
  }

  /**
   * Gets the use_alias property of a docblock.
   *
   * @param array $docblock
   *   Docblock array.
   *
   * @return array
   *   Aliases array.
   */
  protected function getUseAlias(array $docblock) {
    return (isset($docblock['references']['use_alias'])) ?
      $docblock['references']['use_alias'] :
      [];
  }

  /**
   * Change annotation tag to ingroup if it's Drupal code.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function changeAnnotationToInGroup(array &$docblock) {
    $annotation_matches = [];
    $docblock['annotation_class'] = FALSE;
    if (preg_match('/' . self::RE_TAG_START . 'Annotation' . self::RE_WORD_BOUNDARY_END . '/', $docblock['content'], $annotation_matches)) {
      if ($docblock['is_drupal']) {
        $docblock['content'] = str_replace($annotation_matches[0], "\n@ingroup annotation\n", $docblock['content']);
      }
      $docblock['annotation_class'] = TRUE;
    }
  }

  /**
   * Change event tag to ingroup.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function changeEventToInGroup(array &$docblock) {
    $event_matches = [];
    if (preg_match('/' . self::RE_TAG_START . 'Event' . self::RE_WORD_BOUNDARY_END . '/', $docblock['content'], $event_matches)) {
      $docblock['content'] = str_replace($event_matches[0], "\n@ingroup events\n", $docblock['content']);
    }
  }

  /**
   * Checks if a docblock has a tag in its contents.
   *
   * @param array $docblock
   *   Docblock array.
   * @param string $tag
   *   Tag to check.
   *
   * @return bool
   *   Whether the tag is present or not.
   */
  protected function docblockHasTag(array $docblock, $tag) {
    return preg_match('/' . self::RE_TAG_START . $tag . '/', $docblock['content']);
  }

  /**
   * Sets the mainpage tag.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setMainpage(array &$docblock) {
    $matches = [];
    preg_match('/' . self::RE_TAG_START . 'mainpage(.*?)\n/', $docblock['content'], $matches);
    if (!empty($matches)) {
      $docblock['title'] = (isset($matches[1]) ? trim($matches[1]) : '');
      $docblock['content'] = preg_replace('/' . self::RE_TAG_START . 'mainpage.*?\n/', '', $docblock['content']);
      $docblock['object_type'] = 'mainpage';
    }
  }

  /**
   * Sets group membership.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setGroupMembership(array &$docblock) {
    $matches = [];
    if (preg_match_all('/' . self::RE_TAG_START . '(ingroup|addtogroup) ([a-zA-Z0-9_.-]+)/', $docblock['content'], $matches)) {
      $docblock['groups'] = $matches[2];
      $docblock['content'] = preg_replace('/' . self::RE_TAG_START . '(ingroup|addtogroup).*?\n/', '', $docblock['content']);
    }
  }

  /**
   * Returns a defgroup name in a docblock, if any.
   *
   * @param array $docblock
   *   Docblock array.
   * @param bool $all_matches
   *   Return all matches or just the group name.
   *
   * @return mixed|null
   *   Match or null.
   */
  protected function getDefgroupName(array $docblock, $all_matches = FALSE) {
    $matches = [];
    preg_match('/' . self::RE_TAG_START . 'defgroup ([a-zA-Z0-9_.-]+) +(.*?)\n/', $docblock['content'], $matches);

    if ($all_matches && !empty($matches)) {
      return $matches;
    }

    return !empty($matches) ?
      $matches[1] :
      NULL;
  }

  /**
   * Sets the defgroup tag.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setDefgroup(array &$docblock) {
    $matches = $this->getDefgroupName($docblock, TRUE);
    if (!empty($matches)) {
      $docblock['object_name'] = $matches[1];
      $docblock['title'] = $matches[2];
      $docblock['content'] = preg_replace('/' . self::RE_TAG_START . 'defgroup.*?\n/', '', $docblock['content']);
      $docblock['object_type'] = 'group';
    }
    else {
      $this->logger->warning(
        'Malformed @defgroup in %file at line %line.',
        [
          '%file' => $docblock['file_name'],
          '%line' => $docblock['start_line'],
        ]
      );
    }
  }

  /**
   * Replaces a tag in a docblock array.
   *
   * @param array $docblock
   *   Docblock array.
   * @param string $search
   *   String to search.
   * @param string $replace
   *   String to replace.
   * @param bool $warning
   *   Log a warning to the watchdog about this.
   */
  protected function replaceTag(array &$docblock, $search, $replace, $warning = FALSE) {
    $docblock['content'] = str_replace($search, $replace, $docblock['content']);
    if ($warning) {
      $this->logger->warning(
        'Replaced @search in %file at line %line with @replace.',
        [
          '%file' => $docblock['file_name'],
          '%line' => $docblock['start_line'],
          '@search' => $search,
          '@replace' => $replace,
        ]
      );
    }
  }

  /**
   * Handle nested_groups for a docblock.
   *
   * @param array $docblock
   *   Docblock array.
   * @param array $nested_groups
   *   Current nested groups.
   */
  protected function setNestedGroups(array &$docblock, array &$nested_groups) {
    if (!isset($nested_groups[$docblock['class']])) {
      $nested_groups[$docblock['class']] = [];
    }
    foreach ($nested_groups[$docblock['class']] as $group_id) {
      if (!empty($group_id)) {
        $docblock['groups'][] = $group_id;
      }
    }
    if (preg_match('/' . self::RE_TAG_START . '{/', $docblock['content'])) {
      if ($docblock['object_type'] === 'group') {
        array_push($nested_groups[$docblock['class']], $docblock['object_name']);
      }
      elseif (isset($docblock['groups'])) {
        array_push($nested_groups[$docblock['class']], reset($docblock['groups']));
      }
      else {
        array_push($nested_groups[$docblock['class']], '');
      }
    }
    if (preg_match('/' . self::RE_TAG_START . '}/', $docblock['content'])) {
      array_pop($nested_groups[$docblock['class']]);
    }
  }

  /**
   * Do some processing of the docblock to add additional information.
   *
   * @param array $docblock
   *   Docblock array.
   * @param string $namespace
   *   Namespace where this docblock belongs.
   * @param array $use_aliases
   *   Aliases in use in this docblock.
   * @param array $nested_groups
   *   Nested groups in this docblock.
   * @param array $class_ids
   *   Array of documentation ids.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch this docblock will be attached to.
   *
   * @return bool
   *   Whether we should save this or not.
   */
  public function processDocblock(array &$docblock, &$namespace, array &$use_aliases, array &$nested_groups, array &$class_ids, BranchInterface $branch) {
    // Keep track of the namespace and add it to all docblocks for this file.
    $namespace = $this->getReferencesNamespace($docblock) ?? $namespace;

    // Keep track of the use aliases so we can put the right classes into the
    // extends/implements references for classes we encounter.
    $use_aliases = array_merge($this->getUseAlias($docblock), $use_aliases);

    // Start filling additional data for the docblock.
    $docblock['namespace'] = $namespace;
    $this->changeAnnotationToInGroup($docblock);
    $this->changeEventToInGroup($docblock);

    if ($this->docblockHasTag($docblock, 'mainpage')) {
      $this->setMainpage($docblock);
      $docblock['object_name'] = $branch->getSlug();
    }
    elseif ($this->docblockHasTag($docblock, 'defgroup')) {
      $group_name = $this->getDefgroupName($docblock);
      $existing_defgroup = !empty($group_name) ?
        DocBlock::findByDefgroup($group_name, $branch, $docblock['file_name']) :
        NULL;
      if ($existing_defgroup) {
        $this->replaceTag($docblock, 'defgroup', 'addtogroup', TRUE);
        // No need to reparse anything as we're already changing the tag and
        // there is a defgroup in place.
      }
      else {
        $this->setDefgroup($docblock);
      }
    }

    $this->setGroupMembership($docblock);
    $this->setNestedGroups($docblock, $nested_groups);

    // At this point, we might have been dealing with a "block" that is
    // just an @} or an object with no name, or something like that. We needed
    // to do the processing above, but we don't want to save this as an object.
    if (empty($docblock['object_type']) || empty($docblock['object_name'])) {
      return FALSE;
    }

    $this->replaceTag($docblock, '{@inheritdoc}', '');
    $this->replaceTag($docblock, '{@inheritDoc}', '');

    if ($docblock['content'] && trim($docblock['content'])) {
      $this->setParams($docblock);
      $this->setReturn($docblock);
      $this->setSee($docblock);
      $this->setVar($docblock);
      $this->setThrows($docblock);
      $this->setDeprecated($docblock);
      $this->setDocumentation($docblock);
    }

    // Grab the first line as a summary, unless already provided.
    if (!isset($docblock['summary'])) {
      $docblock['summary'] = Formatter::documentationSummary($docblock['documentation']);
    }

    if (!empty($docblock['class'])) {
      $docblock['class'] = $class_ids[$docblock['class']];
    }

    if (!empty($docblock['code'])) {
      $docblock['code'] = Formatter::validateEncoding($docblock['code']);
    }

    // Figure out the namespaced name.
    $docblock['namespaced_name'] = Formatter::fullClassname($docblock['object_name'], $namespace, $use_aliases);

    return TRUE;
  }

  /**
   * Sets the param annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setParams(array &$docblock) {
    $matches = [];
    $tmp = [];

    $params = '';
    while (preg_match('/' . self::RE_TAG_START . 'param\s(.*?)(?=\n' . self::RE_TAG_START . '|$)/s', $docblock['content'], $matches)) {
      $docblock['content'] = str_replace($matches[0], '', $docblock['content']);
      // Add some formatting to the parameter -- strong tag for everything
      // that was on the @param line, and a colon after. Note that tags
      // are stripped out below, so we use [strong] and then fix it later.
      $this_param = $matches[1];
      $this_param = preg_replace('|^([^\n]+)|', '[strong]$1[/strong]:', $this_param);
      $params .= "\n\n" . $this_param;
    }

    // Format and then replace our fake tags with real ones.
    $params = Formatter::formatDocumentation($params, TRUE, $tmp);
    $params = str_replace('[strong]', '<strong>', $params);
    $params = str_replace('[/strong]', '</strong>', $params);
    $docblock['parameters'] = $params;
  }

  /**
   * Sets the return annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setReturn(array &$docblock) {
    $matches = [];
    $tmp = [];

    $docblock['return_value'] = '';
    preg_match_all('/' . self::RE_TAG_START . 'return\s(.*?)(?=\n' . self::RE_TAG_START . '|$)/s', $docblock['content'], $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
      $docblock['content'] = str_replace($match[0], '', $docblock['content']);
      $docblock['return_value'] .= "\n\n" . $match[1];
    }
    $docblock['return_value'] = Formatter::formatDocumentation($docblock['return_value'], TRUE, $tmp);
  }

  /**
   * Sets the see annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setSee(array &$docblock) {
    $this->setProperty($docblock, 'see');
  }

  /**
   * Sets the var annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setVar(array &$docblock) {
    $matches = [];

    $docblock['var'] = '';
    if (preg_match('/' . self::RE_TAG_START . 'var\s(.*?)\n/s', $docblock['content'], $matches)) {
      $docblock['content'] = str_replace($matches[0], '', $docblock['content']);
      $docblock['var'] = trim($matches[1]);
    }
  }

  /**
   * Sets the throws annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setThrows(array &$docblock) {
    $this->setProperty($docblock, 'throws');
  }

  /**
   * Sets the deprecated annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setDeprecated(array &$docblock) {
    $this->setProperty($docblock, 'deprecated');
  }

  /**
   * Generic setter for docblock properties.
   *
   * @param array $docblock
   *   Docblock array.
   * @param string $property
   *   Property to set.
   */
  protected function setProperty(array &$docblock, $property) {
    $matches = [];
    $tmp = [];

    $docblock[$property] = '';
    while (preg_match('/' . self::RE_TAG_START . $property . '\s(.*?)(?=\n' . self::RE_TAG_START . '|$)/s', $docblock['content'], $matches)) {
      $docblock['content'] = str_replace($matches[0], '', $docblock['content']);
      $docblock[$property] .= "\n\n" . $matches[1];
    }
    $docblock[$property] = Formatter::formatDocumentation($docblock[$property], TRUE, $tmp);
  }

  /**
   * Sets the documentation annotations into the docblock.
   *
   * @param array $docblock
   *   Docblock array.
   */
  protected function setDocumentation(array &$docblock) {
    // Format everything remaining as the main documentation.
    $docblock['documentation'] = Formatter::formatDocumentation($docblock['content'], TRUE, $docblock['references']);
  }

  /**
   * Adds defaults for TEXT fields to a database record.
   *
   * These cannot come from the schema, because TEXT fields have no defaults.
   *
   * @param array $record
   *   Record about to be written to the database, passed by reference.
   * @param string $entity_type
   *   Entity type $record is going into.
   */
  public function addTextDefaults(array &$record, $entity_type = 'docblock') {
    switch ($entity_type) {
      case 'docblock':
        $record += [
          'summary' => '',
          'documentation' => '',
          'code' => '',
          'see' => '',
          'deprecated' => '',
          'var' => '',
          'throws' => '',
          'namespace' => '',
          'namespaced_name' => '',
          'modifiers' => '',
        ];
        break;

      case 'docblock_function':
        $record += [
          'parameters' => '',
          'return_value' => '',
        ];
        break;
    }
  }

}
