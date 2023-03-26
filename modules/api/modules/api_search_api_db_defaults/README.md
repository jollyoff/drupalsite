# API Database Search-API Defaults

This module is an adapted copy of `search_api_db_defaults` (submodule from
`search_api_db`) and behaves in exactly the same way.

This module provides a default setup for the Search API, for searching DocBlocks
content through a view, using a database server for indexing.

By installing this module on your site, the required configuration will be set
up on the site. Other than that, this module has no functionality. You can
(and should, for performance reasons) uninstall it again immediately after
installing, to just get the search set up.

Due to Drupal's configuration model, subsequent updates to the configuration
deployed with this module won't be applied to existing configuration.

The search view will be set up at this path: `/search/api`

You can view (and customize) the installed search configuration under these
paths:

- Server: `/admin/config/search/search-api/server/default_server`
- Index: `/admin/config/search/search-api/index/default_api_index`
- View: `/admin/structure/views/view/search_api` (if the [Views UI] module is
installed)

[Views UI]: https://www.drupal.org/docs/8/core/modules/views-ui
