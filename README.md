## Power to Change Custom Survey Extension

1. Event-driven survey hooks
2. Custom API to extract survey data (primarily for use by the Connect app)

## Installing in CiviCRM

## Installing in development environment

1. Clone the repository

        $ git clone https://github.com/PowerToChange/com.powertochange.powertochangesurvey.git
        $ cd com.powertochange.powertochangesurvey

2. Configure your local environment. CIVICRM_SETTINGS_DIR is the directory that contains the civicrm.settings.php file (e.g., /var/www/drupal/sites/default/)
  
        $ vendor/civix/civix config:set civicrm_api3_conf_path CIVICRM_SETTINGS_DIR
        $ vendor/civix/civix civicrm:ping

3. [Install the extension](http://wiki.civicrm.org/confluence/display/CRMDOC43/Extensions)

## Generating application code

See [CiviCRM - Create a module extension](http://wiki.civicrm.org/confluence/display/CRMDOC43/Create+a+Module+Extension).

## Civix - The CiviCRM application framework

This extension was built with the [civix application framework](https://github.com/totten/civix/). To upgrade civix, composer, or any related packages, follow these steps.

### Upgrading composer

For more information, see [Composer](http://getcomposer.org/)

        $ cd vendor/composer
        $ curl -s http://getcomposer.org/installer | php

### Upgrading civix

For more information, see [https://github.com/totten/civix/](https://github.com/totten/civix/).

1. Download the desired version of civix from [Github] (https://github.com/totten/civix/).
2. Unzip the contents of the Zip file

        $ cd vendor
        $ unzip civix-master.zip
        $ mv civix-master civix
        
3. Upgrade civix via composer

        $ cd civix
        $ ../composer/composer install
