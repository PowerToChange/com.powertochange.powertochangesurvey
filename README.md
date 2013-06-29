## Power to Change Custom Survey Extension

1. Event-driven survey hooks
2. Custom API to extract survey data (primarily for use by the Connect app)

## Installing in staging or production environment

## Installing in development environment

1. Clone the repository
BRE
        $ git clone https://github.com/PowerToChange/com.powertochange.powertochangesurvey.git
        $ cd com.powertochange.powertochangesurvey

2. Configure your local environment. CIVICRM_SETTINGS_DIR is the directory that contains the civicrm.settings.php file (e.g., /var/www/drupal/sites/default/)
  
        $ vendor/civix/civix config:set civicrm_api3_conf_path CIVICRM_SETTINGS_DIR
        $ vendor/civix/civix civicrm:ping

3. [Install the extension](http://wiki.civicrm.org/confluence/display/CRMDOC43/Extensions)

## Unit testing

### Configuration

[PHPUnit](https://github.com/sebastianbergmann/phpunit/) is used to manage CiviCRM unit tests. To configure your development environment for unit testing, see [Setting up your personal testing sandbox](http://wiki.civicrm.org/confluence/display/CRM/Setting+up+your+personal+testing+sandbox+HOWTO).

Here is short summary of the steps to get your environment up and running. If you encounter problems, consult the CiviCRM wiki article mentioned above.

1. Create the database
        $ mysqladmin -u root -p create civicrm_tests_dev
        $ mysql -u root -p mysql
        $ CREATE USER 'civitestadmin'@'localhost' IDENTIFIED BY 'ENTER PASSWORD HERE'
        $ GRANT ALL ON civicrm_tests_dev.* TO 'civitestadmin'@'localhost' IDENTIFIED BY 'civitestadmin';
        $ UPDATE user SET Super_priv='Y' WHERE User='civitestadmin';
        $ FLUSH PRIVILEGES;

2. Load the schema
        $ cd sites/all/modules/civicrm
        $ mysql -u civitestadmin -p civicrm_tests_dev < sql/civicrm.mysql
        $ mysql -u civitestadmin -p civicrm_tests_dev < sql/civicrm_generated.mysql

3. Configure civix
        $ vendor/civix/civix civicrm:ping
        $ vendor/civix/civix config:get
        $ vendor/civix/civix config:set civicrm_api3_conf_path /your/path/to/sites/default

### Running tests

Run all the tests in the CustomSurveyFields API test suite:

        $ cd com.powertochange.powertochangesurvey/tests/phpunit
        $ vendor/civix/civix CRM_Powertochangesurvey_CustomSurveyFieldsTest

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
