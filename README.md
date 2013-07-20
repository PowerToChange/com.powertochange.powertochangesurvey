## Power to Change Custom Survey Extension

1. Event-driven survey hooks
2. Custom API to extract survey data (primarily for use by the Connect app)

## Installing in staging or production environment

First, you need to prepare your server for the installation of the com.powertochange.powertochangesurvey extension

1. Download and install the following Drupal modules, in order:
  * [Libraries](https://drupal.org/project/libraries/)
  * [Webform](https://drupal.org/project/webform/)
  * [Webform CiviCRM](https://drupal.org/project/webform_civicrm/).

1. Download [jQuery TokenInput](http://loopj.com/jquery-tokeninput/), and unzip the contents in the Drupal Libraries module directory (most likely, *sites/all/libraries/tokeninput/*)

1. Follow any [additional configuration steps](https://drupal.org/node/1615380) (since the time of writing)

1. Apply the following patches:
  * CiviCRM Twilio extension [CRM-12399](http://issues.civicrm.org/jira/browse/CRM-12399)
  * CiviCRM Core [CRM-13089](http://issues.civicrm.org/jira/browse/CRM-13089)
  * CiviCRM Campaign [CRM-13090](http://issues.civicrm.org/jira/browse/CRM-13090)

1. Install the org.civicrm.sms.twilio Twilio extension

1. Add the Twilio SMS provider (Administer - System Settings - SMS Providers):
  * Title: Your choosing (title will be specified in the extension's configuration file)
  * Username: Account SID
  * Password: Auth token
  * API parameters: From=+PHONE_NUMBER

1. Enable the CiviCampaign component (Administer - System Settings - Enable CiviCRM Components)

Finally, you can install and configure the [com.powertochange.powertochangesurvey extension](https://github.com/mmikitka/com.powertochange.powertochangesurvey).

1. Download the latest stable build from the [GitHub repository](https://github.com/mmikitka/com.powertochange.powertochangesurvey/blob/master/build/com.powertochange.powertochangesurvey.zip)

1. Copy the Zip file to the CiviCRM extensions directory on the server

1. Create a local copy of the powertochangesurvey.settings.php.default configuration file

        $ cd CIVICRM_EXTENSIONS_DIR/com.powertochange.powertochangesurvey/conf
        $ cp powertochangesurvey.settings.php.default powertochangesurvey.settings.php

1. Modify your local version of powertochangesurvey.settings.php to reflect your CiviCRM system. At the very least, you should modify the fields marked with the value *CHANGE* in the default configuration file.

1. Install the com.powertochange.powertochangesurvey extension via the CiviCRM GUI

1. Upon successful installation, the following entities should be available in CiviCRM:
  * Message Templates:
      * MyCravings - Email
      * MyCravings - SMS
  * OptionGroups:
      * MyCravings - Magazine (Values: magazine-no)
      * MyCravings - Journey (Values: journey-nothing)
      * MyCravings - Gauge (Values: gauge values with prefix defined in the configuration file)
      * MyCravings - Processing state (Values: internal processing states for debugging purposes)
  * CustomGroups:
      * MyCravings - Common (Fields: Magazine, Journey, Gauge, Processing state)

## Creating custom forms

Follow these steps to create a custom Webform.

1. If needed, create new custom fields. You can re-use existing fields.

1. Create a Campaign (Campaigns - New Campaign)
  * Campaign type: Constituent Engagement
  * Campaign status: In progress

1. Create a Petition (Campaigns - New Petition). This step is required to facilitate the integration with Webform CiviCRM which currently does not support the creation of Petition/Survey entities from within the Webform GUI. A long-term goal is to remove this step.
  * Campaign: Name of the associated campaign
  * Contact profile: Select anything. Webform CiviCRM does not rely on Profiles, so you can select any value (note: you must select a profile to complete the Petition creation process)
  * Activity profile: Leave blank

1. Switch to the Drupal GUI

1. Create a new Webform (Content - Add content - Webform)
  * Title: Name of the Petition/Survey (By default, this title will be the CiviCRM Activity subject)

1. After clicking save, click the *Form settings* link within the *Webform* tab, and modify the settings. You should at least consider the following:
  * Confirmation message
  * Submission limits
  * Submission access rights

1. Click the "CiviCRM" tab, and check the *Enable CiviCRM Processing* checkbox to enable the CiviCRM entities to the form

1. Add the CiviCRM entities. Click *Save settings* to save your changes.
  * Contact 1:
      * Type: Individual (Student)
      * Existing contact: Uncheck if you the form will create new users
  * Contact 2:
      * Type: Organization (School)
      * Existing contact: Enable
      * Organization name: Enable
      * Enable relationship fields: Enable (School-Student relationship)
  * Activity:
      * Type: Petition signature
      * Default activity subject: The subject value associated with this Activity instance
      * Activity participants: Select the *target contacts* associated with the activity
      * Campaign: Name of the campaign
      * Survey/Petition: Name of the petition
      * MyCravings - Common: Magazine, Journey and Gauge fields are necessary to calculate the follow-up priority. Do not include *internal* fields.
  * Additional options:
      * Confirm subscriptions: Enable
      * Create fieldsets: You may wish to disable this to accommodate faster data entry

1. Go to the *Webform* tab and click *Form components*. Re-order the form fields.

1. Click the *View* tab to access the submission form.

## Installing in development environment

1. Clone the repository

        $ git clone https://github.com/PowerToChange/com.powertochange.powertochangesurvey.git
        $ cd com.powertochange.powertochangesurvey

1. Configure your local environment. CIVICRM_SETTINGS_DIR is the directory that contains the civicrm.settings.php file (e.g., /var/www/drupal/sites/default/)

        $ cd vendor/civix
        $ php ../composer/composer install
        $ vendor/civix/civix config:set civicrm_api3_conf_path CIVICRM_SETTINGS_DIR
        $ vendor/civix/civix civicrm:ping

1. [Install the extension](http://wiki.civicrm.org/confluence/display/CRMDOC43/Extensions)

## Generating an extension Zip file for deployment

The excluded files and directories are listed in build/zip_exclude.conf

        $ cd com.powertochange.powertochangesurvey
        $ vendor/civix/civix build:zip

The generated Zip file is located in the build directory.

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
