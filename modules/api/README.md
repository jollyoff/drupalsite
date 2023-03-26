CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Installation
 * Configuration
 * Maintainers
 * Information for contributors


INTRODUCTION
------------

This is an implementation of a subset of the Doxygen documentation generator
specification, tuned to produce output that best benefits the Drupal code base.
It is designed to assume the code it documents follows Drupal coding
conventions, and supports Doxygen constructs as documented on
https://drupal.org/node/1354.

In addition to standard Doxygen syntax requirements, the following restrictions
are made on the code format. These are all Drupal coding conventions (see
https://drupal.org/node/1354 for more details and suggestions).

1. All documentation blocks must use the syntax:
    ```
    /**
     * Documentation here.
     */
    ```
    The leading spaces are required.

2. When documenting a function, constant, class, etc., the documentation block
   must immediately precede the item it documents, with no intervening blank
   lines.

3. There may be no intervening spaces between a function name and the left
   parenthesis that follows it.

Besides the Doxygen features that are supported, this module also provides the
following features:

1. Functions may be in multiple groups (Doxygen ignores all but the first
   group). This allows, for example, theme_menu_tree() to be marked as both
   "themeable" and part of the "menu system".

2. Function calls to PHP library functions are linked to the PHP manual.

3. Function calls have tooltips briefly describing the called function.

4. Documentation pages have non-volatile, predictable URLs, so links to
   individual functions will not be invalidated when the number of functions in
   a document changes.

For a full description of the module visit:
   https://www.drupal.org/project/api

To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/api


REQUIREMENTS
------------

This module requires the following modules.

* `views`
* `block`
* `comment`
* `options`
* `pathauto`


INSTALLATION
------------

Install the API module as you would normally install a contributed
Drupal module. Visit https://www.drupal.org/node/1897420 for further
information.

See https://drupal.org/node/1516558 for further information on how to
install and set up this module.


CONFIGURATION
-------------

1. Navigate to `Administration > Extend` and enable the module.
2. Navigate to `Administration > Configuration > Development > API`
and follow the on-screen instructions to add projects and branches.
3. If your server has `git` configured, you can use the quick wizard
to speed up the set-up process.


SEARCH INTEGRATION
------------------

If you enable any of the included search submodules, you can perform full-text
searches on API documentation, just like your regular site content.

The modules are:
* API Search - Core Integration
* API Search - Search API Database Defaults
* API Search - Search API Solr Defaults

You only need to enable one of them, depending on the type of search server of
your choice (core, database or solr). By enabling the module you will get the
search server, indexes and views pre-configured.

Search Core integration does not require indexing, but Search API (both DB and
Solr) do. Once done, you can go to the `search/api` endpoint and search
content on the site.

Search API (both DB and Solr) also provide facet blocks to include in the
sidebar, if desired, for faceted search.

Finally, the Solr defaults integration might not work out of the box as it uses
default values for your solr server, port, core name, etc. You will need to
edit these to ensure that the solr server is reachable. Once you do that you
can index the content and everything should work as expected. The default
values used are:
```
scheme: http
host: localhost
port: 8983
path: /
core: drupal
```



MAINTAINERS
-----------

* Fran Garcia (fjgarlin) - https://www.drupal.org/u/fjgarlin
* Neil Drumm (drumm) - https://www.drupal.org/u/drumm



INFORMATION FOR CONTRIBUTORS
----------------------------

Here is a somewhat conversational overview of the architecture of the API module
itself (how it actually works), for people interested in contributing to the
development of the API module (revised from an IRC chat log with a potential
contributor):

During cron runs, the module parses the code and comments in PHP files (and some
other files), and saves information in the database. Then when someone visits
`api.drupal.org` or another site using the API module, they get a parsed view of
the API documentation. In PHP code, any comment that starts with `/**` rather
than just `//` or `/*` is parsed by the API module, and this turns into the
documentation pages on the API site.

For instance, take a look at this code from Drupal Core:
  https://git.drupalcode.org/project/drupal/-/blob/8.9.x/core/modules/node/node.module#L221

And here is what this node_title_list() function looks like on api.drupal.org:
  https://api.drupal.org/api/drupal/core!modules!node!node.module/function/node_title_list/8.9.x

So the `@param` documentation in the Drupal Core code comment is shown in the
`Parameters` section on the `api.drupal.org` page, and so on.

Also the module parses the code itself. For one thing, you can see in the `Code`
section on that page that it has turned a bunch of stuff into links. For
instance `\Drupal` is a link that takes you to that class, and its method turns
into a link too. There are also reverse links made from parsing the code: there
is a section that says `2 calls to node_title_list()` on that page that shows
you other functions that call this one. That comes from parsing the code.

Another detail, there are concepts of `project` and `branch` in the API module.
Drupal Core is a project, and you could also define a project for Webform, etc.
Within each one, you might have a `2.x` branch or a `2.1.x` or whatever. You can
have more than one project defined on an API module site, and multiple branches
within each project. For instance, go look at `https://api.drupal.org`. It has
separate sections for Drupal `7.x` and `6.x` and `8.x`. Those are the branches.
You do not want to mix them together.

So, back to the cron runs and parsing... The API module uses the Drupal queue
system. The code can "add" jobs to the queue, and then the Drupal queue system
will periodically (during cron or independently if you tell it to via Drush) use
"worker" functions to process the queue jobs.

The API module defines 2 queues in `src/Plugin/QueueWorker` folder, and
those files also tells what the "worker" functions are that process them.

The queue/cron architecture:
- `cron`: See if any branches need updating, and if that's the case, start
   parsing them. This is function `api_cron()` in `api.module`.
- The `Parser` will read information from the files and add items to the queue
  for processing at a later stage.
- When the parse queue runs, it takes the docblock information generated in the
  parsing stage and will generate a myriad of custom entities containing the
  information. Note that for each file parsed, many documentation entities can
  be created in the database.

Queues will need to be set up separate from cron to be able to configure them
independently. So you will need to set up:
- `drush core:cron`
- `drush queue:run api_parse_queue`
- `drush queue:run api_delete_related`

The deletion of entities is also queued up to avoid time-outs. When deleting a
project, all its branches and docblock-related entities will be queued for
deletion on cron or via a queue worker.

Aside from the parsing, there is another side to the code, which generates the
actual pages on the site from the stuff that the parse functionality saves in
the database. If you have done some Drupal development, then you can probably
figure out how the pages are generated by looking at `api.routing.yml` and
following the code trail to seei what the page generating functions are, theme
templates, etc.

The API module also has a comprehensive set of automated tests. This helps us
be sure that when we make changes to the module, we don't break anything.
