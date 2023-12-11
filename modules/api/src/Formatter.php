<?php

namespace Drupal\api;

use Drupal\api\Entity\Branch;
use Drupal\api\Entity\ExternalBranch;
use Drupal\api\Entity\ExternalDocumentation;
use Drupal\api\Entity\PhpDocumentation;
use Drupal\api\Entity\Project;
use Drupal\api\Form\SearchForm;
use Drupal\api\Interfaces\BranchInterface;
use Drupal\api\Entity\DocBlock;
use Drupal\api\Entity\DocBlock\DocNamespace;
use Drupal\api\Entity\DocBlock\DocReference;
use Drupal\api\Interfaces\DocBlockInterface;
use Drupal\Component\Utility\Html;
use Drupal\Component\Utility\Unicode;
use Drupal\Component\Utility\Xss;
use Drupal\Core\Link;
use Drupal\Core\Url;

/**
 * Formatting related functions. All functions can be called in isolation.
 *
 * @package Drupal\api
 */
class Formatter {

  /**
   * In listing tables, long item names will be allowed to break at these spots.
   *
   * Space-separated. Careful of the order if any of these appear in the HTML
   * tag being used!
   *
   * @var string
   */
  const BREAKS_WHERE = '/ :: \\';

  /**
   * HTML tag or entity to use to indicate breaks.
   *
   * @var string
   */
  const BREAKS_TAG = '<wbr />';

  /**
   * Regular expression for matching group/topic names.
   *
   * @var string
   */
  const RE_GROUP_NAME = '[a-zA-Z_0-9\.\-]+';

  /**
   * Regular expression for aggressively matching class names in text.
   *
   * Although class names can technically be just like function names, we
   * only want to match class names if they include a capital letter, so as
   * not to be too overly aggressive. Possibly can include namespaces.
   *
   * @var string
   */
  const RE_CLASS_NAME_TEXT = '[\\\\a-zA-Z0-9_\x7f-\xff]*[A-Z][\\\\a-zA-Z0-9_\x7f-\xff]*';

  /**
   * Regular expression for less-aggressively matching class names in text.
   *
   * Matches class names that are namespaced, because we know these are not just
   * plain text words.
   *
   * @var string
   */
  const RE_DEFINITE_CLASS_NAME_TEXT = '[\\\\a-zA-Z0-9_\x7f-\xff]*[\\\\][\\\\a-zA-Z0-9_\x7f-\xff]*';

  /**
   * Regular expression for matching YAML strings.
   *
   * These can contain ., _, letters, and numbers, and are top-level keys
   * in YAML files.
   *
   * @var string
   */
  const RE_YAML_STRING = '[a-zA-Z0-9_\.\x7f-\xff]+';

  /**
   * File path separator.
   *
   * @var string
   */
  const FILEPATH_SEPARATOR = '/';

  /**
   * Namespace separator.
   *
   * @var string
   */
  const NAMESPACE_SEPARATOR = '\\';

  /**
   * File path separator replacement.
   *
   * @var string
   */
  const FILEPATH_SEPARATOR_REPLACEMENT = '!';

  /**
   * File path separator replacement for API v 1.3.
   *
   * @var string
   */
  const V1_3_FILEPATH_SEPARATOR_REPLACEMENT = '--';

  /**
   * Cleans the comment characters out of a doc comment.
   *
   * @param string $text
   *   Comment text to clean.
   *
   * @return string
   *   Cleaned text.
   */
  public static function cleanComment($text) {
    $text = str_replace(['/**', '*/'], '', $text);
    return preg_replace('|^ *\* ?|m', '', $text);
  }

  /**
   * Adds the configured line break character to text.
   *
   * @param string $text
   *   Text to add line breaks to, presumably a class member name or something
   *   similar that is too long.
   *
   * @return string
   *   Text with line break characters added.
   */
  public static function addBreaks($text) {
    /** @var \Drupal\Core\Config\ImmutableConfig $api_config */
    $config = \Drupal::config('api.settings');

    $where = array_filter(explode(' ', $config->get('breaks_where') ?? self::BREAKS_WHERE));
    if (!count($where)) {
      return $text;
    }

    $tag = $config->get('breaks_tag') ?? self::BREAKS_TAG;
    if (!strlen($tag)) {
      return $text;
    }

    $replace = [];
    foreach ($where as $string) {
      $replace[] = $string . $tag;
    }

    $text = str_replace($where, $replace, $text);
    return $text;
  }

  /**
   * Formats documentation comment text as HTML.
   *
   * First escapes all HTML tags. Then processes links and code blocks, and
   * converts newlines into paragraphs. Note that this function does not do any
   * Drupal-specific formatting, aside from formatting plugin annotation, which
   * should be fine for vendor files as well.
   *
   * @param string $documentation
   *   Documentation string to format.
   * @param bool $make_paragraphs
   *   TRUE (default) to convert to paragraphs. FALSE to skip this conversion
   *   and put the documentation in PRE tags.
   * @param array $references
   *   Array of references. If this function finds references (only for plugin
   *   annotation), this array may be added to.
   *
   * @return string
   *   Formatted documentation.
   */
  public static function formatDocumentation($documentation, $make_paragraphs, array &$references) {
    // Don't do processing on empty text (so we don't end up with empty
    // paragraphs).
    if (empty($documentation)) {
      return '';
    }

    $documentation = self::validateEncoding($documentation);

    // @link full URLs.
    $documentation = preg_replace(
      '/' . Parser::RE_TAG_START . 'link ((http:\/\/|https:\/\/|ftp:\/\/|mailto:|smb:\/\/|afp:\/\/|file:\/\/|gopher:\/\/|news:\/\/|ssl:\/\/|sslv2:\/\/|sslv3:\/\/|tls:\/\/|tcp:\/\/|udp:\/\/)([a-zA-Z0-9@:%_+*!~#?&=.,\/;-]*[a-zA-Z0-9@:%_+*!~#&=\/;-])) (.*?) ' . Parser::RE_TAG_START . 'endlink/',
      '<a href="$1">$4</a>',
      $documentation
    );
    // Site URLs.
    $documentation = preg_replace(
      '/' . Parser::RE_TAG_START . 'link \/([a-zA-Z0-9_\/-]+) (.*?) ' . Parser::RE_TAG_START . 'endlink/', str_replace('%24', '$',
      '<a href="$1">$2</a>'),
      $documentation
    );

    // Process sections.
    $regexp = '/^' . Parser::RE_TAG_START . 'section ([a-zA-Z0-9_-]+) (.*)$/m';
    preg_match_all($regexp, $documentation, $section_matches, PREG_SET_ORDER);
    if (!empty($section_matches)) {
      $documentation = preg_replace($regexp, '<h3 id="$1">$2</h3>', $documentation);
    }

    // Process sub-sections.
    $regexp = '/^' . Parser::RE_TAG_START . 'subsection ([a-zA-Z0-9_-]+) (.*)$/m';
    preg_match_all($regexp, $documentation, $subsection_matches, PREG_SET_ORDER);
    if (!empty($subsection_matches)) {
      $documentation = preg_replace($regexp, '<h4 id="$1">$2</h4>', $documentation);
    }

    // Process in-page references to sections/subsections.
    if (!empty($section_matches) || !empty($subsection_matches)) {
      $search = [];
      $replace = [];
      foreach (array_merge($section_matches, $subsection_matches) as $match) {
        array_shift($match);
        $id = array_shift($match);
        $caption = trim(array_shift($match));
        $search[] = '/' . Parser::RE_TAG_START . 'ref ' . $id . '/';
        $replace[] = '<a href="#' . $id . '">' . Html::escape($caption) . '</a>';
      }
      $documentation = preg_replace($search, $replace, $documentation);
    }

    // Replace left over curly braces.
    $documentation = preg_replace('/' . Parser::RE_TAG_START . '[{}]/', '', $documentation);

    // Change @Plugin and other annotation sections into @code. They have to be
    // at the very end of the documentation block. And add a reference.
    $annotation_matches = [];
    if (preg_match(
      '/' . Parser::RE_TAG_START . '(' . Parser::RE_FUNCTION_CHARACTERS . ')(\(.*\))\s*$/s',
      $documentation,
      $annotation_matches
    )) {
      $class = $annotation_matches[1];
      $references['annotation'][$class] = $class;
      $documentation = str_replace(
        $annotation_matches[0],
        '<h3>' . t('Plugin annotation') . '</h3>' . "\n@code\n@" . $annotation_matches[1] . $annotation_matches[2] . "\n@endcode",
        $documentation
      );
      // Annotation like @FormElement("button") or @RenderElement("table").
      // Extract the element machine name (button/table)
      // from $annotation_matches[2] and save it as a reference.
      $element_types = ['FormElement', 'RenderElement'];
      if (in_array($class, $element_types)) {
        $element_type = trim(self::entityDecode($annotation_matches[2]), " \t\n\r()'\"");
        $references['element'][$element_type] = $element_type;
      }
    }

    // Process the @code @endcode tags.
    $documentation = preg_replace_callback(
      '/' . Parser::RE_TAG_START . 'code(.+?)' . Parser::RE_TAG_START . 'endcode/s',
      ['\Drupal\api\Formatter', 'formatEmbeddedPhp'],
      $documentation
    );

    // Convert newlines into paragraphs.
    $documentation = ($make_paragraphs) ?
      self::makeParagraphs($documentation) :
      '<pre class="api-text">' . $documentation . '</pre>';

    return $documentation;
  }

  /**
   * Regular expression callback for \@code tags in formatDocumentation().
   */
  public static function formatEmbeddedPhp($matches) {
    /** @var \Drupal\api\Parser $apiParser */
    $apiParser = \Drupal::service('api.parser');
    $code = self::entityDecode($matches[1]);
    $statements = $apiParser->parsePhpCode($code);
    if (empty($statements)) {
      // The code block is not valid PHP. It could be JavaScript, annotation,
      // PHP code with a syntax error, or something else. The best we can do is
      // try to find function-like strings and wrap them in spans for
      // formatting. And recode the HTML entities, so they don't screw up
      // other formatting, but this time avoiding quotes.
      $code = htmlentities($code, ENT_NOQUOTES, 'UTF-8');
      $string_matches = [];
      $wrappers = '\'" @(';
      $possible_matches = [];
      if (preg_match_all(
        '|[' . $wrappers . '](' . Parser::RE_FUNCTION_IN_TEXT . ')[' . $wrappers . ']|',
        $code,
        $string_matches
      )) {
        $possible_matches = array_unique($string_matches[1]);
        $code = self::formatYamlCode(
          $code,
          ['potential callback' => $possible_matches],
          $wrappers,
          'php-function-or-constant-declared'
        );
      }

      return "\n" . self::wrapPhpCode($code) . "\n";
    }

    return "\n" . self::formatStatements($statements) . "\n";
  }

  /**
   * Wraps a PHP code block into pre formatted tags.
   *
   * @param string $code
   *   Code to wrap.
   *
   * @return string
   *   Code wrapped up.
   */
  public static function wrapPhpCode($code) {
    return '<pre class="php"><code>' . $code . '</code></pre>';
  }

  /**
   * Numbers lines in code.
   *
   * @param string $code
   *   Code to number.
   * @param int $number
   *   (optional) Number to start with, if different from 1.
   *
   * @return string
   *   Numbered code. Uses OL list.
   */
  public static function numberLines($code, $number = 1) {
    $start = (is_int($number)) ? $number : 1;
    $lines = explode("\n", $code);
    // If the last line is empty, omit it.
    $last = array_pop($lines);
    if ($last) {
      array_push($lines, $last);
    }
    $output = '<ol class="code-lines" start="' . $start . '"><li>' . implode("\n</li><li>", $lines) . "\n</li></ol>";

    return $output;
  }

  /**
   * Sort callback for usort in parseYamlFile().
   *
   * Sorts with longest first.
   */
  public static function lengthCompare($a, $b) {
    $lena = strlen($a);
    $lenb = strlen($b);
    if ($lena < $lenb) {
      return 1;
    }
    return ($lena > $lenb) ? -1 : 0;
  }

  /**
   * Formats statements as PHP code set up for linking at output time.
   *
   * @param array $statements
   *   Array of statements from PhpParser parsing.
   * @param bool $is_drupal
   *   (optional) TRUE (default) if this is Drupal code; FALSE if not.
   *   This turns on recognition of things like hooks and theme calls.
   * @param bool $is_file
   *   (optional) TRUE if this is a file (to print the opening ?php tag).
   *   FALSE (default) if not.
   *
   * @return string
   *   HTML-formatted code, with spans enclosing various PHP elements.
   */
  public static function formatStatements(array $statements, $is_drupal = TRUE, $is_file = FALSE) {
    $printer = new PrettyPrinter(['isDrupal' => $is_drupal]);
    $code = ($is_file) ?
      $printer->prettyPrintFile($statements) :
      $printer->prettyPrint($statements);
    $code = self::validateEncoding($code);

    return self::wrapPhpCode($code);
  }

  /**
   * Converts newlines into paragraphs.
   *
   * Like _filter_autop(), but does not add <br /> tags.
   *
   * @param string $text
   *   Text to convert.
   *
   * @return string
   *   Converted text.
   */
  public static function makeParagraphs($text) {
    // All block level tags.
    $block = '(?:table|thead|tfoot|caption|colgroup|tbody|tr|td|th|div|dl|dd|dt|ul|ol|li|pre|select|form|blockquote|address|p|h[1-6]|hr)';

    // Split at <pre>, <script>, <style> and </pre>, </script>, </style> tags.
    // We don't apply any processing to the contents of these tags to avoid
    // messing up code. We look for matched pairs and allow basic nesting. For
    // example: "processed <pre> ignored <script> ignored </script>
    // ignored </pre> processed".
    $chunks = preg_split('@(</?(?:pre|script|style|object)[^>]*>)@i', $text, -1, PREG_SPLIT_DELIM_CAPTURE);
    // Note: PHP ensures the array consists of alternating delimiters and
    // literals and begins and ends with a literal (inserting NULL as
    // required).
    $ignore = FALSE;
    $ignoretag = '';
    $output = '';
    foreach ($chunks as $i => $chunk) {
      if ($i % 2) {
        // Opening or closing tag?
        $open = ($chunk[1] != '/');
        [$tag] = preg_split('/[ >]/', substr($chunk, 2 - $open), 2);
        if (!$ignore) {
          if ($open) {
            $ignore = TRUE;
            $ignoretag = $tag;
          }
        }
        // Only allow a matching tag to close it.
        elseif (!$open && $ignoretag == $tag) {
          $ignore = FALSE;
          $ignoretag = '';
        }
      }
      elseif (!$ignore) {
        $chunk = self::formatDocumentationLists($chunk);
        // Just to make things a little easier, pad the end.
        $chunk = preg_replace('|\n*$|', '', $chunk) . "\n\n";
        $chunk = preg_replace('|<br />\s*<br />|', "\n\n", $chunk);
        // Space things out a little.
        $chunk = preg_replace('!(<' . $block . '[^>]*>)!', "\n$1", $chunk);
        $chunk = preg_replace('!(</' . $block . '>)!', "$1\n\n", $chunk);
        // Take care of duplicates.
        $chunk = preg_replace("/\n\n+/", "\n\n", $chunk);
        // Make paragraphs, including one at the end.
        $chunk = preg_replace('/\n?(.+?)(?:\n\s*\n|\z)/s', "<p>$1</p>\n", $chunk);
        // Under certain strange conditions it could create a P of entirely
        // whitespace.
        $chunk = preg_replace('|<p>\s*</p>\n|', '', $chunk);
        // Problem with nested lists.
        $chunk = preg_replace("|<p>(<li.+?)</p>|", "$1", $chunk);
        $chunk = preg_replace('|<p><blockquote([^>]*)>|i', "<blockquote$1><p>", $chunk);
        $chunk = str_replace('</blockquote></p>', '</p></blockquote>', $chunk);
        $chunk = preg_replace('!<p>\s*(</?' . $block . '[^>]*>)!', "$1", $chunk);
        $chunk = preg_replace('!(</?' . $block . '[^>]*>)\s*</p>!', "$1", $chunk);
        $chunk = preg_replace('/&([^#])(?![A-Za-z0-9]{1,8};)/', '&amp;$1', $chunk);
      }
      $output .= $chunk;
    }
    return $output;
  }

  /**
   * Formats documentation lists as HTML lists.
   *
   * Parses a block of text for lists that uses hyphens or asterisks as bullets,
   * and format the lists as proper HTML lists.
   *
   * @param string $documentation
   *   Documentation string to format.
   *
   * @return string
   *   $documentation with lists formatted.
   */
  public static function formatDocumentationLists($documentation) {
    $lines = explode("\n", $documentation);
    $output = '';
    $bullet_indents = [-1];

    foreach ($lines as $line) {
      preg_match('!^( *)([*-] )?(.*)$!', $line, $matches);
      $indent = strlen($matches[1]);
      $bullet_exists = $matches[2];
      $is_start = FALSE;

      if ($indent < $bullet_indents[0]) {
        // First close off any lists that have completed.
        while ($indent < $bullet_indents[0]) {
          array_shift($bullet_indents);
          $output .= '</li></ul>';
        }
      }

      if ($indent == $bullet_indents[0]) {
        if ($bullet_exists) {
          // A new bullet at the same indent means a new list item.
          $output .= '</li><li>';
          $is_start = TRUE;
        }
        else {
          // If the indent is the same, but there is no bullet, that also
          // signifies the end of the list.
          array_shift($bullet_indents);
          $output .= '</li></ul>';
        }
      }

      if ($indent > $bullet_indents[0] && $bullet_exists) {
        // A new list at a lower level.
        array_unshift($bullet_indents, $indent);
        $output .= '<ul><li>';
        $is_start = TRUE;
      }

      // At the start of a bullet, if there is a ":" followed by a space, put
      // everything before the : in bold.
      if ($is_start && (($p = strpos($matches[3], ': ')) > 0)) {
        $matches[3] = '<strong>' . substr($matches[3], 0, $p) . '</strong>' .
          substr($matches[3], $p);
      }
      $output .= $matches[3] . "\n";
    }

    // Clean up any unclosed lists.
    array_pop($bullet_indents);
    foreach ($bullet_indents as $indent) {
      $output .= '</li></ul>';
    }

    // To make sure that makeParagraphs() doesn't get confused, remove
    // newlines immediately before </li> tags.
    $output = str_replace("\n</li>", "</li>", $output);

    return $output;
  }

  /**
   * Formats YAML code.
   *
   * @param string $code
   *   The code to format.
   * @param array $references
   *   Array of found references, used to put spans around strings that can
   *   turn into link.
   * @param string $wrappers
   *   (optional) Characters that can wrap strings, formatted so it can go into
   *   a [] character class in a regular expression.
   * @param string $span_class
   *   (optional) Class to put on spans in the text.
   *
   * @return string
   *   Formatted code.
   */
  public static function formatYamlCode($code, array $references, $wrappers = '\'"\s', $span_class = 'yaml-reference') {
    // Wrap each found callback reference string in a span. We have to use
    // preg_replace() to do this, because we only want to match whole strings,
    // enclosed in single quotes, double quotes, or whitespace. And we want to
    // do the whole thing in one replace, so it matches the longest possible
    // string in each case. So we also need to sort the strings by length,
    // longest first, because PHP regular expressions with alternatives take the
    // first matching one.
    $callbacks = $references['potential callback'];
    if (count($callbacks)) {
      $newstrings = [];
      usort($callbacks, ['\Drupal\api\Formatter', 'lengthCompare']);
      foreach ($callbacks as $string) {
        $newstrings[] = preg_quote($string, '/');
      }

      $regexp = '/(?<=[' . $wrappers . '])(' . implode('|', $newstrings) . ')(?=[' . $wrappers . '])/';
      $code = preg_replace($regexp, '<span class="' . $span_class . '">$1</span>', $code);
    }

    return $code;
  }

  /**
   * Tries to cast a string or object into a string.
   *
   * @param mixed $string_or_object
   *   String or object from the parser methods.
   *
   * @return string
   *   String representation of the variable.
   */
  public static function asString($string_or_object) {
    if (is_null($string_or_object)) {
      return '';
    }

    $result = (!is_string($string_or_object) && method_exists($string_or_object, 'toString')) ?
      $string_or_object->toString() :
      $string_or_object;

    if (!is_string($result)) {
      $result = '';
    }

    return $result;
  }

  /**
   * Retrieves a summary from a documentation block.
   *
   * @param string $documentation
   *   Documentation block to find the summary of. Should be pre-formatted into
   *   paragraphs.
   *
   * @return string
   *   First paragraph of the documentation block, stripped of tags, and
   *   truncated to 255 characters.
   */
  public static function documentationSummary($documentation) {
    $documentation = self::validateEncoding($documentation);

    $pos = strpos($documentation, '</p>');
    if ($pos !== FALSE) {
      $documentation = substr($documentation, 0, $pos);
    }
    $documentation = trim(strip_tags($documentation));

    if (strlen($documentation) > 255) {
      return substr($documentation, 0, strrpos(substr($documentation, 0, 252), ' ')) . '…';
    }
    else {
      return $documentation;
    }
  }

  /**
   * Figures out the full class name of a class, with namespaces.
   *
   * @param string $name
   *   The name as it appears, which could include a namespace, and might or
   *   might not start with a backslash if it does.
   * @param string $namespace
   *   The namespace for the file the name appears in.
   * @param array $use_aliases
   *   Associative array of alias name to full name for use statements in the
   *   file the name appears in.
   * @param int $class_id
   *   Id of the class object.
   *
   * @return string
   *   Fully-qualified name of the class, starting with a backslash.
   */
  public static function fullClassname($name, $namespace = '', array $use_aliases = [], $class_id = NULL) {
    // Break off the class name from the rest.
    $classname = $name;
    $suffix = '';

    $pos = strpos($name, '::');
    if ($pos === FALSE) {
      $pos = strpos($name, '->');
    }
    if ($pos !== FALSE) {
      $classname = substr($name, 0, $pos);
      $suffix = substr($name, $pos);
    }

    if (in_array($classname, ['self', 'static', 'parent', 'this'])) {
      if ($class_id) {
        $class = DocBlock::load($class_id);
        if ($class) {
          if (in_array($classname, ['self', 'static', 'this'])) {
            $classname = $class->getNamespacedName();
          }
          else {
            $res = DocReference::getClassReference($class->getBranch(), $class);
            if ($res) {
              $res = array_shift($res);
              $ref = DocReference::load($res);
              $classname = $ref->getObjectName();
            }
            else {
              return $name;
            }
          }
        }
        else {
          return $name;
        }
      }
      else {
        return $name;
      }
    }

    // See if there is an alias for this class name, or if we should prepend
    // the file's namespace.
    if (isset($use_aliases[$classname])) {
      // This class was aliased.
      $classname = $use_aliases[$classname];
    }
    elseif (strpos($classname, '\\') === FALSE) {
      // There was no alias, and the classname id not have a namespace in it.
      $classname = $namespace . '\\' . $classname;
    }

    // Make sure to start with a backslash, no matter what.
    if (strpos($classname, '\\') !== 0) {
      $classname = '\\' . $classname;
    }

    return $classname . $suffix;
  }

  /**
   * Validates some text against utf8 encoding.
   *
   * @param string $text
   *   Text to check.
   *
   * @return string
   *   Same text or string saying it's not valid.
   */
  public static function validateEncoding($text) {
    if (!Unicode::validateUtf8($text)) {
      return t('Non-displayable characters.');
    }

    return $text;
  }

  /**
   * Turns function, class, and other names into links in documentation.
   *
   * @param string $documentation
   *   Documentation to scan for things to turn into links.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to make the links in.
   * @param int $docblock_id
   *   Documentation ID of the docblock the code is in (for namespace
   *   information). Can omit if namespaces are not relevant.
   * @param int $class_id
   *   Documentation ID of the class the documentation is in (if any).
   * @param bool $aggressive_classes
   *   Try linking every word with a capital letter to a class or interface, if
   *   TRUE. Otherwise, just try to link words with backslashes in them.
   * @param bool $aggressive_topics
   *   For use in [at]see only, if TRUE try linking every paragraph as a topic
   *   name.
   * @param bool $is_drupal
   *   (optional) If set to FALSE, omit the Drupal-specific link steps.
   *
   * @return string
   *   Documentation with links.
   *
   * @see api_link_code
   */
  public static function linkDocumentation($documentation, BranchInterface $branch = NULL, $docblock_id = NULL, $class_id = NULL, $aggressive_classes = FALSE, $aggressive_topics = FALSE, $is_drupal = TRUE) {
    if (!$documentation) {
      return '';
    }

    // Start with the code-related stages, for the @code sections. Follow with
    // the basic documentation stages.
    if ($is_drupal) {
      $stages = [
        'code hook name',
        'code fieldhook name',
        'code entityhook name',
        'code userhook name',
        'code alter hook name',
        'code theme hook name',
        'code element name',
        'code function',
        'code function declared',
        'code member',
        'code string',
        'yaml string',
        'service',
        'yaml file',
        'annotation string',
        'code global',
        'code class',
        'annotation class',
        'tags',
        'link',
        'function',
      ];
    }
    else {
      $stages = [
        'code function',
        'code function declared',
        'code member',
        'code string',
        'service',
        'annotation string',
        'code global',
        'code class',
        'annotation class',
        'tags',
        'link',
        'function',
      ];
    }

    if ($aggressive_topics) {
      // Look for topics before classes, constants, and files.
      $stages[] = 'topic';
    }
    $stages[] = 'file';
    $stages[] = 'class constant';
    $stages[] = 'constant';
    if ($aggressive_classes) {
      $stages[] = 'class';
    }
    else {
      $stages[] = 'definite class';
    }

    $documentation = self::makeDocumentationLinks($documentation, $branch, $docblock_id, $class_id, $stages);

    // Now remove escaping from \@.
    $documentation = preg_replace('!\\\@!', '@', $documentation);

    // Now use the standard Drupal URL filter to make links out of bare URLs in
    // the text.
    $filter = new \stdClass();
    $filter->callback = '_filter_url';
    $filter->settings = ['filter_url_length' => 72];

    return _filter_url($documentation, $filter);
  }

  /**
   * Makes links in documentation and code.
   *
   * Recursively calls itself to iterate through various stages. At each stage,
   * self::processPattern() is called to find matches for a particular regular
   * expression pattern and process the matches via callback functions
   * self::linkName(), self::linkMemberName(), and self::linkLink().
   *
   * @param string $documentation
   *   PHP code or documentation to scan for text to link.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to make the links in.
   * @param int $docblock_id
   *   Documentation ID of the docblock the code is in (for namespace
   *   information). Can omit if namespaces are not relevant.
   * @param int $class_id
   *   Documentation ID of the class the documentation is in (if any).
   * @param array $stages
   *   Array of stages to process, which determines what type of links to make.
   *
   * @return string
   *   $documentation with text turned into links.
   */
  public static function makeDocumentationLinks($documentation, BranchInterface $branch = NULL, $docblock_id = NULL, $class_id = NULL, array $stages = []) {
    // Pop off the next stage to run.
    $stage = array_shift($stages);

    // For this stage, figure out what regular expression pattern to use for
    // matching, what callback to call from self::processPattern() for pattern
    // matches, and what arguments to pass to the callback function.
    $callback_match = '\Drupal\api\Formatter::linkName';
    $prepend = '';
    $append = '';
    $prepend_if_not_found = NULL;
    $use_php = FALSE;
    $type = '';
    $pattern = '';
    $continue_matching = FALSE;

    switch ($stage) {
      case 'tags':
        // Find HTML tags, not filtered.
        $callback_match = NULL;
        $pattern = '/(<[^>]+?' . '>)/';
        break;

      case 'link':
        // Find @link.
        $pattern = '/' . Parser::RE_TAG_START . 'link\s+(.*)\s+' . Parser::RE_TAG_START . 'endlink/U';
        $callback_match = '\Drupal\api\Formatter::linkLink';
        break;

      case 'function':
        // Find function names, which are preceded by white space and followed
        // by '('.
        $append = '(';
        $pattern = '!' . Parser::RE_WORD_BOUNDARY_START . '(' . Parser::RE_FUNCTION_IN_TEXT . ')\(!';
        $type = 'function';
        $use_php = TRUE;
        break;

      case 'code function':
        // Find function names in marked-up code.
        $pattern = '!<span class="php-function-or-constant"> *(' . Parser::PHP_FUNCTION_PATTERN . ')</span>!';
        $prepend = '<span class="php-function-or-constant">';
        $append = '</span>';
        $type = 'function_or_constant';
        $use_php = TRUE;
        $continue_matching = TRUE;
        break;

      case 'code function declared':
        // Find function names in marked-up code, but this is a declaration,
        // so skip checking built-in PHP functions.
        $pattern = '!<span class="php-function-or-constant-declared"> *(' . Parser::PHP_FUNCTION_PATTERN . ')</span>!';
        $prepend = '<span class="php-function-or-constant">';
        $append = '</span>';
        $type = 'function_or_constant';
        $use_php = FALSE;
        $continue_matching = TRUE;
        break;

      case 'code class':
        // Find class names in marked-up code, as constructors.
        $pattern = '!<span class="(?:php-function-or-constant|php-function-or-constant-declared)">(' . self::RE_CLASS_NAME_TEXT . ')</span>!';
        $prepend = '<span class="php-function-or-constant">';
        $append = '</span>';
        $type = 'class';
        break;

      case 'annotation class':
        // Find annotation class names in marked-up code.
        $pattern = '!<span class="class-annotation">(' . self::RE_CLASS_NAME_TEXT . ')</span>!';
        $prepend = '<span class="php-function-or-constant">';
        $append = '</span>';
        $type = 'annotation';
        $use_php = FALSE;
        break;

      case 'code global':
        // Find global variable names in marked-up code.
        $pattern = '!<span class="php-keyword">global</span> <span class="php-variable">\$(' . Parser::PHP_FUNCTION_PATTERN . ')</span>!';
        $prepend = '<span class="php-keyword">global</span> <span class="php-variable">$';
        $append = '</span>';
        $type = 'global';
        break;

      case 'code string':
        // Find potential function names (callback strings) in marked-up code.
        // These are all strings that are legal function names, where the
        // function name is put into something like a hook_menu() page callback
        // as a string.
        $pattern = '!<span class="php-string">\'(' . Parser::PHP_FUNCTION_PATTERN . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'function';
        $continue_matching = TRUE;
        break;

      case 'code string theme':
        // Find potential theme hook names as strings in marked-up code.
        // Works like 'code string', but looks for theme hook names and links
        // to the theme template or function.
        $pattern = '!<span class="php-string">\'(' . Parser::PHP_FUNCTION_PATTERN . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'theme';
        $continue_matching = TRUE;
        break;

      case 'yaml string':
        // Find potential YAML key strings in marked-up code.
        $pattern = '!<span class="php-string">\'(' . self::RE_YAML_STRING . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'yaml_string';
        $continue_matching = TRUE;
        break;

      case 'service':
        // Find potential service names in marked-up code.
        $pattern = '!<span class="php-string">\'(' . self::RE_YAML_STRING . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'service';
        $continue_matching = TRUE;
        break;

      case 'yaml file':
        // Find potential YAML file name strings in marked-up code.
        $pattern = '!<span class="php-string">\'(' . self::RE_YAML_STRING . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'yaml_file';
        break;

      case 'annotation string':
        // Find potential function, method, and class strings in annotations.
        $pattern = '!<span class="php-string">&quot;(' . Parser::RE_FUNCTION_IN_TEXT . ')&quot;</span>!';
        $prepend = '<span class="php-function-or-constant">"';
        $append = '"</span>';
        $prepend_if_not_found = '<span class="php-string">"';
        $type = 'function_or_constant';
        $continue_matching = TRUE;
        break;

      case 'yaml reference':
        // Find potential function names (callback strings) in marked-up YAML
        // code. These are all strings that are legal function names, possibly
        // with namespaces and class names, possibly in quotes.
        $pattern = '!<span class="yaml-reference">(' . Parser::RE_FUNCTION_IN_TEXT . ')</span>!';
        $prepend = '<span class="php-function-or-constant">';
        $append = '</span>';
        $type = 'yaml_reference';
        break;

      case 'code hook name':
        // Find potential hook names in marked-up code. These are strings that
        // are legal function names, which were found in parsing to be inside
        // module_implements() and related functions.
        $pattern = '!<span class="php-string potential-hook">\'(' . Parser::RE_FUNCTION_CHARACTERS . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'hook';
        break;

      case 'code fieldhook name':
        // Works like 'code hook name' above, but for field hooks.
        $pattern = '!<span class="php-string potential-fieldhook">\'(' . Parser::RE_FUNCTION_CHARACTERS . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'fieldhook';
        break;

      case 'code entityhook name':
        // Works like 'code hook name' above, but for entity hooks.
        $pattern = '!<span class="php-string potential-entityhook">\'(' . Parser::RE_FUNCTION_CHARACTERS . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'entityhook';
        break;

      case 'code userhook name':
        // Works like 'code hook name' above, but for user hooks.
        $pattern = '!<span class="php-string potential-userhook">\'(' . Parser::RE_FUNCTION_CHARACTERS . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'userhook';
        break;

      case 'code alter hook name':
        // Works like 'code hook name' above, but for alter hooks.
        $pattern = '!<span class="php-string potential-alter">\'(' . Parser::RE_FUNCTION_CHARACTERS . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'alter hook';
        break;

      case 'code theme hook name':
        // Works like 'code hook name' above, but for theme hooks.
        $pattern = '!<span class="php-string potential-theme">\'(' . Parser::PHP_FUNCTION_PATTERN . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'theme';
        break;

      case 'code element name':
        // Works like 'code hook name' above, but for render/form elements.
        $pattern = '!<span class="php-string potential-element">\'(' . Parser::PHP_FUNCTION_PATTERN . ')\'</span>!';
        $prepend = '<span class="php-function-or-constant">\'';
        $append = '\'</span>';
        $prepend_if_not_found = '<span class="php-string">\'';
        $type = 'element';
        break;

      case 'code member':
        // Works like 'code hook name' above, but for class members.
        $callback_match = '\Drupal\api\Formatter::linkMemberName';
        $pattern = '!(<span class="(?:php-function-or-constant|php-function-or-constant-declared|php-variable) [^"]+ member-of-[^"]+">\$*' . Parser::PHP_FUNCTION_PATTERN . '</span>)!';
        break;

      case 'file':
        // Find file names, which are an arbitrary number of strings joined with
        // '.'.
        $pattern = '%' . Parser::RE_WORD_BOUNDARY_START . Parser::RE_FILENAME . Parser::RE_WORD_BOUNDARY_END . '%';
        $type = 'file';
        break;

      case 'constant':
        // Find constants, UPPERCASE_LETTERS_WITH_UNDERSCORES.
        $pattern = '/' . Parser::RE_WORD_BOUNDARY_START . '([A-Z_]+)' . Parser::RE_WORD_BOUNDARY_END . '/';
        $type = 'constant';
        break;

      case 'class constant':
        // Find constants, UPPERCASE_LETTERS_WITH_UNDERSCORES, preceeded by a
        // class name and ::.
        $pattern = '/' . Parser::RE_WORD_BOUNDARY_START . '(' . self::RE_CLASS_NAME_TEXT . '::' . '[A-Z_]+)' . Parser::RE_WORD_BOUNDARY_END . '/';
        $type = 'constant';
        break;

      case 'class':
        // Find class names, which have a capital letter.
        $pattern = '/' . Parser::RE_WORD_BOUNDARY_START . '(' . self::RE_CLASS_NAME_TEXT . ')' . Parser::RE_WORD_BOUNDARY_END . '/';
        $type = 'class';
        break;

      case 'definite class':
        // Find definite class names, which have a backslash.
        $pattern = '/' . Parser::RE_WORD_BOUNDARY_START . '(' . self::RE_DEFINITE_CLASS_NAME_TEXT . ')' . Parser::RE_WORD_BOUNDARY_END . '/';
        $type = 'class';
        break;

      case 'topic':
        // Find topic/group names.
        $pattern = '/' . Parser::RE_WORD_BOUNDARY_START . '(' . self::RE_GROUP_NAME . ')' . Parser::RE_WORD_BOUNDARY_END . '/';
        $type = 'group';
        // Patterns that match topics might also match other objects.
        // keep looking for matches after running the linker.
        $continue_matching = TRUE;
        break;
    }

    // See if we have more stages to do. If so, set up to call this function
    // again; if not, we're done.
    $append_if_not_found = $append;
    $prepend_if_no_change = '';
    $append_if_no_change = '';
    if (count($stages) > 0) {
      $callback = '\Drupal\api\Formatter::makeDocumentationLinks';
      if ($continue_matching) {
        // In this case, we want to tell the matching callback function not
        // to prepend or append anything, and self::processPattern() to put back
        // the wrappers if there was no change.
        $prepend_if_no_change = $prepend_if_not_found ?? $prepend;
        $append_if_no_change = $append;
        $prepend_if_not_found = '';
        $append_if_not_found = '';
      }
    }
    else {
      $callback = NULL;
    }

    return self::processPattern(
      $pattern,
      $documentation,
      $callback_match,
      [
        $branch,
        $prepend,
        $append,
        $docblock_id,
        $class_id,
        NULL,
        FALSE,
        $use_php,
        $prepend_if_not_found,
        $append_if_not_found,
        $type,
      ],
      $callback,
      [
        $branch,
        $docblock_id,
        $class_id,
        $stages,
      ],
      $continue_matching,
      $prepend_if_no_change,
      $append_if_no_change
    );
  }

  /**
   * Generates tab navigation for listing pages.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch of the current page.
   * @param string $object_type
   *   (optional) Object type of the listing page; leave out for the branch
   *   home page.
   *
   * @return array
   *   Render array for tab navigation.
   */
  public static function makeNavigation(BranchInterface $branch, $object_type = '') {
    $suffix = self::getPluralObjectType($object_type);

    // Make links to all the other branches' listing pages within this
    // project.
    $project = $branch->getProject();
    $branches = $project->getBranches(TRUE);
    $links = [];
    foreach ($branches as $other_branch) {
      /** @var \Drupal\api\Interfaces\BranchInterface $other_branch */
      if ($suffix) {
        $path = 'api/' . $project->getSlug() . '/' . $suffix . '/' . $other_branch->getSlug();
      }
      else {
        $path = 'api/' . $project->getSlug() . '/' . $other_branch->getSlug();
      }
      $links[$other_branch->getTitle() . $other_branch->id()] = [
        'title' => $other_branch->getTitle(),
        'url' => Url::fromUri('internal:/' . $path),
        'active' => ($other_branch->id() == $branch->id()),
      ];
    }

    if ($links) {
      uksort($links, "strnatcmp");
      // Output this as a Local tasks array.
      $output = [
        '#theme' => 'menu_local_tasks',
        '#primary' => [],
      ];
      foreach (array_reverse($links) as $link) {
        $link['localized_options'] = [];
        $output['#primary'][] = [
          '#theme' => 'menu_local_task',
          '#link' => $link,
          '#active' => $link['active'],
        ];
      }
      return $output;
    }

    return NULL;
  }

  /**
   * See if there is a special defgroup in this branch for the object_type.
   *
   * @param string $object_type
   *   The type to look for.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where to look.
   *
   * @return array
   *   DefGroup render array if found.
   */
  public static function defGroup($object_type, BranchInterface $branch) {
    $output = [];

    $defgroup = DocBlock::getGroupListingPage($object_type, $branch);
    if ($defgroup) {
      $defgroup = DocBlock::load($defgroup);
      $documentation = self::linkDocumentation($defgroup->getDocumentation(), $branch, $defgroup->id(), NULL, FALSE, FALSE, $defgroup->isDrupal());
      $see = self::linkDocumentation($defgroup->getSee(), $branch, $defgroup->id(), NULL, TRUE, TRUE, $defgroup->isDrupal());
      $output['heading'] = [
        '#theme' => 'api_group_page',
        '#documentation' => $documentation,
        '#see' => $see,
        '#object' => $defgroup,
        '#hide_alternatives' => TRUE,
        '#hide_comments' => TRUE,
      ];
    }

    return $output;
  }

  /**
   * Splits a string using a regular expression and processes using callbacks.
   *
   * @param string $pattern
   *   The regular expression to match for splitting.
   * @param string $subject
   *   The string to process.
   * @param string $callback_match
   *   Function name to be called for text which matches $pattern. The first
   *   parameter will be the parenthesized expression in the pattern. Should
   *   return a string. NULL to pass the text through unchanged.
   * @param array $callback_match_arguments
   *   An array of additional parameters for $callback_match.
   * @param string $callback
   *   Function name to be called for text which does not match $pattern. The
   *   first parameter will be the text. Should return a string. NULL to pass
   *   the text through unchanged.
   * @param array $callback_arguments
   *   An array of additional parameters for $callback.
   * @param bool $continue_matching
   *   If TRUE, call $callback again on the matched text if it is left unchanged
   *   by its callback.
   * @param string $prepend_if_not_changed
   *   String to prepend if the match callback makes no change and we are
   *   continuing.
   * @param string $append_if_not_changed
   *   String to append if the match callback makes no change and we are
   *   continuing.
   *
   * @return string
   *   The original string, with both matched and unmatched portions filtered by
   *   the appropriate callbacks.
   */
  public static function processPattern($pattern, $subject, $callback_match = NULL, array $callback_match_arguments = [], $callback = NULL, array $callback_arguments = [], $continue_matching = FALSE, $prepend_if_not_changed = '', $append_if_not_changed = '') {
    $return = '';
    $matched = FALSE;
    foreach (preg_split($pattern . 'sm', $subject, -1, PREG_SPLIT_DELIM_CAPTURE) as $part) {
      // The return values will alternate being unmatched text and delimeters.
      // And note that the "delimiters" are only part of the expression.
      if ($matched) {
        // This is a "delimiter", which is a piece of the matched regular
        // expression (whatever is in parens).
        if (is_null($callback_match)) {
          $return .= $part;
        }
        else {
          $new_text = call_user_func_array($callback_match, array_merge([$part], $callback_match_arguments));

          if ($new_text == $part && $continue_matching) {
            $new_text = call_user_func_array($callback, array_merge([$prepend_if_not_changed . $part . $append_if_not_changed], $callback_arguments));
          }

          $return .= $new_text;
        }
      }
      else {
        // This is a part of the input text that id not match.
        if (is_null($callback)) {
          $return .= $part;
        }
        else {
          $return .= call_user_func_array($callback, array_merge([$part], $callback_arguments));
        }
      }

      // This makes the foreach alternate between thinking it's a delimeter and
      // unmatched text.
      $matched = !$matched;
    }

    return $return;
  }

  /**
   * Turns text into a link, using the first word as the object name.
   *
   * Callback for self::processPattern() in self::makeDocumentationLinks().
   *
   * @param string $name
   *   Text to link.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object indicating which branch to make the link in.
   * @param string $prepend
   *   Text to prepend on the link.
   * @param string $append
   *   Text to append on the link.
   * @param int|null $docblock_id
   *   Documentation ID of the docblock the code is in (for namespace
   *   information). Can omit if namespaces are not relevant.
   * @param int|null $class_id
   *   Documentation ID of the class the link is in (if any).
   *
   * @return string
   *   The text as a link.
   *
   * @see self::linkMemberName()
   * @see self::linkName()
   */
  public static function linkLink($name, BranchInterface $branch = NULL, $prepend = '', $append = '', $docblock_id = NULL, $class_id = NULL) {
    $words = preg_split('/\s+/', trim($name));
    $name = array_shift($words);
    return self::linkName($name, $branch, $prepend, $append, $docblock_id, $class_id, implode(' ', $words), TRUE);
  }

  /**
   * Links text to an appropriate class member variable, constant, or function.
   *
   * Callback for self::processPattern() in self::makeDocumentationLinks().
   *
   * Tries to find something to link to by looking for matches in the following
   * order:
   * - The passed-in branch.
   * - The core branch with the same core compatibility.
   * - An API reference core branch with the same core compatibility.
   * - Any other branch with the same core compatibility.
   * - Any API reference branch with the same core compatibility.
   *
   * @param string $text
   *   Text matched by the regular expression.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object indicating which branch to make the link in.
   * @param string $prepend
   *   Unused.
   * @param string $append
   *   Unused.
   * @param int|null $file_id
   *   Documentation ID of the file the code is in (for namespace information).
   *   Can omit if namespaces are not relevant.
   * @param int|null $class_id
   *   Documentation ID of the class this is part of (if any).
   *
   * @return string
   *   The link.
   *
   * @see self::linkName()
   * @see self::linkLink()
   */
  public static function linkMemberName($text, BranchInterface $branch = NULL, $prepend = '', $append = '', $file_id = NULL, $class_id = NULL) {
    // The pattern matched to get here contains the entire span with all of its
    // classes. Parse it out.
    $matches = [];
    preg_match('!<span class="(?:php-function-or-constant|php-function-or-constant-declared|php-variable) ([^"]+) member-of-([^"]+)"> *(\$*' . Parser::PHP_FUNCTION_PATTERN . ')</span>!', $text, $matches);
    $name = $matches[3];
    $member_type = $matches[2];
    $object_type = $matches[1];

    $prepend = '<span class="php-function-or-constant">';
    $append = '</span>';

    // Strip off a $ if there is one at the start.
    if (strpos($name, '$') === 0) {
      $name = substr($name, 1);
      $prepend .= '$';
    }

    // Get the objects as we'll need them in several places.
    $classDocBlock = NULL;
    $parentDocBlock = NULL;
    if ($class_id) {
      $classDocBlock = DocBlock::load($class_id);
      $parent_class = DocReference::getClassReference($branch, $classDocBlock);
      if ($parent_class) {
        $parent_class = array_shift($parent_class);
        $parentDocBlock = DocReference::load($parent_class);
      }
    }

    /** @var \Drupal\api\Interfaces\BranchInterface $default_branch */
    $default_core_compatibility = '';
    $default_branch = \Drupal::service('api.utilities')->getDefaultBranchProject();
    if ($default_branch) {
      $default_core_compatibility = $default_branch->getCoreCompatibility();
    }

    // Get some information we will need in several places below.
    $name_info = self::getNamespaceInfo($file_id);
    $core_compatibility = ($branch) ?
      $branch->getCoreCompatibility() :
      $default_core_compatibility;

    // Convert parent references to the name of the parent class, which must be
    // in the reference_storage table for this branch.
    if ($member_type == 'parent') {
      if (!$class_id) {
        // We cannot do a parent reference if we do not know the class, so do
        // not make a link at all.
        return $prepend . $name . $append;
      }

      if (!$parentDocBlock) {
        return $prepend . $name . $append;
      }
      $member_type = 'class-' . $parentDocBlock->getObjectName();
    }

    // Try matching in this branch.
    $link = self::makeMatchLinkClassMember($name, $name_info, $member_type, $object_type, $class_id, $file_id, $branch);
    if ($link) {
      return $prepend . $link . $append;
    }

    // Some members marked as self are actually calls to the parent functions.
    if ($member_type == 'self' && $classDocBlock && $parentDocBlock) {
      $parent_member_type = 'class-' . $parentDocBlock->getObjectName();

      // Try matching in this branch from the parent.
      $link = self::makeMatchLinkClassMember($name, $name_info, $parent_member_type, $object_type, $class_id, $file_id, $branch);
      if ($link) {
        return $prepend . $link . $append;
      }
    }

    // If this is not a core branch, try matching in the core branch.
    $core_branch = ($branch) ? Branch::findCoreBranch($branch) : FALSE;
    if ($core_branch && $core_branch->id() != $branch->id()) {
      $link = self::makeMatchLinkClassMember($name, $name_info, $member_type, $object_type, $class_id, $file_id, $core_branch);
      if ($link) {
        return $prepend . $link . $append;
      }
    }

    $external_branches = ExternalBranch::loadMultiple();
    if (!$core_branch) {
      // We do not have a core branch. See if there is an API reference core
      // branch to use, and if we can make a link there.
      $ids = [];
      foreach ($external_branches as $external_branch) {
        if (
          ($external_branch->getType() == 'core') &&
          ($core_compatibility == $external_branch->getCoreCompatibility())
        ) {
          $ids[] = $external_branch->id();
        }
      }

      if (count($ids)) {
        $link = self::makeMatchLinkClassMember($name, $name_info, $member_type, $object_type, $class_id, $file_id, NULL, '', $ids);
        if ($link) {
          return $prepend . $link . $append;
        }
      }
    }

    // Try to find a match at least within this same core compatibility, but
    // only if this was not a core branch.
    if (!$core_branch || ($core_branch->id() != $branch->id())) {
      $link = self::makeMatchLinkClassMember($name, $name_info, $member_type, $object_type, $class_id, $file_id, NULL, $core_compatibility);
      if ($link) {
        return $prepend . $link . $append;
      }

      // Also try API reference branches with this core compatibility.
      $ids = [];
      foreach ($external_branches as $external_branch) {
        if ($core_compatibility == $external_branch->getCoreCompatibility()) {
          $ids[] = $external_branch->id();
        }
      }

      if (count($ids)) {
        $link = self::makeMatchLinkClassMember($name, $name_info, $member_type, $object_type, $class_id, $file_id, NULL, '', $ids);
        if ($link) {
          return $prepend . $link . $append;
        }
      }
    }

    // If we got here, we didn't have a match.
    return $prepend . $name . $append;
  }

  /**
   * Finds matches for a class member object name in a branch and makes a link.
   *
   * Helper function for self::linkMemberName().
   *
   * @param string $name
   *   Name to match (text found in the code).
   * @param array $namespace_info
   *   Namespace information for the file context
   *   (output of self::getNamespaceInfo()).
   * @param string $member_type
   *   What type of reference to find: 'parent', 'self', 'variable', or
   *   'class-NAME'. This is set up in the parser.
   * @param string $object_type
   *   Type of object, such as 'function' if this is a member function, etc.
   * @param int|null $class_id
   *   Documentation ID of the class this is part of (if any).
   * @param int|null $file_id
   *   Documentation ID of the file the code is in (for namespace information).
   *   Can omit if namespaces are not relevant.
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Object representing the branch to search. If NULL, use core compatibility
   *   instead.
   * @param string $core_compatibility
   *   If $branch is NULL, search all branches with this core compatibility.
   * @param array|null $external_branch_ids
   *   If set, instead of looking in core branches, look in API reference
   *   branches that have an ID in this array.
   *
   * @return string|false
   *   Link to either a single matching object or a search if multiple matches
   *   exist; if there are no matches, FALSE.
   */
  public static function makeMatchLinkClassMember($name, array $namespace_info, $member_type, $object_type, $class_id = NULL, $file_id = NULL, BranchInterface $branch = NULL, $core_compatibility = '', $external_branch_ids = NULL) {
    $branch_ids = is_null($branch) ?
      Branch::sameCoreCompatibilityBranches($core_compatibility) :
      [$branch->id()];

    // If we're looking for a specific class, see if it exists in this branch
    // or the reference branch.
    if (strpos($member_type, 'class-') === 0) {
      $class_name = substr($member_type, 6);
      $namespaced_name = self::fullClassname($class_name, $namespace_info['namespace'], $namespace_info['use_alias']);
      if ($external_branch_ids) {
        // If we are looking in a reference branch, do the query to find this
        // exact member name $class_name::$name -- we cannot do anything very
        // fancy in that case, because we do not have the full member
        // information.
        $result = ExternalDocumentation::findByNamespaceNameAndType($namespaced_name . '::' . $name, $object_type, $external_branch_ids);
        if ($result) {
          $result = array_shift($result);
          $result = ExternalDocumentation::load($result);
          $options = [
            'attributes' => [
              'title' => self::entityDecode($result->getSummary()),
            ],
          ];
          return Link::fromTextAndUrl($name, Url::fromUri($result->getUrl(), $options))->toString();
        }
        else {
          return FALSE;
        }
      }

      // If we get here, we are looking in a real branch or compatible branches.
      // We want to find a matching class_docblock, and then convert this to
      // being a "self" reference on that class.
      $results = DocBlock::findClassesByNamespacedName($namespaced_name, $branch_ids);
      if ($results) {
        $class_id = array_shift($results);
        $member_type = 'self';
      }
      else {
        // Class is not in this branch.
        return FALSE;
      }
    }

    $result = NULL;
    $using_api_branch = FALSE;
    $result_contains_full_objects = FALSE;

    if ($member_type == 'self') {
      // Type 'self' does not make sense for API reference branches.
      if ($external_branch_ids) {
        return FALSE;
      }

      // Looking for a member of a particular class, or one of several classes,
      // whose documentation ID we have already located. Use the {api_members}
      // table to find the right method, since it includes members inherited
      // from parent classes.
      if (!$class_id) {
        return FALSE;
      }

      $classDocBlock = DocBlock::load($class_id);
      $result = $classDocBlock->getDocBlockClassMembers($name, ($object_type == 'function'));
      $result_contains_full_objects = TRUE;
    }
    elseif ($member_type == 'variable') {
      // This was some kind of a variable like $foo->member(). So match any
      // member of any class in this branch or reference branch.
      if ($external_branch_ids) {
        // Looking in API reference branches.
        $result = ExternalDocumentation::findByMemberName($name, $external_branch_ids, ($object_type == 'function'));
        $using_api_branch = TRUE;
      }
      else {
        // Looking in regular documentation branches.
        $result = !empty($branch_ids) ?
          DocBlock::findByMemberName($name, $branch_ids, ($object_type == 'function')) :
          [];
      }
    }

    // See if we have one result, more than one result, or no results.
    $matches = [];
    if (!empty($result)) {
      if ($result_contains_full_objects) {
        $matches = $result;
      }
      else {
        $matches = ($using_api_branch) ?
          ExternalDocumentation::loadMultiple($result) :
          DocBlock::loadMultiple($result);
      }
    }

    // No matches.
    if (!count($matches)) {
      return FALSE;
    }

    // If we found one match, return its URL as a link.
    if (count($matches) == 1) {
      $object = array_shift($matches);
      $options = [
        'attributes' => [
          'title' => self::entityDecode($object->getSummary()),
          'class' => ['local'],
        ],
      ];

      if ($using_api_branch) {
        /** @var \Drupal\api\Interfaces\ExternalDocumentationInterface $object */
        $url = $object->getUrl();
        unset($options['attributes']['class']);
      }
      else {
        $url = self::objectUrl($object);
      }
      return Link::fromTextAndUrl($name, Url::fromUri($url, $options))->toString();
    }

    // If we found multiple matches, make a search URL.
    // If we found multiple matches in the case of a multi-branch
    // search, we should probably go to a multi-branch search page, but this
    // does not exist yet. So just go to the first found branch.
    $options = [
      'attributes' => [
        'title' => t('Multiple implementations exist.'),
        'class' => ['local'],
      ],
    ];

    $object = array_shift($matches);
    if ($using_api_branch) {
      /** @var \Drupal\api\Interfaces\ExternalDocumentationInterface $object */
      $api_branch = $object->getExternalBranch();
      $url = $api_branch->getSearchUrl() . $name;
      unset($options['attributes']['class']);
    }
    else {
      /** @var \Drupal\api\Interfaces\DocBlockInterface $object */
      $url = 'internal:/api/' . $object->getBranch()->getProject()->getSlug() . '/' . $object->getBranch()->getSlug() . '/search/' . $name;
    }

    return Link::fromTextAndUrl($name, Url::fromUri($url, $options))->toString();
  }

  /**
   * Links an object name to its documentation.
   *
   * Callback for self::processPattern() in self::makeDocumentationLinks().
   * Can also be called directly.
   *
   * Tries to find something to link to by looking for matches in the following
   * order:
   * - The passed-in branch.
   * - PHP reference branches, if $use_php is TRUE.
   * - The core branch with the same core compatibility.
   * - An API reference core branch with the same core compatibility.
   * - Any other branch with the same core compatibility.
   * - Any API reference branch with the same core compatibility.
   *
   * @param string $name
   *   Object name to link to.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object indicating which branch to make the link in.
   * @param string $prepend
   *   Text to prepend on the link.
   * @param string $append
   *   Text to append on the link.
   * @param int $docblock_id
   *   Documentation ID of the docblock the code is in (for namespace
   *   information). Can omit if namespaces are not relevant.
   * @param int $class_id
   *   (unused) Documentation ID of the class this is part of (if any).
   * @param string $text
   *   Link text. If omitted, uses $name.
   * @param bool $is_link
   *   TRUE if this was inside a @link.
   * @param bool $use_php
   *   TRUE if links to functions found in PHP reference branches should be
   *   checked for and made; FALSE to skip this (normally only set to TRUE if
   *   this is a Drupal-specific thing like a hook name and thus looking for PHP
   *   reference functions would be pointless). API reference branches are
   *   always checked regardless of $use_php.
   * @param string $prepend_if_not_found
   *   Text to prepend if object is not found (defaults to $prepend).
   * @param string $append_if_not_found
   *   Text to append if object is not found (defaults to $append).
   * @param string $type
   *   The type of information $name represents. Possible values:
   *   - '': (default) $name is a normal object name.
   *   - 'hook': $name is a hook name.
   *   - 'fieldhook': $name is a field hook name.
   *   - 'entityhook': $name is an entity hook name.
   *   - 'userhook': $name is a user hook name.
   *   - 'alter hook': $name is an alter hook name.
   *   - 'theme': $name is a theme hook name.
   *   - 'element': $name is a form/render element name.
   *   - 'function': $name is specifically a function ('file', 'constant', etc.
   *     also are supported).
   *   - 'function_or_constant': $name is either a function, constant, or class.
   *   - 'annotation': $name is an annotation class name.
   *   - 'group': $name is a group/topic identifier.
   *   - 'global': $name is a global variable.
   *   - 'yaml_reference': $name is the possibly namespaced name of a function
   *     or method, or class, possibly in single or double quotes (in a YAML
   *     file).
   *   - 'yaml_string': $name is a string that could be a 'yaml string'
   *     reference.
   *   - 'yaml_file': $name is possibly the name of a YAML config file (missing
   *     the .yml extension).
   *   - 'service': $name is the name of a service.
   *
   * @return string
   *   The text as a link to the object page.
   *
   * @see self::linkMemberName()
   * @see self::linkLink()
   */
  public static function linkName($name, BranchInterface $branch = NULL, $prepend = '', $append = '', $docblock_id = NULL, $class_id = NULL, $text = NULL, $is_link = FALSE, $use_php = TRUE, $prepend_if_not_found = NULL, $append_if_not_found = NULL, $type = '') {
    if (!$text) {
      $text = $name;
    }
    $name = trim($name);

    if ($type == 'yaml_reference') {
      // Trim off quotes and match as any object name.
      $name = trim($name, '"\'');
      $type = 'function_or_constant';
      $is_link = FALSE;
    }
    elseif ($type == 'yaml_file') {
      // Add .yml and match as a file.
      $name = $name . '.yml';
      $type = 'file';
      $is_link = FALSE;
    }

    /** @var \Drupal\api\Interfaces\BranchInterface $default_branch */
    $default_core_compatibility = '';
    $default_branch = \Drupal::service('api.utilities')->getDefaultBranchProject();
    if ($default_branch) {
      $default_core_compatibility = $default_branch->getCoreCompatibility();
    }

    // Get some information we will need in several places below.
    $name_info = self::getNamespaceInfo($docblock_id);
    // NOTE FOR D9: special case with $class_id.
    $namespaced_name = self::fullClassname($name, $name_info['namespace'], $name_info['use_alias'], $class_id);
    $core_compatibility = ($branch) ?
      $branch->getCoreCompatibility() :
      $default_core_compatibility;

    // If we get here, we're looking to match some kind of documentation object.
    // Try to match within the passed-in branch.
    if ($branch) {
      $link = self::makeMatchLink($name, $namespaced_name, $text, $type, $is_link, $branch);
      if ($link) {
        return $prepend . $link . $append;
      }

      // Also try finding a link to a class member.
      $link = self::makeMatchMemberLink($namespaced_name, $text, $branch);
      if ($link) {
        return $prepend . $link . $append;
      }
    }

    // If we get here, there wasn't a match. Try PHP functions.
    if ($use_php) {
      $link = self::makePhpReferenceLink($name, $text);
      if ($link) {
        return $prepend . $link . $append;
      }
    }

    // If this is not a core branch, try matching in the core branch.
    $core_branch = ($branch) ? Branch::findCoreBranch($branch) : NULL;
    if ($core_branch && $core_branch->id() != $branch->id()) {
      $link = self::makeMatchLink($name, $namespaced_name, $text, $type, $is_link, $core_branch);
      if ($link) {
        return $prepend . $link . $append;
      }

      // Also try finding a link to a class member.
      $link = self::makeMatchMemberLink($namespaced_name, $text, $core_branch);
      if ($link) {
        return $prepend . $link . $append;
      }
    }

    $external_branches = ExternalBranch::loadMultiple() ?? [];
    if (!$core_branch) {
      // We do not have a core branch. See if there is an API reference core
      // branch to use, and if we can make a link there.
      $ids = [];
      foreach ($external_branches as $external_branch) {
        // No internal branches set, only external ones.
        if (empty($core_compatibility)) {
          $core_compatibility = $external_branch->getCoreCompatibility();
        }

        if (
          ($external_branch->getType() == 'core') &&
          ($core_compatibility == $external_branch->getCoreCompatibility())
        ) {
          $ids[] = $external_branch->id();
        }
      }

      if (count($ids)) {
        $link = self::makeMatchLink($name, $namespaced_name, $text, $type, $is_link, NULL, '', $ids);
        if ($link) {
          return $prepend . $link . $append;
        }
      }
    }

    // Try to find a match at least within this same core compatibility, but
    // only if this was not a core branch.
    if (!$core_branch || $core_branch->id() != $branch->id()) {
      $link = self::makeMatchLink($name, $namespaced_name, $text, $type, $is_link, NULL, $core_compatibility);
      if ($link) {
        return $prepend . $link . $append;
      }

      // Also try finding a link to a class member.
      $link = self::makeMatchMemberLink($namespaced_name, $text, NULL, $core_compatibility);
      if ($link) {
        return $prepend . $link . $append;
      }

      // Also try API reference branches with this core compatibility.
      $ids = [];
      foreach ($external_branches as $external_branch) {
        if ($core_compatibility == $external_branch->getCoreCompatibility()) {
          $ids[] = $external_branch->id();
        }
      }

      if (count($ids)) {
        $link = self::makeMatchLink($name, $namespaced_name, $text, $type, $is_link, NULL, '', $ids);
        if ($link) {
          return $prepend . $link . $append;
        }
      }
    }

    // If we get here, there still wasn't a match, so return non-linked text.
    if (isset($prepend_if_not_found)) {
      $prepend = $prepend_if_not_found;
    }
    if (isset($append_if_not_found)) {
      $append = $append_if_not_found;
    }

    return $prepend . $text . $append;
  }

  /**
   * Checks if the name found has higher or lower priority than previous match.
   *
   * Helper function for self::linkName(), to distinguish between theme
   * functions and theme templates.
   *
   * @param string $current
   *   Current matched string.
   * @param string $previous
   *   Previous matched string.
   * @param bool $prefer_shorter
   *   TRUE to prefer shorter names.
   * @param string[] $potential_names
   *   Array of the potential names we were matching on.
   * @param bool $prefer_earlier
   *   TRUE to prefer earlier matches in list of potential names.
   *
   * @return int
   *   1 if previous is empty or current has higher priority. 0 if they have
   *   the same priority. -1 if current has lower priority.
   */
  protected static function matchPriority($current, $previous, $prefer_shorter, array $potential_names, $prefer_earlier) {
    if (strlen($previous) == 0) {
      return 1;
    }
    if ($current == $previous) {
      return 0;
    }

    $extension = (strpos($current, '.tpl.php') === FALSE) ?
      '.html.twig' :
      '.tpl.php';
    $extension_length = strlen($extension);

    // Theme templates have higher priority than theme functions.
    $current_is_theme_function = (strpos($current, 'theme_') === 0);
    $current_is_theme_template = (strpos($current, $extension) === strlen($current) - $extension_length);
    $previous_is_theme_function = (strpos($previous, 'theme_') === 0);
    $previous_is_theme_template = (strpos($previous, $extension) === strlen($previous) - $extension_length);
    if ($current_is_theme_function && $previous_is_theme_template) {
      return -1;
    }
    if ($current_is_theme_template && $previous_is_theme_function) {
      return 1;
    }

    // Prefer the shorter item.
    if ($prefer_shorter && (strlen($current) < strlen($previous))) {
      return 1;
    }
    if ($prefer_shorter && (strlen($previous) < strlen($current))) {
      return -1;
    }

    // Prefer the earlier item.
    if ($prefer_earlier) {
      $current_index = array_search($current, $potential_names);
      $previous_index = array_search($previous, $potential_names);
      if ($current_index === FALSE) {
        return -1;
      }
      if ($previous_index === FALSE) {
        return 1;
      }
      if ($current_index < $previous_index) {
        return 1;
      }
      if ($current_index > $previous_index) {
        return -1;
      }
    }

    // All things being equal...
    return 0;
  }

  /**
   * Loads namespace and use information for a file.
   *
   * @param int $docblock_id
   *   Documentation ID of the file.
   *
   * @return array
   *   Associative array with elements:
   *   - namespace: Name of the namespace for this file (could be '').
   *   - use_alias: Associative array of use statement class aliases. Keys are
   *     the alias names, and values are the fully namespaced class names.
   */
  public static function getNamespaceInfo($docblock_id = NULL) {
    $ret = [
      'namespace' => '',
      'use_alias' => [],
    ];
    if (!isset($docblock_id) || !$docblock_id) {
      return $ret;
    }

    $docBlock = DocBlock::load($docblock_id);
    $ids = DocNamespace::getByDocBlock($docBlock);
    $values = DocNamespace::loadMultiple($ids);
    foreach ($values as $info) {
      /** @var \Drupal\api\Interfaces\DocBlock\DocNamespaceInterface $info */
      // Start namespaces with backslash.
      $name = $info->getClassName();
      if (mb_substr($name, 0, 1) != '\\') {
        $name = '\\' . $name;
      }

      if ($info->getObjectType() == 'namespace') {
        $ret['namespace'] = $name;
      }
      else {
        $ret['use_alias'][$info->getClassAlias()] = $name;
      }
    }

    return $ret;
  }

  /**
   * Finds matches for an object name in a branch and makes a link.
   *
   * Helper function for api_link_name().
   *
   * @param string $name
   *   Name to match (text found in the code or documentation).
   * @param string $namespaced_name
   *   Fully namespaced name to look for. Only used when $type is 'function',
   *   'function_or_constant', or 'class'. In these cases, the function tries to
   *   find matches of the namespaced name first, and if that fails, then it
   *   tries again with the plain name.
   * @param string $text
   *   Text to put in the link.
   * @param string $type
   *   Type of object to match (see api_link_name() for options).
   * @param bool $try_link
   *   If TRUE, try making links as if this is to a topic or file first.
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Object representing the branch to search. If NULL, use core compatibility
   *   instead.
   * @param string $core_compatibility
   *   If $branch is NULL, search all branches with this core compatibility.
   * @param array|null $external_branch_ids
   *   If set, instead of looking in core branches, look in API reference
   *   branches that have an ID in this array.
   *
   * @return string|false
   *   Link to either a single matching object or a search if multiple matches
   *   exist; if there are no matches, FALSE.
   */
  public static function makeMatchLink($name, $namespaced_name, $text, $type, $try_link = FALSE, BranchInterface $branch = NULL, $core_compatibility = '', $external_branch_ids = NULL) {
    if ($try_link) {
      // Before trying standard matches, see if this is a link to a group/topic.
      $link = self::makeMatchLink($name, $namespaced_name, $text, 'group', FALSE, $branch, $core_compatibility, $external_branch_ids);
      if ($link) {
        return $link;
      }

      // Also see if it could be a file name being linked.
      $link = self::makeMatchLink($name, $namespaced_name, $text, 'file', FALSE, $branch, $core_compatibility, $external_branch_ids);
      if ($link) {
        return $link;
      }
    }

    $res = ExtendedQueries::findMatchesAdvanced($name, $namespaced_name, $type, $branch, $core_compatibility, $external_branch_ids);
    $results = $res['results'] ?? [];
    $potential_names = $res['potential_names'] ?? [];
    $search_name_field = $res['search_name_field'] ?? '';
    $prefer_shorter = $res['prefer_shorter'] ?? FALSE;
    $prefer_earlier = $res['prefer_earlier'] ?? FALSE;
    $using_external_branch = $res['using_external_branch'] ?? FALSE;
    $original_name = $res['original_name'] ?? $name;

    // Now do the standard linking tries.
    $best = [];
    $name_matched = '';
    $preferred_matched = 0;
    foreach ($results as $object) {
      // MySQL is not case-sensitive, so check the match for exact string.
      $matched = $object->match_name;
      if (!in_array($matched, $potential_names)) {
        continue;
      }

      // See if this matched name takes precedence over the previous one.
      $priority = self::matchPriority($matched, $name_matched, $prefer_shorter, $potential_names, $prefer_earlier);
      if ($priority == 0 && ($preferred_matched == $object->preferred)) {
        // Same priority: add to array.
        $best[] = $object;
      }
      elseif ($priority > 0 || ($object->preferred == 1 && $preferred_matched == 0)) {
        // Higher priority: start new array.
        $best = [$object];
        $name_matched = $matched;
        $preferred_matched = 1;
      }
    }

    if (!count($best) || ($type == 'group' && count($best) != 1)) {
      // If we didn't find anything, and we were trying to match within a
      // namespace, try to match without the namespace.
      if (in_array($type, ['function', 'function_or_constant', 'class']) && ('\\' . $original_name != $namespaced_name)) {
        return self::makeMatchLink($original_name, '\\' . $original_name, $text, $type, FALSE, $branch, $core_compatibility, $external_branch_ids);
      }

      return FALSE;
    }

    // If we get here, we found one or more matches.
    $count = count($best);
    $best = $best[0];

    // If we found just one, make a link and return.
    if ($count == 1) {
      $options = [
        'attributes' => [
          'title' => self::entityDecode($best->summary ?? $best->title),
          'class' => ['local'],
        ],
      ];

      if ($using_external_branch) {
        $url = $best->url;
        unset($options['attributes']['class']);
      }
      else {
        $url = self::objectUrl($best);
      }

      if ($type == 'group' && $text == $name) {
        // Override with the group name if no link text was provided.
        $text = $best->title;
      }
      elseif ($type == 'group') {
        // If this is a topic link and someone provided text, then it was
        // check_plained at parse time. Don't double-encode it!
        $options['html'] = TRUE;
      }

      return Link::fromTextAndUrl($text, Url::fromUri($url, $options))->toString();
    }

    // If we get here, we found multiple matches. So, return a link to a search
    // page. Go to the first found branch.
    if (!empty($search_name_field)) {
      $search_name = $best->search_name;
    }
    else {
      $search_name = $best->match_name;
    }

    $options = [
      'attributes' => [
        'title' => t('Multiple implementations exist.'),
        'class' => ['local'],
      ],
    ];

    if ($using_external_branch) {
      $external_branches = ExternalBranch::loadMultiple();
      $external_branch = $external_branches[$best->branch];
      $url = $external_branch->getSearchUrl();
      unset($options['attributes']['class']);
    }
    else {
      $docBlock = DocBlock::load($best->id);
      $url = 'internal:/api/' . $docBlock->getBranch()->getProject()->getSlug() . '/' . $docBlock->getBranch()->getSlug() . '/search/';
    }
    $url = $url . $search_name;

    return Link::fromTextAndUrl($text, Url::fromUri($url, $options))->toString();
  }

  /**
   * Decodes HTML entities.
   *
   * @param string $text
   *   Text to decode.
   *
   * @return string
   *   Text with all HTML entities decoded.
   */
  public static function entityDecode($text) {
    $text = Html::decodeEntities($text);
    // html_entity_decode does not decode numeric entities, and there are
    // many cases of &#39; (quote) in here.
    $text = str_replace('&#039;', "'", $text);
    $text = str_replace('&#39;', "'", $text);
    return $text;
  }

  /**
   * Constructs a link to an API object page.
   *
   * @param object|\Drupal\api\Interfaces\DocBlockInterface $object
   *   An API object with object_type, object_name, branch, and file_name
   *   properties.
   * @param bool $file
   *   TRUE links to the object’s containing file, FALSE links to the object
   *   itself.
   *
   * @return string
   *   A URL string, or an empty string if there was a problem.
   */
  public static function objectUrl($object, $file = FALSE) {
    $docBlock = $object;
    if (!$docBlock instanceof DocBlockInterface) {
      $docBlock = !empty($object->id) ? DocBlock::load($object->id) : NULL;
      if (!$docBlock) {
        return '';
      }
    }

    // Double check that branch and project do exist as some data is deleted
    // via queues and might not be present.
    $branch = $docBlock->getBranch();
    if (!$branch) {
      return '';
    }
    $project = $branch->getProject();
    if (!$project) {
      return '';
    }

    $replaced_string = self::getReplacementName($docBlock->getFileName());
    if ($file || $docBlock->getObjectType() === 'file') {
      return 'internal:/api/' . $project->getSlug() . '/' . $replaced_string . '/' . $branch->getSlug();
    }
    else {
      return 'internal:/api/' . $project->getSlug() . '/' . $replaced_string . '/' . $docBlock->getObjectType() . '/' . $docBlock->getObjectName() . '/' . $branch->getSlug();
    }
  }

  /**
   * Applies the replacement pattern to a given string for files, namespaces...
   *
   * @param string $string
   *   String to process.
   * @param string $type
   *   Type of replacement (ie: file, namespace)
   * @param bool $reverse
   *   Apply the replacement in reverse.
   *
   * @return string
   *   String with the replaced characters.
   */
  public static function getReplacementName($string, $type = 'file', $reverse = FALSE) {
    if (!in_array($type, ['namespace', 'file'])) {
      return $string;
    }

    $separator = ($type == 'namespace') ?
      self::NAMESPACE_SEPARATOR :
      self::FILEPATH_SEPARATOR;

    if ($reverse) {
      return str_replace(
        self::FILEPATH_SEPARATOR_REPLACEMENT,
        $separator,
        $string
      );
    }

    return str_replace(
      $separator,
      self::FILEPATH_SEPARATOR_REPLACEMENT,
      $string
    );
  }

  /**
   * Finds matches for an object name in a PHP reference branch to make a link.
   *
   * @param string $name
   *   Name to match (text found in the code or documentation).
   * @param string $text
   *   Text to put in the link.
   *
   * @return string|false
   *   Link to either a single matching object or a search if multiple matches
   *   exist; if there are no matches, FALSE.
   */
  public static function makePhpReferenceLink($name, $text) {
    $ids = PhpDocumentation::findByName($name);
    if ($ids) {
      $matches = PhpDocumentation::loadMultiple($ids);
      foreach ($matches as $info) {
        // MySQL is not case-sensitive, so check the match for exact string.
        if ($info->getObjectName() != $name) {
          continue;
        }

        $pattern = $info->getPhpBranch()->getFunctionUrlPattern();
        $link = strtr($pattern, ['!function' => $name]);
        return Link::fromTextAndUrl(
          $text,
          Url::fromUri(
            $link,
            [
              'attributes' => [
                'title' => self::entityDecode($info->getDocumentation()),
                'class' => ['php-manual'],
              ],
            ]
          )
        )->toString();
      }
    }

    // If we get here, we id not find a match in the PHP reference branches.
    return FALSE;
  }

  /**
   * Finds matches for a member object name in a branch and makes a link.
   *
   * Helper function for api_link_name().
   *
   * @param string $namespaced_name
   *   Fully namespaced name to look for. It must contain :: and this will be
   *   used to separate it into the class name and member name.
   * @param string $text
   *   Text to put in the link.
   * @param \Drupal\api\Interfaces\BranchInterface|null $branch
   *   Object representing the branch to search. If NULL, use core compatibility
   *   instead.
   * @param string $core_compatibility
   *   If $branch is NULL, search all branches with this core compatibility.
   *
   * @return string|false
   *   Link to a single matching object if one is found, or FALSE if there is
   *   not one.
   */
  public static function makeMatchMemberLink($namespaced_name, $text, BranchInterface $branch = NULL, $core_compatibility = '') {
    // Figure out the class name and member name.
    $parts = explode('::', $namespaced_name);
    if (count($parts) != 2) {
      return FALSE;
    }
    $class_name = $parts[0];
    $member_name = $parts[1];

    $results = ExtendedQueries::findMatchingMembersAdvanced($class_name, $member_name, $branch, $core_compatibility);
    if (count($results) != 1) {
      return FALSE;
    }

    $found = NULL;
    foreach ($results as $object) {
      // MySQL is not case-sensitive, so check the match for exact string.
      if ($object->classname == $class_name && ($object->member1 == $member_name || $object->member2 == $member_name)) {
        if ($found) {
          // This is a second match, so forget it.
          return FALSE;
        }
        else {
          $found = $object;
        }
      }
    }
    if (!$found) {
      return FALSE;
    }

    $options = [
      'attributes' => [
        'title' => self::entityDecode($found->summary),
        'class' => ['local'],
      ],
    ];

    $url = self::objectUrl($found);
    return Link::fromTextAndUrl($text, Url::fromUri($url, $options))->toString();
  }

  /**
   * Generates a link to the projects page if there is more than one project.
   *
   * @param bool $wrap_in_p
   *   TRUE to wrap in a P tag; FALSE to not do that.
   *
   * @return string
   *   Link to the API projects page, maybe inside a P tag, or an empty string
   *   if there would only be one project to display on that page.
   */
  public static function otherProjectsLink($wrap_in_p = TRUE) {
    // Figure out how many different projects there are.
    $count = count(Project::loadMultiple());
    if ($count > 1) {
      $text = '';
      if ($wrap_in_p) {
        $text .= '<p class="api-switch"><strong>';
      }
      $text .= Link::fromTextAndUrl(t('Other projects'), Url::fromUri('internal:/api/projects'))->toString();
      if ($wrap_in_p) {
        $text .= '</strong></p>';
      }

      return $text;
    }

    return '';
  }

  /**
   * Get the string in singular format.
   *
   * @param string $object_type
   *   String in plural.
   *
   * @return string
   *   String in singular.
   */
  protected static function getSingularObjectType(string $object_type) {
    $choices = [
      'functions' => 'function',
      'files' => 'file',
      'constants' => 'constant',
      'globals' => 'global',
      'groups' => 'group',
      'classes' => 'class',
      'namespaces' => 'namespace',
      'deprecated' => 'deprecated',
      'services' => 'service',
      'elements' => 'element',
    ];
    return $choices[$object_type] ?? $object_type;
  }

  /**
   * Get the string in plural format.
   *
   * @param string $object_type
   *   String in singular.
   *
   * @return string
   *   String in plural.
   */
  protected static function getPluralObjectType(string $object_type) {
    $choices = [
      'function' => 'functions',
      'file' => 'files',
      'constant' => 'constants',
      'global' => 'globals',
      'group' => 'groups',
      'class' => 'classes',
      'namespace' => 'namespaces',
      'deprecated' => 'deprecated',
      'service' => 'services',
      'element' => 'elements',
    ];
    return $choices[$object_type] ?? $object_type;
  }

  /**
   * Prepare the render array to display a listing.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Object representing branch to generate the listing for.
   * @param string $object_type
   *   Type of object to list ('file', 'group', etc.), or 'deprecated' for the
   *   Deprecated page.
   * @param bool $is_page
   *   (optional) TRUE if this is on its own page (default), FALSE if it is
   *   included in another page.
   *
   * @return array|null
   *   Render array for the listing page, or NULL if the page doesn't work.
   */
  public static function preparePageListingVariables(BranchInterface $branch, $object_type, $is_page = TRUE) {
    $variables = [];

    // Object type is given in the URL in plural form, but we need it in
    // singular form for everything else.
    $object_type = self::getSingularObjectType($object_type);

    // Set the HTML page title and breadcrumb.
    if ($is_page) {
      $titles = [
        'class' => t('Classes, traits, and interfaces'),
        'constant' => t('Constants'),
        'file' => t('Files'),
        'function' => t('Functions'),
        'global' => t('Globals'),
        'group' => t('Topics'),
        'namespace' => t('Namespaces'),
        'deprecated' => t('Deprecated'),
        'service' => t('Services'),
        'element' => t('Form and render elements'),
      ];
      $title = $titles[$object_type] ?? $object_type;
      $page_title = [
        Html::escape($title),
        Html::escape($branch->getTitle()),
        Html::escape($branch->getProject()->getTitle()),
      ];
      $page_title = implode(' - ', $page_title);

      $variables['#title'] = $page_title;
    }

    if ($is_page) {
      // Tabbed navigation to switch between branches.
      $variables['navigation'] = self::makeNavigation($branch, $object_type);
    }

    // See if there is a special defgroup in this branch with the name
    // 'listing_page_OBJECT_TYPE'. If so, display its documentation.
    $variables += self::defGroup($object_type, $branch);

    if ($object_type === 'function' || $object_type == 'constant' || $object_type == 'global') {
      $variables['listing'] = views_embed_view('api_listings', 'block_listing', $object_type, $branch->id());
    }
    elseif ($object_type === 'group') {
      $variables['listing'] = views_embed_view('api_listings', 'block_groups', $branch->id());
    }
    elseif ($object_type === 'service') {
      $variables['listing'] = views_embed_view('api_listings', 'block_services', $branch->id());
    }
    elseif ($object_type === 'element') {
      $variables['listing'] = views_embed_view('api_listings', 'block_elements', $branch->id());
    }
    elseif ($object_type === 'file') {
      $variables['listing'] = views_embed_view('api_listings', 'block_files', $branch->id());
    }
    elseif ($object_type === 'class') {
      $variables['listing'] = views_embed_view('api_listings', 'block_class', $branch->id());
    }
    elseif ($object_type === 'namespace') {
      $variables['listing'] = views_embed_view('api_namespaces', 'block_namespace_list', $branch->id());
    }
    elseif ($object_type === 'deprecated') {
      $variables['listing'] = views_embed_view('api_listings', 'block_deprecated', $branch->id());
    }

    if ($is_page) {
      $variables['suffix'] = [
        '#markup' => self::otherProjectsLink(),
      ];
    }

    return $variables;
  }

  /**
   * Prepare the render array to display a function call.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   Docblock element.
   * @param string $type
   *   Type of function call.
   *
   * @return array
   *   Render array.
   */
  public static function prepareFunctionCallVariables(DocBlockInterface $docBlock, $type) {
    $branch = $docBlock->getBranch();
    $count = ExtendedQueries::findReferences($docBlock, $branch, $type, TRUE, $docBlock->id(), 0, $docBlock->isDrupal());
    $title = self::referenceText($type, $count, $docBlock);

    return [
      '#title' => $title,
      '#markup' => self::listReferences($docBlock, $branch, $type, 0, $docBlock->isDrupal()),
    ];
  }

  /**
   * Prepare the render array to display a branch.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to display.
   *
   * @return array
   *   Render array.
   */
  public static function prepareBranchVariables(BranchInterface $branch) {
    $variables = [
      '#title' => $branch->getProject()->getTitle() . ' - ' . $branch->getTitle(),
    ];

    // Tabbed navigation to switch between branches.
    $variables['navigation'] = self::makeNavigation($branch);

    // Main documentation.
    $doc = DocBlock::getMainpage($branch);
    if ($doc) {
      $doc = DocBlock::load($doc);
      $variables['docs'] = [
        '#markup' => self::linkDocumentation($doc->getDocumentation(), $branch),
      ];
    }
    else {
      $variables['docs'] = [
        '#theme' => 'api_branch_default_page',
        '#branch' => $branch,
        '#api_admin_permission' => \Drupal::currentUser()->hasPermission('administer API reference'),
        '#types' => DocBlock::getListingTypes($branch),
        '#def_group' => self::defGroup('group', $branch),
        '#topics' => views_embed_view('api_listings', 'block_groups', $branch->id()),
        '#other_projects' => (bool) count(Project::loadMultiple()),
        '#search_form' => \Drupal::formBuilder()->getForm(SearchForm::class, $branch),
      ];
    }

    return $variables;
  }

  /**
   * Prepare the render array to display a file.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $file
   *   File to display.
   *
   * @return array
   *   Render array.
   */
  public static function preparePageFileVariables(DocBlockInterface $file) {
    $branch = $file->getBranch();

    $documentation = self::linkDocumentation($file->getDocumentation(), $branch, $file->id(), NULL, FALSE, FALSE, $file->isDrupal());
    $see = self::linkDocumentation($file->getSee(), $branch, $file->id(), NULL, TRUE, TRUE, $file->isDrupal());
    $deprecated = self::linkDocumentation($file->getDeprecated(), $branch, $file->id(), NULL, TRUE, FALSE, $file->isDrupal());
    $related_topics = views_embed_view('api_references', 'block_related_topics', $file->id());
    $code = self::linkCode($file->getCode(), $branch, $file->id(), NULL, $file->isDrupal());
    $defined = $file->getFileName();

    $types = [
      'function' => t('Functions'),
      'constant' => t('Constants'),
      'global' => t('Globals'),
      'class' => t('Classes'),
      'interface' => t('Interfaces'),
      'trait' => t('Traits'),
      'service' => t('Services'),
    ];

    $objects = [];
    foreach ($types as $type => $label) {
      // Note that Views arguments must not contain / characters, because it
      // screws up the parsing if you do an Ajax sort later on. We will undo the
      // replacement in a view plugin.
      // https://www.drupal.org/project/drupal/issues/672606
      $filename = self::getReplacementName($file->getFileName());
      if (views_get_view_result('api_listings', 'block_items_file', $filename, $type, $file->getBranch()->id())) {
        $objects[$type] = [
          '#markup' => '<h3>' . $label . '</h3>',
        ];
        $objects[$type . '_view'] = views_embed_view('api_listings', 'block_items_file', $filename, $type, $file->getBranch()->id());
      }
    }

    // If this is a theme template or YML file, make reference link.
    $links = self::buildReferencesSection([
      'theme_invokes',
      'yml_config',
      'yml_keys',
    ], $file, $branch);

    $variables = [
      '#title' => $file->getTitle(),
    ];
    $variables['file_info'] = [
      '#theme' => 'api_file_page',
      '#object' => $file,
      '#documentation' => $documentation,
      '#objects' => $objects,
      '#code' => $code,
      '#see' => $see,
      '#deprecated' => $deprecated,
      '#related_topics' => $related_topics,
      '#defined' => $defined,
      '#call_links' => $links,
    ];

    return $variables;
  }

  /**
   * Builds and returns a references section for a documentation object.
   *
   * @param string[] $types
   *   Types of references to make (see api_find_references() for the list).
   * @param \Drupal\api\Interfaces\DocBlockInterface $object
   *   Documentation object to find references for.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to find references in.
   *
   * @return string[]
   *   Array of HTML strings for the references section. Each one is a
   *   collapsible list of a few references, with a link to the full references
   *   page. Empty sections are omitted.
   */
  public static function buildReferencesSection(array $types, DocBlockInterface $object, BranchInterface $branch) {
    /** @var \Drupal\Core\Config\ImmutableConfig $config */
    $config = \Drupal::config('api.settings');
    $output = [];
    foreach ($types as $type) {
      $count = ExtendedQueries::findReferences($object, $branch, $type, TRUE, $object->id(), 0, $object->isDrupal());
      if ($count > 0) {
        $limit = $config->get('reference_limit') ?? 5;
        $section = self::listReferences($object, $branch, $type, $limit, $object->isDrupal());
        if ($count > $limit) {
          $section .= '<p>' . self::functionReferenceLink($type, $count, $object, t('... See full list')) . '</p>';
        }
        $title = self::referenceText($type, $count, $object);
        $output[] = '<details class="api-references"><summary>' . $title . '</summary>' . $section . '</details>';
      }
    }

    return $output;
  }

  /**
   * Determines whether an object contains any documentation.
   *
   * @param array $docblock
   *   Array check.
   *
   * @return bool
   *   TRUE if the object has any of the expected properties non-empty.
   */
  public static function elementHasDocumentation(array $docblock) {
    $members = [
      'documentation',
      'parameters',
      'return_value',
      'see',
      'deprecated',
      'throws',
      'var',
    ];
    foreach ($members as $member) {
      if (!empty($docblock[$member])) {
        return TRUE;
      }
    }
    return FALSE;
  }

  /**
   * Generates a link to the file.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $file
   *   Entity to link its file.
   *
   * @return string
   *   Link markup.
   */
  public static function linkFile(DocBlockInterface $file) {
    $url = self::objectUrl($file, TRUE);
    $title = $file->getObjectName();
    $dirname = dirname($file->getFileName());
    if ($file->getObjectType() !== 'file') {
      $title = str_replace($dirname . '/', '', $file->getFileName());
    }

    $link_to_file = Link::fromTextAndUrl(
      $title,
      Url::fromUri($url)
    )->toString();

    // Prepend folder path.
    return self::addBreaks($dirname . '/') . $link_to_file;
  }

  /**
   * Adds additional contextual variables to the render array for some pages.
   *
   * This method is usually called in addition to another "prepare" or
   * "preprocess" method to add additional information.
   *
   * @param array $variables
   *   Render array of the page.
   */
  public static function prepareObjectPage(array &$variables) {
    /** @var \Drupal\api\Interfaces\DocBlockInterface $object */
    $object = $variables['object'];
    $branch = $object->getBranch();
    $extended_object = (array) ExtendedQueries::loadExtendedWithOverrides($object->id());

    $variables['defined'] = [
      '#theme' => 'api_defined',
      '#branch' => $branch,
      '#object' => $object,
    ];

    // Find the namespace.
    $namespace = $object->getNamespace();
    $variables['namespace'] = $namespace ?
      Link::fromTextAndUrl($namespace, Url::fromUri(self::namespaceUrl($branch, $namespace)))->toString() :
      '';

    self::findAlternativesSection($object, $variables);

    // See if this is an override of another method/etc.
    $variables['override'] = '';
    if (!empty($extended_object['overrides_docblock'])) {
      $overrides = (array) ExtendedQueries::loadExtendedWithOverrides(
        (int) $extended_object['overrides_docblock'],
        $branch,
        [
          'function',
          'property',
          'constant',
        ]
      );
      if (!empty($overrides)) {
        $override_object = DocBlock::load($overrides['id']);
        $overrides_link = Link::fromTextAndUrl(
          $overrides['title'],
          Url::fromUri(self::objectUrl($override_object))
        )->toString();
        $variables['override'] = '<p class="api-override">'
          . t('Overrides %link', ['%link' => $overrides_link])
          . '</p>';
      }
    }

    $variables['comments'] = $object->getComments()->view();
  }

  /**
   * Turns function, class, and other names into links in code.
   *
   * @param string $code
   *   PHP code to scan for things to make into links.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch to make the links in.
   * @param int $file_id
   *   Documentation ID of the file the code is in (for namespace information).
   *   Can omit if namespaces are not relevant.
   * @param int $class_id
   *   Documentation ID of the class the code is in (if any).
   * @param bool $is_drupal
   *   If set explicitly to FALSE, omit the Drupal-specific link steps.
   *
   * @return string
   *   Code with links.
   *
   * @see self::linkDocumentation
   */
  public static function linkCode($code, BranchInterface $branch = NULL, $file_id = NULL, $class_id = NULL, $is_drupal = TRUE) {
    if ($is_drupal) {
      $steps = [
        'code hook name',
        'code fieldhook name',
        'code entityhook name',
        'code userhook name',
        'code alter hook name',
        'code theme hook name',
        'code element name',
        'code function',
        'code function declared',
        'code member',
        'code string',
        'yaml string',
        'service',
        'yaml file',
        'yaml reference',
        'annotation string',
        'code global',
        'code class',
        'annotation class',
      ];
    }
    else {
      $steps = [
        'code function',
        'code function declared',
        'code member',
        'code string',
        'service',
        'yaml reference',
        'annotation string',
        'code global',
        'code class',
        'annotation class',
      ];
    }

    return self::makeDocumentationLinks($code, $branch, $file_id, $class_id, $steps);
  }

  /**
   * Returns the page title or link text for a references page/link.
   *
   * @param string $type
   *   The type of reference link. This is either one of the values listed in
   *   api_find_references(), or:
   *   - 'hierarchy': Class hierarchy ($count is ignored).
   *   - 'implements': Interface implements list ($count is ignored).
   * @param int $count
   *   The number of referenced items.
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   The function, class, or file object being referenced.
   *
   * @return string
   *   Text to be used as the link to the reference listing page, or the title
   *   of the page.
   */
  public static function referenceText($type, $count, DocBlockInterface $docBlock) {
    $name_to_use = Html::escape($docBlock->getTitle());
    if ($type == 'references') {
      $name_to_use = "'" . $name_to_use . "'";
    }
    elseif ($type == 'yml_config') {
      $name_to_use = (strpos($name_to_use, '.yml') !== FALSE) ?
        "'" . substr($name_to_use, 0, strlen($name_to_use) - 4) . "'" :
        "'" . $name_to_use . "'";
      $type = 'references';
    }
    elseif ($docBlock->getObjectType() == 'function') {
      $name_to_use .= '()';
    }

    $translation = \Drupal::translation();
    if ($type == 'calls') {
      return $translation->formatPlural(
        $count,
        '1 call to %name',
        '@count calls to %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'constants') {
      return $translation->formatPlural(
        $count,
        '1 use of %name',
        '@count uses of %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'implementations') {
      return $translation->formatPlural(
        $count,
        '1 function implements %name',
        '@count functions implement %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'uses') {
      return $translation->formatPlural(
        $count,
        '1 file declares its use of %name',
        '@count files declare their use of %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'overrides') {
      return $translation->formatPlural(
        $count,
        '1 method overrides %name',
        '@count methods override %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'element_invokes') {
      return $translation->formatPlural(
        $count,
        '1 #type use of %name',
        '@count #type uses of %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'theme_invokes') {
      return $translation->formatPlural(
        $count,
        '1 theme call to %name',
        '@count theme calls to %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'theme_references') {
      return $translation->formatPlural(
        $count,
        '1 string reference to the theme hook from %name',
        '@count string references to the theme hook from %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'invokes') {
      return $translation->formatPlural(
        $count,
        '1 invocation of %name',
        '@count invocations of %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'references' || $type == 'use') {
      return $translation->formatPlural(
        $count,
        '1 string reference to %name',
        '@count string references to %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'annotations') {
      return $translation->formatPlural(
        $count,
        '1 class is annotated with %name',
        '@count classes are annotated with %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'services') {
      return $translation->formatPlural(
        $count,
        '1 service uses %name',
        '@count services use %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'yml_keys') {
      return $translation->formatPlural(
        $count,
        '1 string reference to YAML keys in %name',
        '@count string references to YAML keys in %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'hierarchy') {
      return t('Expanded class hierarchy of %name',
        ['%name' => $name_to_use]);
    }
    if ($type == 'implements') {
      return t('All classes that implement %name',
        ['%name' => $name_to_use]);
    }

    return '';
  }

  /**
   * Generates a list of references as HTML.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $object
   *   Object to list references for.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch the object is in.
   * @param string $type
   *   Type of references: see api_find_references() for list.
   * @param int $limit
   *   (optional) Limit list to a few references.
   * @param bool $is_drupal
   *   (optional) If set to FALSE, and $type is a Drupal-specific type (such as
   *   theme- or hook- related), just return an empty list or zero count.
   *
   * @return string
   *   HTML for a list of references.
   */
  public static function listReferences(DocBlockInterface $object, BranchInterface $branch, $type, $limit = 0, $is_drupal = FALSE) {
    $call_functions = ExtendedQueries::findReferences($object, $branch, $type, FALSE, $object->id(), $limit, $is_drupal);

    $note = '';
    if ($type == 'implementations') {
      $note = '<p>' . t('Note: this list is generated by pattern matching, so it may include some functions that are not actually implementations of this hook.') . '</p>';
    }
    elseif ($type == 'theme_references') {
      $note = '<p>' . t('Note: this list is generated by looking for the string for this theme hook, so it may include some references that are not actually using this theme hook.') . '</p>';
    }

    $render_array = [
      '#theme' => 'api_functions',
      '#functions' => $call_functions,
    ];
    $output = \Drupal::service('renderer')->render($render_array);

    return $note . $output;
  }

  /**
   * Returns HTML for a reference link on a function or file page.
   *
   * This is used for the "N functions call function()" and "N functions
   * implement hook()" links on function pages.
   *
   * @param string $type
   *   The type of reference link. See ::findReferences() for list.
   * @param int $count
   *   The number of referenced items.
   * @param \Drupal\api\Interfaces\DocBlockInterface $docBlock
   *   The function, class, or file object being referenced.
   * @param string $override_text
   *   Text to override the link text.
   *
   * @return string
   *   Link markup.
   */
  public static function functionReferenceLink($type, $count, DocBlockInterface $docBlock, $override_text = NULL) {
    $link_title = ($override_text) ?? self::referenceText($type, $count, $docBlock);

    // Create the link path.
    $processed_file_name = self::getReplacementName($docBlock->getFileName());
    $branch = $docBlock->getBranch();

    $base_path = 'internal:/api/' . $branch->getProject()->getSlug() . '/' . $processed_file_name;
    $object_type = $docBlock->getObjectType();
    if ($object_type == 'file') {
      $link_path = $base_path . '/' . $type . '/' . $branch->getSlug();
    }
    elseif (in_array($object_type, [
      'class',
      'interface',
      'trait',
      'service',
      'constant',
    ])) {
      $link_path = $base_path . '/' . $object_type . '/' . $type . '/' . $docBlock->getObjectName() . '/' . $branch->getSlug();
    }
    else {
      $link_path = $base_path . '/function/' . $type . '/' . $docBlock->getObjectName() . '/' . $branch->getSlug();
    }

    return Link::fromTextAndUrl($link_title, Url::fromUri($link_path))->toString();
  }

  /**
   * Constructs a link to an API namespace page.
   *
   * Constructs a URL for a namespace, replacing any NAMESPACE_SEPARATOR in a
   * file path with FILEPATH_SEPARATOR_REPLACEMENT.
   *
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch object.
   * @param string $namespace
   *   Namespace to make the link for.
   *
   * @return string
   *   A URL string, or an empty string if there was a problem.
   */
  public static function namespaceUrl(BranchInterface $branch, $namespace) {
    $replaced_string = self::getReplacementName($namespace, 'namespace');
    return 'internal:/api/' . $branch->getProject()->getSlug() . '/namespace/' . $replaced_string . '/' . $branch->getSlug();
  }

  /**
   * Finds alternative versions of an object for the object page.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $object
   *   Object to find alternatives for.
   * @param array $variables
   *   Variables array, passed by reference, to put the alternatives in.
   */
  public static function findAlternativesSection(DocBlockInterface $object, array &$variables) {
    switch ($object->getObjectType()) {
      case 'file':
        $labels = [
          'within' => t('Same filename in this branch'),
          'other' => t('Same filename and directory in other branches'),
        ];
        break;

      default:
        $labels = [
          'within' => t('Same name in this branch'),
          'other' => t('Same name and namespace in other branches'),
        ];
    }

    $alternatives = [];

    $result = DocBlock::findSimilar($object);
    if ($result) {
      $result = DocBlock::loadMultiple($result);
      $within_branch = self::makeAlternativeSection($result, $object, 1);
      if ($within_branch) {
        $alternatives['same_name'] = [
          '#prefix' => '<details class="api-alternatives"><summary>' . $labels['within'] . '</summary>',
          [$within_branch],
          '#suffix' => '</details>',
        ];
      }
    }

    $result = DocBlock::findSimilar($object, FALSE);
    if ($result) {
      $result = DocBlock::loadMultiple($result);
      $other_branches = self::makeAlternativeSection($result, $object, 0, TRUE);
      if ($other_branches) {
        $alternatives['other_branches'] = [
          '#prefix' => '<details class="api-alternatives"><summary>' . $labels['other'] . '</summary>',
          [$other_branches],
          '#suffix' => '</details>',
        ];
      }
    }

    $variables['alternatives'] = $alternatives;
  }

  /**
   * Makes a set of alternative links from a query result.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface[] $result
   *   Database query result to turn into alternatives list.
   * @param \Drupal\api\Interfaces\DocBlockInterface $object
   *   Object we are making alternatives list for.
   * @param int $min_count
   *   Minimum count the alternatives list must have to consider it not empty.
   *   Typically 0 for other branches, 1 within this branch.
   * @param bool $show_number_of_comments
   *   Show number of comments besides each link or not.
   */
  public static function makeAlternativeSection(array $result, DocBlockInterface $object, $min_count = 0, $show_number_of_comments = FALSE) {
    $count = 0;
    $alternatives = [
      '#prefix' => '<ol class="api-alternatives">',
      '#suffix' => '</ol>',
    ];

    foreach ($result as $alternative) {
      /** @var \Drupal\api\Interfaces\DocBlockInterface $alternative */
      // Construct link label.
      $label = $alternative->getBranch()->getSlug() . ' ' . $alternative->getFileName();
      $suffix = ($object->getObjectType() == 'file') ? '' : ' ' . $alternative->getNamespacedName();
      if ($object->getObjectType() == 'function') {
        $suffix .= '()';
      }
      if ($show_number_of_comments && ($count = $alternative->getComments()->count())) {
        $suffix .= ' <span class="description">' . \Drupal::translation()->formatPlural($count, '1 comment', '@count comments') . '</span>';
      }

      $alternatives[$alternative->getBranch()->getProject()->getSlug()][] = [
        '#prefix' => '<li>',
        '#markup' => Link::fromTextAndUrl($label, Url::fromUri(self::objectUrl($alternative)))->toString() . $suffix,
        '#weight' => $alternative->getBranch()->getWeight(),
        '#suffix' => '</li>',
      ];
      $count++;
    }

    if ($count < $min_count) {
      return '';
    }

    return $alternatives;
  }

  /**
   * Creates a section documenting which class a member is from.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   Documentation item for the member.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch the item is in.
   *
   * @return array|false
   *   Render array for the section, or FALSE if it is not a class member.
   */
  protected static function classSection(DocBlockInterface $item, BranchInterface $branch) {
    if (!$item->getClass()) {
      return FALSE;
    }

    $class = (array) ExtendedQueries::loadExtendedWithOverrides(
      $item->getClass()->id(),
      $branch,
      ['class', 'interface', 'trait']
    );
    if (empty($class)) {
      return FALSE;
    }
    $class = DocBlock::load($class['id']);

    return [
      '#theme' => 'api_class_section',
      '#class' => $class,
      '#branch' => $branch,
    ];
  }

  /**
   * Generates a page when viewing the class or interface hierarchy.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageClassHierarchy(DocBlockInterface $item) {
    return [
      '#title' => $item->getTitle(),
      '#markup' => self::classHierarchy($item, 'full'),
    ];
  }

  /**
   * Generates a page when viewing the interface implements sections.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageInterfaceImplements(DocBlockInterface $item) {
    // Make a list of all the classes that implement the interface.
    $to_process = [$item->id() => $item];
    $processed = [];
    $found = [];
    while (count($to_process)) {
      $todo = array_shift($to_process);
      $processed[$todo->id()] = $todo;
      if ($todo->getObjectType() == 'class') {
        self::classProcessInherits($todo, $to_process, $processed, $found);
      }
      else {
        self::interfaceProcessInherits($todo, $to_process, $processed, $found);
      }
    }

    $render_array = [
      '#title' => $item->getTitle(),
    ];

    // Sort alphabetically and return it.
    if ($found) {
      ksort($found);
      $render_array['items'] = [
        '#theme' => 'item_list',
        '#items' => $found,
      ];
    }
    else {
      $render_array['#markup'] = t('No classes implement this interface');
    }

    return $render_array;
  }

  /**
   * Processes an interface for api_page_interface_implements().
   *
   * Finds all interfaces that extend it and classes that implement it,
   * directly, and adds them to the to do and found lists.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $interface
   *   Documentation object for the interface to check.
   * @param object[] $to_process
   *   List of classes and interfaces still to be processed, keyed by
   *   documentation ID.
   * @param object[] $processed
   *   List of classes and interfaces that have already been processed, keyed
   *   by documentation ID.
   * @param string[] $found
   *   Array of classes found to implement the interface. Keys are the
   *   a sort index; values are lines like in a class hierarchy.
   */
  protected static function interfaceProcessInherits(DocBlockInterface $interface, array &$to_process, array &$processed, array &$found) {
    $exclude = array_merge(
      array_keys($to_process),
      array_keys($processed)
    );

    // Find interfaces that directly extend this one.
    $children = $interface->getChildren();
    $children = (!empty($exclude) && !empty($children)) ?
      array_diff($children, $exclude) :
      $children;

    if (!empty($children)) {
      $children = DocBlock::loadMultiple($children);
      foreach ($children as $child) {
        if ($child->getObjectType() == 'interface') {
          $to_process[$child->id()] = $child;
        }
      }
    }

    // Find classes that directly implement this interface.
    $children = $interface->getChildren('interface');
    $children = (!empty($exclude) && !empty($children)) ?
      array_diff($children, $exclude) :
      $children;

    if (!empty($children)) {
      $children = DocBlock::loadMultiple($children);
      foreach ($children as $child) {
        if ($child->getObjectType() == 'class') {
          $to_process[$child->id()] = $child;
          $found[$child->getNamespacedName() . $child->id()] = [
            '#markup' => self::classHierarchyLine($child),
          ];
        }
      }
    }
  }

  /**
   * Processes a class for api_page_interface_implements().
   *
   * Finds all classes that extend it directly, and adds them to the to do and
   * found lists.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $class
   *   Documentation object for the class to check.
   * @param object[] $to_process
   *   List of classes and interfaces still to be processed, keyed by
   *   documentation ID.
   * @param object[] $processed
   *   List of classes and interfaces that have already been processed, keyed
   *   by documentation ID.
   * @param string[] $found
   *   Array of classes found to implement the interface. Keys are the
   *   a sort index; values are lines like in a class hierarchy.
   */
  protected static function classProcessInherits(DocBlockInterface $class, array &$to_process, array &$processed, array &$found) {
    $exclude = array_merge(
      array_keys($to_process),
      array_keys($processed)
    );

    // Find classes that directly extend this one.
    $children = $class->getChildren();
    $children = (!empty($exclude) && !empty($children)) ?
      array_diff($children, $exclude) :
      $children;

    if (!empty($children)) {
      $children = DocBlock::loadMultiple($children);
      foreach ($children as $child) {
        if ($child->getObjectType() == 'class') {
          $to_process[$child->id()] = $child;
          $found[$child->getNamespacedName() . $child->id()] = [
            '#markup' => self::classHierarchyLine($child),
          ];
        }
      }
    }
  }

  /**
   * Generates a page when viewing multiple sections ($subtype) or an element.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   * @param string $subtype
   *   Subtype of the element to generate output for.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageFunctionCalls(DocBlockInterface $item, $subtype) {
    $branch = $item->getBranch();

    $call_count = ExtendedQueries::findReferences($item, $branch, $subtype, TRUE, $item->id(), 0, $item->isDrupal());
    $call_title = self::referenceText($subtype, $call_count, $item);

    return [
      '#title' => $call_title,
      '#markup' => self::listReferences($item, $branch, $subtype, 0, $item->isDrupal()),
    ];
  }

  /**
   * Generates a page when viewing simple item types (constant, global...).
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   * @param string $type
   *   Type of the element to generate output for.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageSimpleItem(DocBlockInterface $item, $type) {
    $branch = $item->getBranch();

    $documentation = self::linkDocumentation($item->getDocumentation(), $branch, $item->id(), $item->getClass(), FALSE, FALSE, $item->isDrupal());
    $code = self::linkCode($item->getCode(), $branch, $item->id(), $item->getClass(), $item->isDrupal());
    $related_topics = views_embed_view('api_references', 'block_related_topics', $item->id());
    $see = self::linkDocumentation($item->getSee(), $branch, $item->id(), $item->getClass(), TRUE, TRUE, $item->isDrupal());
    $deprecated = self::linkDocumentation($item->getDeprecated(), $branch, $item->id(), $item->getClass(), TRUE, FALSE, $item->isDrupal());
    $var = '';
    if ($type == 'property') {
      $var = self::linkName($item->getVar() ?? $item->getMemberName(), $branch, '', '', $item->id(), $item->getClass(), NULL, FALSE, TRUE, NULL, NULL, 'class');
    }
    $class = self::classSection($item, $branch);
    $links = ($type == 'constant') ?
      self::buildReferencesSection(['constants'], $item, $branch) :
      [];

    $theme_hooks = [
      'constant' => 'api_constant_page',
      'global' => 'api_global_page',
      'property' => 'api_property_page',
    ];
    $output = [
      '#title' => $item->getTitle(),
      '#theme' => $theme_hooks[$type],
      '#branch' => $branch,
      '#object' => $item,
      '#documentation' => $documentation,
      '#code' => $code,
      '#related_topics' => $related_topics,
      '#see' => $see,
      '#deprecated' => $deprecated,
      '#var' => $var,
      '#class' => $class,
      '#call_links' => $links,
    ];

    return $output;
  }

  /**
   * Generates a page when viewing class-like items types.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageClass(DocBlockInterface $item) {
    $branch = $item->getBranch();

    $documentation = self::linkDocumentation($item->getDocumentation(), $branch, $item->id(), $item->id(), FALSE, FALSE, $item->isDrupal());
    $related_topics = views_embed_view('api_references', 'block_related_topics', $item->id());
    $code = self::linkCode($item->getCode(), $branch, $item->id(), $item->id(), $item->isDrupal());
    $see = self::linkDocumentation($item->getSee(), $branch, $item->id(), $item->id(), TRUE, TRUE, $item->isDrupal());
    $deprecated = self::linkDocumentation($item->getDeprecated(), $branch, $item->id(), $item->id(), TRUE, FALSE, $item->isDrupal());

    // Figure out the class hierarchy.
    $hierarchy = self::classHierarchy($item);

    // Find and render all the class members.
    $objects = [];
    // Only display if there was something there.
    if (views_get_view_result('api_members', 'block_member_list', $item->id())) {
      $objects['members'] = [
        '#markup' => '<h3>' . t('Members') . '</h3>',
      ];
      $objects['members_view'] = views_embed_view('api_members', 'block_member_list', $item->id());
    }

    $reference_types = ['uses', 'references'];
    if ($item->getObjectType() == 'class') {
      $reference_types[] = 'annotations';
      $reference_types[] = 'element_invokes';
      $reference_types[] = 'services';
    }
    elseif ($item->getObjectType() == 'interface') {
      $reference_types[] = 'implements';
      $reference_types[] = 'services';
    }

    $links = self::buildReferencesSection($reference_types, $item, $branch);

    // Put it all together.
    $output = [
      '#title' => $item->getObjectType() . ' ' . $item->getTitle(),
      '#theme' => 'api_class_page',
      '#branch' => $branch,
      '#object' => $item,
      '#documentation' => $documentation,
      '#hierarchy' => $hierarchy,
      '#objects' => $objects,
      '#code' => $code,
      '#related_topics' => $related_topics,
      '#see' => $see,
      '#deprecated' => $deprecated,
      '#call_links' => $links,
    ];

    return $output;
  }

  /**
   * Renders a class hierarchy, either full or partial.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $class
   *   Class object.
   * @param string $type
   *   One of the following strings to indicate what type of hierarchy:
   *   - 'full': Full hierarchy, showing all parents, siblings, children, etc.
   *   - 'ancestors': Only direct ancestors of this class.
   *
   * @return string
   *   HTML string containing the class hierarchy, or an empty string if the
   *   only thing to display would be the class itself.
   */
  public static function classHierarchy(DocBlockInterface $class, $type = 'ancestors') {
    // Avoid inheritance loops.
    $processed = [];
    $renderer = \Drupal::service('renderer');

    // See if this class has any children. Note that this is only in the same
    // branch.
    $child_output = '';
    if ($type == 'full') {
      $children = self::classChildren($class, $class->id());
      if (count($children)) {
        $render_array = [
          '#theme' => 'item_list',
          '#items' => $children,
        ];
        $child_output = $renderer->render($render_array);
      }
    }

    // Find the direct-line extends ancestors of this class, only in the same
    // branch.
    $parent = $class;
    $current = $class;
    while ($parent) {
      $processed[] = $parent->id();

      $results = $parent->getAncestors() ?? [];
      $count = count($results);
      if ($count < 1) {
        $parent = self::classHierarchyLine($current, $class->id());
        break;
      }
      $parent = array_pop($results);
      $parent = $parent ? DocBlock::load($parent) : $parent;
      if ($count == 1 && $type == 'full') {
        $siblings = self::classChildren($parent, $class->id());
      }
      else {
        $siblings = [
          $current->id() => [
            '#markup' => self::classHierarchyLine($current, $class->id()),
          ],
        ];
      }

      if (empty($siblings[$current->id()])) {
        $siblings[$current->id()] = ['#markup' => ''];
      }
      $siblings[$current->id()]['#markup'] .= $child_output;

      $render_array = [
        '#theme' => 'item_list',
        '#items' => $siblings,
      ];
      $child_output = $renderer->render($render_array);
      $current = $parent;

      if ($count > 1) {
        // If we found more than one result, that means that either there were
        // multiple results with the same class name, or this was an interface
        // with multiple inheritance (hopefully not both!). So, if the results
        // have the same name, add a line indicating a search. If they have
        // different names, list them all. In either case, don't attempt any
        // more lines of hierarchy.
        $same = TRUE;
        $results_objects = DocBlock::loadMultiple($results);
        foreach ($results_objects as $item) {
          if ($item->getObjectName() != $parent->getObjectName()) {
            $same = FALSE;
            break;
          }
        }

        if ($same) {
          $text = t('Multiple classes named @classname', [
            '@classname' => $parent->getObjectName(),
          ]);
          $parent = self::searchLink($parent->getObjectName(), $text, $parent->getBranch());
        }
        else {
          $this_line = [];
          $results_objects = DocBlock::loadMultiple($results);
          $results_objects[] = $parent;
          foreach ($results_objects as $item) {
            $this_line[] = self::classHierarchyLine($item, $class->id());
          }
          $parent = implode('; ', $this_line);
        }
        break;
      }

      // If we get here, make sure we're not in an infinite loop.
      if (in_array($parent->id(), $processed)) {
        $parent = self::classHierarchyLine($current, $class->id());
        break;
      }
    }

    $render_array = [
      '#theme' => 'item_list',
      '#items' => [
        ['#markup' => $parent . $child_output],
      ],
    ];
    $output = $renderer->render($render_array);

    if ($type != 'full' && $class->getObjectType() != 'trait') {
      $output .= '<p>' . self::functionReferenceLink('hierarchy', 0, $class) . '</p>';
    }

    if ($type != 'full' && $class->getObjectType() == 'interface') {
      $output .= '<p>' . self::functionReferenceLink('implements', 0, $class) . '</p>';
    }

    return $output;
  }

  /**
   * Finds and renders the children of a class within the same branch.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $class
   *   Documentation object for the class.
   * @param int $current_id
   *   Documentation ID of the class on the current page.
   *
   * @return array
   *   Render array of children. Keys are class names, and values are output
   *   of self::classHierarchyLine().
   */
  public static function classChildren(DocBlockInterface $class, $current_id = 0) {
    $children = [];

    $children_ids = $class->getChildren();
    if ($children_ids) {
      $children_docBlock = DocBlock::loadMultiple($children_ids);
      foreach ($children_docBlock as $docBlock) {
        $children[$docBlock->id()] = [
          '#markup' => self::classHierarchyLine($docBlock, $current_id),
        ];
      }
    }

    return $children;
  }

  /**
   * Renders the class hierarchy line for a single class.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $class
   *   Documentation object for the class to render.
   * @param int $current_id
   *   Documentation ID of the class that the current page is showing. If this
   *   class is being shown, it will have an 'active' class on the link.
   *
   * @return string
   *   HTML for this class in the class hierarchy.
   */
  public static function classHierarchyLine(DocBlockInterface $class, $current_id = 0) {
    $classes = [];
    if ($class->id() == $current_id) {
      $classes[] = 'active';
    }

    // See if this class implements any interfaces or uses traits.
    $interfaces = [];
    $traits = [];

    // Get any references coming from this class.
    $references = $class->getDocReferences(['interface', 'trait'], FALSE);
    if ($references) {
      foreach ($references as $reference) {
        if ($reference->getExtendsDocBlock()) {
          // Interface/trait exists as an object in the database, make a link.
          if ($reference->getObjectType() == 'interface') {
            if (!isset($interfaces[$reference->getObjectName()])) {
              $interfaces[$reference->getObjectName()] = Link::fromTextAndUrl(
                $reference->getObjectName(),
                Url::fromUri(self::objectUrl($reference->getExtendsDocBlock()))
              )->toString();
            }
          }
          else {
            if (!isset($traits[$reference->getObjectName()])) {
              $traits[$reference->getObjectName()] = Link::fromTextAndUrl(
                $reference->getObjectName(),
                Url::fromUri(self::objectUrl($reference->getExtendsDocBlock()))
              )->toString();
            }
          }
        }
        else {
          // This class was declared to implement this interface, or use this
          // trait, but it didn't get defined (probably a built-in PHP object
          // or from another project). So just display the name.
          if ($reference->getObjectType() == 'interface') {
            $interfaces[] = $reference->getObjectName();
          }
          else {
            $traits[] = $reference->getObjectName();
          }
        }
      }
    }

    // See if this class extends another class that isn't defined in our DB.
    // For interfaces, this could be multiple interfaces.
    $extends = '';
    $references = $class->getDocReferences('class', FALSE);
    if ($references && count($references) > 0) {
      $extends = ' extends';
      foreach ($references as $reference) {
        $extends .= ' ' . $reference->getObjectName();
      }
    }

    $namespace = substr($class->getNamespacedName(), 0, -strlen($class->getObjectName()));
    $output = $class->getObjectType()
      . ' ' . $namespace .
      Link::fromTextAndUrl(
        $class->getObjectName(),
        Url::fromUri(
          self::objectUrl($class),
          ['attributes' => ['class' => $classes]]
        )
      )->toString()
      . $extends;

    if (count($interfaces) > 0) {
      $output .= ' implements ' . implode(', ', $interfaces);
    }
    if (count($traits) > 0) {
      $output .= ' uses ' . implode(', ', $traits);
    }

    return $output;
  }

  /**
   * Generates a page when viewing function-like items types.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageFunction(DocBlockInterface $item) {
    $branch = $item->getBranch();
    $function = $item->getDocFunction();

    // Build the page sections.
    $class_id = $item->getClass() ? $item->getClass()->id() : '';
    $documentation = self::linkDocumentation($item->getDocumentation(), $branch, $item->id(), $class_id, FALSE, FALSE, $item->isDrupal());
    $parameters = ($function) ? self::linkDocumentation($function->getParameters(), $branch, $item->id(), $class_id, TRUE, FALSE, $item->isDrupal()) : '';
    $return = ($function) ? self::linkDocumentation($function->getReturnValue(), $branch, $item->id(), $class_id, TRUE, FALSE, $item->isDrupal()) : '';
    $see = self::linkDocumentation($item->getSee(), $branch, $item->id(), $class_id, TRUE, TRUE, $item->isDrupal());
    $deprecated = self::linkDocumentation($item->getDeprecated(), $branch, $item->id(), $class_id, TRUE, FALSE, $item->isDrupal());
    $throws = self::linkDocumentation($item->getThrows(), $branch, $item->id(), $class_id, TRUE, FALSE, $item->isDrupal());
    $code = self::linkCode($item->getCode(), $branch, $item->id(), $class_id, $item->isDrupal());
    $related_topics = views_embed_view('api_references', 'block_related_topics', $item->id());
    $class = self::classSection($item, $branch);

    // Build reference links.
    $links = self::buildReferencesSection([
      'calls',
      'references',
      'implementations',
      'invokes',
      'theme_invokes',
      'overrides',
    ], $item, $branch);

    // Put it all together and theme the output.
    $output = [
      '#title' => $item->getObjectType() . ' ' . $item->getTitle(),
      '#theme' => 'api_function_page',
      '#branch' => $branch,
      '#object' => $item,
      '#documentation' => $documentation,
      '#parameters' => $parameters,
      '#return' => $return,
      '#related_topics' => $related_topics,
      '#call_links' => $links,
      '#code' => $code,
      '#see' => $see,
      '#deprecated' => $deprecated,
      '#throws' => $throws,
      '#class' => $class,
    ];

    return $output;
  }

  /**
   * Generates a page when viewing service-like items types.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageService(DocBlockInterface $item) {
    $branch = $item->getBranch();

    // The class name (if there is one), or some other information is stored
    // in the documentation field.
    $class = self::linkDocumentation($item->getDocumentation(), $branch, $item->id(), 0, TRUE, FALSE, $item->isDrupal());
    $code = self::linkCode($item->getCode(), $branch, $item->id(), $item->id(), $item->isDrupal());

    // Make a list of the service tags. These are in the api_references table.
    $service_tags = DocReference::getServiceTags([$item->id()]);
    if ($service_tags) {
      $items = [];
      $service_tags = DocReference::loadMultiple($service_tags);
      foreach ($service_tags as $service_tag) {
        $items[] = $service_tag->getObjectName();
      }
      $tags = [
        '#theme' => 'item_list',
        '#items' => $items,
      ];
    }
    else {
      $tags = FALSE;
    }

    $links = self::buildReferencesSection(['use'], $item, $branch);

    // Put it all together.
    $output = [
      '#title' => $item->getTitle(),
      '#theme' => 'api_service_page',
      '#branch' => $branch,
      '#object' => $item,
      '#class' => $class,
      '#code' => $code,
      '#tags' => $tags,
      '#call_links' => $links,
    ];

    return $output;
  }

  /**
   * Generates a page when viewing group-like items types.
   *
   * @param \Drupal\api\Interfaces\DocBlockInterface $item
   *   DocBlock element to generate the output from.
   *
   * @return array
   *   Render array for page.
   */
  public static function pageGroup(DocBlockInterface $item) {
    $branch = $item->getBranch();

    $documentation = self::linkDocumentation($item->getDocumentation(), $branch, $item->id(), NULL, FALSE, FALSE, $item->isDrupal());
    $see = self::linkDocumentation($item->getSee(), $branch, $item->id(), NULL, TRUE, TRUE, $item->isDrupal());
    $related_topics = views_embed_view('api_references', 'block_related_topics', $item->id());

    // Find and render all items in this group.
    $types = [
      'function' => t('Functions'),
      'constant' => t('Constants'),
      'global' => t('Globals'),
      'class' => t('Classes'),
      'interface' => t('Interfaces'),
      'trait' => t('Traits'),
      'file' => t('Files'),
      'group' => t('Sub-Topics'),
    ];

    $objects = [];
    foreach ($types as $type => $label) {
      // Render the view of this type of object in this group.
      $view_result = views_get_view_result(
        'api_references',
        'block_items_in_group',
        $branch->id(),
        $item->getObjectName(),
        $type
      );
      if ($view_result) {
        $objects[$type] = [
          '#markup' => '<h3>' . $label . '</h3>',
        ];
        $objects[$type . '_view'] = views_embed_view(
          'api_references',
          'block_items_in_group',
          $branch->id(),
          $item->getObjectName(),
          $type
        );
      }
    }

    $output = [
      '#title' => $item->getTitle(),
      '#theme' => 'api_group_page',
      '#branch' => $branch,
      '#object' => $item,
      '#documentation' => $documentation,
      '#objects' => $objects,
      '#see' => $see,
      '#related_topics' => $related_topics,
    ];

    return $output;
  }

  /**
   * Generates a HTML link to the search page for the given term.
   *
   * @param string $search_term
   *   Term to search.
   * @param string $text
   *   Text for the link.
   * @param \Drupal\api\Interfaces\BranchInterface $branch
   *   Branch where to check.
   *
   * @return string
   *   HTML link to the search page for the given term.
   */
  public static function searchLink($search_term, $text, BranchInterface $branch) {
    return Link::fromTextAndUrl(
      $text,
      Url::fromUri('internal:/api/' . $branch->getProject()->getSlug() . '/' . $branch->getSlug() . '/search/' . Html::escape($search_term))
    )->toString();
  }

  /**
   * Creates a block of search links to other branches within the same project.
   *
   * @param string $term
   *   Term to search.
   * @param \Drupal\api\Interfaces\BranchInterface $search_branch
   *   Branch where the search is originated.
   *
   * @return array
   *   Render array with links.
   */
  public static function searchLinks($term, BranchInterface $search_branch) {
    $term = Xss::filter($term);
    $project = $search_branch->getProject();
    $branches = $project->getBranches(TRUE);

    $links = [
      '#prefix' => '<ol class="api-alternatives">',
      '#suffix' => '</ol>',
    ];

    foreach ($branches as $branch) {
      if ($branch->id() != $search_branch->id()) {
        $text = t('Search @branch_name for %search_text', [
          '@branch_name' => $branch->getTitle(),
          '%search_text' => $term,
        ]);
        $search_link = self::searchLink($term, $text, $branch);
        $links[$project->getSlug()][] = [
          '#prefix' => '<li>',
          '#markup' => $search_link,
          '#weight' => $branch->getWeight(),
          '#suffix' => '</li>',
        ];
      }
    }

    $other_projects_link = self::otherProjectsLink(FALSE);
    if (strlen($other_projects_link)) {
      $links[$project->getSlug()][] = [
        '#prefix' => '<li>',
        '#markup' => $other_projects_link,
        '#weight' => PHP_INT_MAX,
        '#suffix' => '</li>',
      ];
    }

    return $links;
  }

}
