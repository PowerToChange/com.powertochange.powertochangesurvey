## Table of Contents
  * [Introduction](#introduction-to-the-custom-survey-extension)
  * [Installation](#installation-procedure)
  * [Customizing](#customizing-the-extension)
  * [Creating surveys](#creating-custom-forms)
  * [Reporting](#reporting)
  * [Testing](#testing)
  * [Design](#design)
  * [Developing](#developing)

## Introduction to the Custom Survey Extension

At a high level, the *Custom survey extension* provides the following functionality:

1. Event-driven survey hooks
2. Custom API to extract survey data (primarily for use by the Connect app)

## Installation procedure

### Install and configure third party modules

First, you need to prepare your server for the installation of the com.powertochange.powertochangesurvey extension

1. Download and install the following Drupal modules, in order:
  * [Libraries](https://drupal.org/project/libraries/)
  * [Webform](https://drupal.org/project/webform/)
  * [Webform CiviCRM](https://drupal.org/project/webform_civicrm/).

1. Download [jQuery TokenInput](http://loopj.com/jquery-tokeninput/), and unzip the contents in the Drupal Libraries module directory (most likely, *sites/all/libraries/tokeninput/*)

1. Follow any [additional configuration steps](https://drupal.org/node/1615380) (since the time of writing)

1. Install the org.civicrm.sms.twilio Twilio extension

1. Apply the following patches:
  * CiviCRM Twilio extension [CRM-12399](http://issues.civicrm.org/jira/browse/CRM-12399)
  * CiviCRM Core [CRM-13089](http://issues.civicrm.org/jira/browse/CRM-13089)
  * CiviCRM Core [CRM-13223](http://issues.civicrm.org/jira/browse/CRM-13223)
  * CiviCRM Core [CRM-13259](http://issues.civicrm.org/jira/browse/CRM-13259)
  * CiviCRM Campaign [CRM-13090](http://issues.civicrm.org/jira/browse/CRM-13090)
  * Webform CiviCRM [2070529](http://drupal.org/node/2070529)

1. Add the Twilio SMS provider (Administer - System Settings - SMS Providers):
  * Title: Your choosing (title will be specified in the extension's configuration file)
  * Username: Account SID
  * Password: Auth token
  * API parameters: From=+PHONE_NUMBER

1. Enable the CiviCampaign component (Administer - System Settings - Enable CiviCRM Components)

### Install and configure the powertochangesurvey extension

Finally, you can install and configure the [com.powertochange.powertochangesurvey extension](https://github.com/mmikitka/com.powertochange.powertochangesurvey).

1. Download the latest stable build from the [GitHub repository](https://github.com/mmikitka/com.powertochange.powertochangesurvey/blob/master/build/com.powertochange.powertochangesurvey.zip)

1. Copy the Zip file to the CiviCRM extensions directory on the server

1. Create a local copy of the [powertochangesurvey.settings.php.default](conf/powertochangesurvey.settings.php.default) configuration file

        $ cd CIVICRM_EXTENSIONS_DIR/com.powertochange.powertochangesurvey/conf
        $ cp powertochangesurvey.settings.php.default powertochangesurvey.settings.php

1. Modify your local version of powertochangesurvey.settings.php to reflect your CiviCRM system. At the very least, you should modify the fields marked with the value *CHANGE* in the default configuration file. For more information, see [Customizing the extension](#customizing-the-extension).

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

1. To enable the auto-population of surveys and petitions in the *Related survey* custom field associated with the Rejoiceable custom group, store the *Related survey* custom field ID in the **MYCRAVINGS_RELATED_SURVEY_CUSTOMFIELD_ID** variable located in the powertochangesurvey.settings.php configuration file.

## Customizing the extension

### Configuration parameters

Several configuration parameters are exposed in [powertochangesurvey.settings.php.default](conf/powertochangesurvey.settings.php.default) which is located in *CIVICRM_EXTENSIONS_DIR/com.powertochangesurvey/conf/*. You should modify these parameters to reflect your environment.

See [the default settings file](conf/powertochangesurvey.settings.php.default) for a complete list of parameters and descriptions.

### Message templates

Two message templates are used by the extension: one for email messages and the other for SMS text messages. You can customize the message templates with the following tokens:
  * **{mycravings_url}**: The auto-generated short URL which references the long URL defined in the MYCRAVINGS_SMS_MESSAGE_LONG_URL configuration parameter. **NOTE**: This token is only available to the SMS message template.
  * **{contact.COLUMN_NAME}**: Any Contact-related column associated with the person providing the survey information (also known as the *target contact*). Possible COLUMN_NAME values include: first_name, last_name, nick_name, legal_identifier, display_name, birth_date, contact_type, contact_sub_type.
  * **{contact_relationship_school.COLUMN_NAME}**: Any Contact-related column of the School associated with the target contact. Possible COLUMN_NAME values include: first_name, last_name, nick_name, legal_identifier, display_name, birth_date, contact_type, contact_sub_type.

If you want the extension to use a different message template then modify the following configuration parameters in powertochangesurvey.settings.php:
  * MYCRAVINGS_SMS_MESSAGE_TEMPLATE
  * MYCRAVINGS_EMAIL_MESSAGE_TEMPLATE

**WARNING**: Be aware of the 160-character limit on SMS messages. Remember to account for variable length tokens when measuring the length e.g., first name, school name, etc. If the body exceeds the limit, the message will not be sent.

## Creating custom forms

Follow these steps to create a custom Webform.

1. If needed, create new custom groups and custom fields. Wherever possible, try to re-use custom fields. If a custom field will be used in multiple surveys then add it to the *MyCravings - Common* custom group (Administer - Customize Data and Screens - Custom Fields). Otherwise, add the field to a different custom group.

1. Create a Campaign (Campaigns - New Campaign). *Note*: You do not need to create a new campaign for every petition. Campaigns often contain multiple petitions.
  * Campaign type: Constituent Engagement
  * Campaign status: In progress

1. Create a Petition (Campaigns - New Petition). This step is required to facilitate the integration with Webform CiviCRM which currently does not support the creation of Petition/Survey entities from within the Webform GUI. A long-term goal is to remove this step.
  * Campaign: Name of the associated campaign
  * Contact profile: Select anything. Webform CiviCRM does not rely on Profiles, so you can select any value (note: you must select a profile to complete the Petition creation process)
  * Activity profile: Leave blank

1. Edit the Email and SMS message [templates](#message-templates)

1. Switch to the Drupal GUI

1. Create a new Webform (Content - Add content - Webform)
  * Title: Name of the Petition/Survey (By default, this title will be the CiviCRM Activity subject)

1. After clicking save, click the *Form settings* link within the *Webform* tab, and modify the settings. You should at least consider the following:
  * Confirmation message
  * Submission limits
  * Submission access rights

1. Click the "CiviCRM" tab, and check the *Enable CiviCRM Processing* checkbox to enable the CiviCRM entities to the form.

1. *Number of Contacts* should be 2 if you want to create a relationship between the student and a school.

1. Add the CiviCRM entities. Each top-level bullet corresponds to a tab on the left-side of the configuration page.
  * Contact 1:
      * Contact Type: Individual (Student)
      * *Contact Fields* group
          * Uncheck *Existing contact*
          * Uncheck *Contact ID*
          * Check *First name*, *Last name* and *Gender*
      * Number of phone fields: 1
          * Check *Phone number* and *Mobile* phone type
      * Number of email fields: 1
          * Check *Email*
  * Contact 2:
      * Contact Type: Organization
      * Type of Organization: School
      * *Contact Fields* group
          * Check *Existing contact*
          * Uncheck *Contact ID*
      * *Enable Relationship Fields* = Yes
          * Relationship to Contact 1 = Student attends school
          * Relationship to Contact 1 is active = Yes
          * Relationship to Contact 1 Permission = No Permissions
  * Activity:
      * Activity Type: Petition
      * Update Existing Activity: Select *Uncontacted*
      * Default activity subject: The value that will be displayed in the Activity subject field as viewed in CiviCRM
      * *Activity* group
          * Activity participants: Select *Contact 1*
          * Campaign: Select the CiviCRM campaign
          * Survey/Petition: Select the CiviCRM petition
          * Check *Activity details* (this will be used to store data input notes)
          * Activity Status: Select *Uncontacted*
          * Activity Priority: Select *N/A*
          * Assign Activity to: Select *No One*
      * *MyCravings - Common* group
          * If you want to send a MyCravings email or text and calculate the follow-up priority then check the following fields, otherwise leave all of the fields unchecked:
              * MyCravings - Magazine
              * MyCravings - Journey
              * MyCravings - Gauge
          * Check *MyCravings - Data inputter* (used to collect inputs from the data inputter)
  * Additional options:
      * Uncheck *Create fieldsets*
      * Check *Confirm subscriptions*
      * Uncheck *Block unknown users*

1. Click *Save settings* to save your changes.

1. Click the *Webform* tab and click *Form components*.

1. Re-order the form components to your liking

1. Follow these steps if you want to expose an auto-complete field to anonymous users (e.g., Schools).  **WARNING**: Expose information with caution - double-check that you have the necessary access rights established. For more information, see [Working with existing contact](https://drupal.org/node/1615380).
    1. Click the Webform tab
    1. Click *edit* to the right of the respective field
    1. Disable *Enforce Permissions* in the *Filter* field group.

1. Follow these steps if you want to control the position or visibility of field labels:
    1. Click the Webform tab
    1. Click *edit* to the right of the respective field
    1. In the *Label display* select box, choose the desired value

1. Click the *View* tab to access the form

## Reporting

### Diagnostics

Whenever a MyCravings survey is submitted, the internal processing state is stored with the Activity in the *MyCravings - Processing state (internal)* field. As a diagnostic measure, you are encouraged to periodically generate a report on all incomplete surveys.

## Testing

The following list of functional system tests should be executed on the staging server prior to deployment to production.

<table border="1">
  <tr>
    <td><strong>ID</strong></td>
    <td><strong>Summary</strong></td>
    <td><strong>Priority</strong></td>
    <td><strong>Email/SMS</strong></td>
    <td><strong>Final State</strong></td>
  </tr>
  <tr>
    <td>1</td>
    <td>Magazine=null, journey=null, gauge=null. Null priority. Null state.</td>
    <td>Null</td>
    <td>Null</td>
    <td>Null (the customgroup civicrm hook is not called since no custom values were specified)</td>
  </tr>
  <tr>
    <td>2</td>
    <td>Magazine=null, journey=null, gauge=1. Not interested. No message sent.</td>
    <td>Not interested</td>
    <td>None</td>
    <td>Complete - no message sent</td>
  </tr>
  <tr>
    <td>3</td>
    <td>Magazine=yes, gauge=4. Hot. Send SMS.</td>
    <td>Hot</td>
    <td>SMS</td>
    <td>Complete</td>
  </tr>
  <tr>
    <td>4</td>
    <td>Magazine=no, gauge=5, journey=yes. Hot. Send email.</td>
    <td>Hot</td>
    <td>Email</td>
    <td>Complete</td>
  </tr>
  <tr>
    <td>5</td>
    <td>Magazine=yes, gauge=3. Medium. Do not provide email or phone.</td>
    <td>Medium</td>
    <td>None</td>
    <td>Error - invalid contact info</td>
  </tr>
  <tr>
    <td>6</td>
    <td>Magazine=no, gauge=2, journey=yes. Medium. Send SMS.</td>
    <td>Medium</td>
    <td>SMS</td>
    <td>Complete</td>
  </tr>
  <tr>
    <td>7</td>
    <td>Magazine=yes, gauge=5, journey=yes. Hot. Invalid phone number</td>
    <td>Hot</td>
    <td>None</td>
    <td>Error - invalid contact info</td>
  </tr>
  <tr>
    <td>8</td>
    <td>Magazine=yes, gauge=5, journey=yes. Hot. Re-use email address with same survey.</td>
    <td>Hot</td>
    <td>Email</td>
    <td>Complete</td>
  </tr>
  <tr>
    <td>9</td>
    <td>Magazine=yes, gauge=5, journey=yes. Hot. Re-use phone and email address with same survey.</td>
    <td>Hot</td>
    <td>Email</td>
    <td>Complete - should display the full P2C URL in the text message.</td>
  </tr>
</table>

## Design
See the [survey design document](docs/DESIGN.md)

## Developing

### Installing in development environment

1. Clone the repository

        $ git clone https://github.com/PowerToChange/com.powertochange.powertochangesurvey.git
        $ cd com.powertochange.powertochangesurvey

1. Configure your local environment. CIVICRM_SETTINGS_DIR is the directory that contains the civicrm.settings.php file (e.g., /var/www/drupal/sites/default/)

        $ cd vendor/civix
        $ php ../composer/composer install
        $ vendor/civix/civix config:set civicrm_api3_conf_path CIVICRM_SETTINGS_DIR
        $ vendor/civix/civix civicrm:ping

1. [Install the extension](http://wiki.civicrm.org/confluence/display/CRMDOC43/Extensions)

### Generating an extension Zip file for deployment

The excluded files and directories are listed in build/zip_exclude.conf

        $ cd com.powertochange.powertochangesurvey
        $ vendor/civix/civix build:zip

The generated Zip file is located in the build directory.

### Load testing

The [Grinder](http://grinder.sourceforge.net/) is used to load test form submissions. You need to meet the following pre-requisities:

1. [Install Grinder version 3](http://grinder.sourceforge.net/download.html)
1. Create the shell scripts located at the bottom of the [how to start page](http://grinder.sourceforge.net/g3/getting-started.html#howtostart

Now you're ready to start load testing.

1. Create a symlink (or copy) the [tests/performance/grinder.properties](tests/performance/grinder.properties) file to $GRINDER_HOME on your local machine.

1. Review the sections marked **REVIEW** in the [tests/performance/mycravings_survey_1.py](tests/performance/mycravings_1.py) Grinder test configuration file. You need to ensure that the test script aligns with the survey form used in the load case.

1. Modify the SMS provider in the [powertochangesurvey.settings.php](conf/powertochangesurvey.settings.php.default) configuration file to use the Twilio test connection (likely named "Twilio - test"). This is done to avoid sending messages to unknown people and using the messaging quota.

1. Start the Console

        $ $GRINDER_HOME/startConsole.sh

1. Start the Agent

        $ $GRINDER_HOME/startAgent.sh

1. From the Console, load the mycravings_1.py test file and sync the file contents with the Agent

1. Start the load test

1. Review the results. *NOTE*: Occassionally, Grinder does not display the test results in the Console, so I had to manually inspect the test result files in the log directory.

#### Load test results
Here is a summary of load test results against the *MyCravings - Load test* survey.

<table border="1">
  <tr>
    <td><strong>Date</strong></td>
    <td><strong>Concurrent users</strong></td>
    <td><strong>Duration (min)</strong></td>
    <td><strong>Total transactions</strong></td>
    <td><strong>Comments</strong></td>
  </tr>
  <tr>
    <td>2013-08-03</td>
    <td>3 (1 process, 3 threads)</td>
    <td>5 min</td>
    <td>346</td>
    <td>Approx 1.1/sec 70/min; Apache CPU ~45% per thread; MySQL CPU% relatively low; p2c.sh was down so only included email address in forms</td>
  </tr>
  <tr>
    <td>2013-08-03</td>
    <td>4 (1 process, 4 threads)</td>
    <td>5 min</td>
    <td>301</td>
    <td>Approx 1.0/sec 60/min; Apache CPU ~45% per thread; MySQL CPU% relatively low; p2c.sh was down so only included email address in forms</td>
  </tr>
</table>

### Unit testing

#### Configuring

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

#### Running tests

There are no unit tests at the moment.

### Generating application code

See [CiviCRM - Create a module extension](http://wiki.civicrm.org/confluence/display/CRMDOC43/Create+a+Module+Extension).

### Civix - The CiviCRM application framework

This extension was built with the [civix application framework](https://github.com/totten/civix/). To upgrade civix, composer, or any related packages, follow these steps.

#### Upgrading composer

For more information, see [Composer](http://getcomposer.org/)

        $ cd vendor/composer
        $ curl -s http://getcomposer.org/installer | php

#### Upgrading civix

For more information, see [https://github.com/totten/civix/](https://github.com/totten/civix/).

1. Download the desired version of civix from [Github] (https://github.com/totten/civix/).
2. Unzip the contents of the Zip file

        $ cd vendor
        $ unzip civix-master.zip
        $ mv civix-master civix

3. Upgrade civix via composer

        $ cd civix
        $ ../composer/composer install

### Tools

#### API
Using the API from the command-line:
  * List of [entities and commands](http://localhost/civicrm/api/doc)
  * Use the [API explorer](http://localhost/civicrm/api/explorer)
  * Drush is your friend:

        $ sudo -u apache drush cvapi CustomField.get custom_group_id=4

In order to avoid sucking in a continuously-growing stream of data, consider using chains and filters:
  * http://forum.civicrm.org/index.php/topic,29187.0.html
  * https://github.com/civicrm/civicrm-core/blob/master/api/v3/examples/Activity/DateTimeHigh.php
  * https://github.com/civicrm/civicrm-core/blob/master/api/v3/examples/Activity/DateTimeLow.php
  * http://forum.civicrm.org/index.php/topic,28409.msg121534.html#msg121534

Watch out for bugs in the API (make sure you test well). I ran into a few issues when working with more complex queries (people on forums expressed similar experience). For example, it may be better to execute several requests than trying to chain multiple entities together via joins, filters, etc.

I found that the source code provides the most helpful documentation. See https://github.com/civicrm/civicrm-core/tree/master/api
