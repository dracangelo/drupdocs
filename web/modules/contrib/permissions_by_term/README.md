CONTENTS OF THIS FILE
---------------------

 * Introduction
 * Requirements
 * Recommended Modules
 * Installation
 * Configuration
 * Testing
 * Supporting Organization
 * Maintainer


INTRODUCTION
------------

The Permissions by Term module extends Drupal by functionality for restricting
access to single nodes via taxonomy terms. Taxonomy term permissions can be
coupled to specific user accounts and/or user roles. Taxonomy terms are part of
the Drupal core functionality.

Since Permissions by Term is using Node Access Records, every other core system
will be restricted:

 * search
 * menus
 * views
 * nodes

 * For a full description of the module visit:
   https://www.drupal.org/project/permissions_by_term
   or
   https://www.drupal.org/docs/8/modules/permissions-by-term

 * To submit bug reports and feature suggestions, or to track changes visit:
   https://www.drupal.org/project/issues/permissions_by_term


REQUIREMENTS
------------

This module requires no modules outside of Drupal core.


RECOMMENDED MODULES
-------------------

Module ships with Permissions by Entity module that extends the functionality of
Permissions By Term to be able to limit the selection of specific taxonomy terms
by users or roles for an entity.

* Webform Permissions Term -
  https://www.drupal.org/project/webform_permissions_by_term


INSTALLATION
------------

 * Install the Permissions by Term module as you would normally install a
   contributed Drupal module. Visit https://www.drupal.org/node/1897420 for
   further information.


CONFIGURATION
-------------

    1. Navigate to Administration > Extend and enable the module.
    2. Navigate to Administration > Structure > Taxonomy to create or edit a
       taxonomy term and add permissions to it. You can edit permissions in the
       "Permissions" labeled form field set.
    3. Enter in allowed users with a comma separated list of user names will be
       able to access content, related to this taxonomy term.
    4. Select the user roles who will be able to access content, related to the
       taxonomy term. Save.


TESTING
-------

Behat testing:

* composer.json config - Make sure that the dependencies for Behat testing are
  installed. Check your drupal's `composer.json` file for the following
  contents:
```
   "require-dev": {
       "behat/behat": "^3.13",
       "behat/mink": "^1.11",
       "behat/mink-browserkit-driver": "^2.2",
       "drupal/drupal-extension": "^5.0",
       "wikimedia/composer-merge-plugin": "^2.1.0"
   },

  ...

   "merge-plugin": {
       "include": [
           "web/core/composer.json",
           "web/modules/contrib/permissions_by_term/composer.json"
       ],
       "recurse": false,
       "replace": false,
       "merge-extra": false
   },

  ...
   "autoload-dev": {
       "psr-4": {
           "Drupal\\Tests\\permissions_by_term\\Behat\\": "web/modules/contrib/permissions_by_term/tests/src/Behat/"
       }
   }
   ```

 * allow composer-merge-plugin:

  ```
    composer config --no-plugins allow-plugins.wikimedia/composer-merge-plugin true
  ```

 * behat.yml file: Use the file at `tests/src/behat.yml.dist` as a
   template for your needs. Copy and name it to `behat.yml` and change it's
   paths according to your needs.

 * using Ddev, it's required to add .ddev/docker-compose.selenium-chrome.yaml and run `ddev restart`.
   Remember to update `base_url` and `wd_host` according to your environment setup.

  ```
version: '3.6'
services:
  selenium-chrome:
    image: selenium/standalone-chrome:88.0
    container_name: ddev-${DDEV_SITENAME}-chrome
    volumes:
      - /dev/shm:/dev/shm
    ports:
      - "4444:4444"
    external_links:
      - ddev-router:${DDEV_HOSTNAME}
  ```

  * run Behat tests

  ```
  bin/behat --config=behat.yml --colors --stop-on-failure ./web/modules/contrib/permissions_by_term/tests/src/Behat/Features
  ```

SUPPORTING ORGANIZATION:
------------------------

 * publicplan GmbH - https://www.drupal.org/publicplan-gmbh

MAINTAINER:
-----------

 * Peter Majmesku - https://www.drupal.org/u/peter-majmesku
