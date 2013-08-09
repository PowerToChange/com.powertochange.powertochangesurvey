## Data model ##
  * [Entity Relationship Diagram](p2c_mycravings_survey_erd.png)

## Enhancements ##
  1. **Improve survey submission throughput**. Measured rate on Aug 8, 2013 was 70 transactions per minute (tpm). Goal is to reach at least 100 tps. Profiling indicates the following breakdown: 25-30% in YOURLS, 10-15% in Twilio, 45% in CiviCRM and Drupal, 5-10% in the remainder of the custom extension code. I recommend investigating YOURLS before CiviCRM/Drupal. For profiling Drupal, I highly recommend using the Devel module in combination with the xhprof PHP profiler.
  1. **Toggle follow-up priority calculation from Drupal webform UI**. Create a hidden field in the Drupal webform to control whether the follow-up priority is calculated (and therefore a communication is generated).  This can enable form creators to use the *MyCravings - Common* fields without calculating a follow-up priority, sending a communication, etc.

## Why use petitions and not surveys?
CiviCRM [petitions](http://book.civicrm.org/user/current/petition/what-you-need-to-know/) are essentially online surveys available to anonymous users (survey activity types are only available to authenticated users). The majority of form submissions come from anonymous users.

## Why use petitions and not custom activity types?
An activity describes the means by which information is collected (e.g., "phone", "email", "petition", etc.), rather than the unique set of information associated with a user. Under the petition approach, you have the advantage of separating the information (field sets) from the means used to collect the data (activity).

In short, petitions better align with the CiviCRM data model.

## Why use Drupal Webform?

### Pros
  1. Much more user-friendly (intuitive, faster)
  1. Interleaving of activity and contact fields to align with paper forms
  1. Supports relationships within the form builder (e.g. Individual-School). Even provides auto-complete (pulls in the Schools from CiviCRM)
  1. Supports multiple inputs per user session
  1. Hooks into all of the webform components: extensive validation routines, multi-page forms, Drupal hooks and templating
  1. Webform results are stored in Drupal and CiviCRM, so you can leverage reporting capabilities of both

### Cons
  1. User is required to leave CiviCRM to create the form in Drupal (usability issue)
  1. Does not align with the CiviCRM Petition data model. For details, see http://forum.civicrm.org/index.php/topic,28877.0.html
  1. Tighter coupling with Drupal. Short-term this will likely not be a problem, because webform is relatively more advanced.

Until CiviCRM clarifies their long-term plan on the degree of coupling with Drupal, webform seems to be the best way to go.

## How is the follow-up priority calculated?
The calculation of the follow-up priority is driven by the presence of the following custom fields located within the *MyCravings - Common* custom fields group:
  * MyCravings - Magazine
  * MyCravings - Journey
  * MyCravings - Gauge

For the latest and most accurate documentation, refer to the [source code](../powertochangesurvey.php) (the name of the function is _powertochangesurvey_calc_followup_priority).

Here is a summary of the algorithm:
  * If all of the custom fields (magazine, journey, gauge) are unassigned then do not assign a follow-up priority
  * If at least one of the custom fields has a value then:
      * If Magazine = No and Journey = No and Gauge = 1 then assign *Not interested*
      * If Magazine = Yes and Gauge >= 4 then assign *Hot*
      * If Magazine = No and Gauge >= 4 and Journey = Yes then assign *Hot*
      * If Magazine = Yes and Gauge = 3 then assign *Medium*
      * If Magazine = No and Gauge = 2 and Journey = Yes then assign *Medium*
      * Else default to *Mild*

## When is a communication (email/SMS) sent to the survey submitter?
A communication (email/SMS) is only sent if the priority is Mild, Medium or Hot, and the user-provided phone or email is valid.

SMS messages take precedence, so if the phone number is valid, send a text message. Otherwise, if only a valid email is provided, send an email.

## Contingency planning
In the event that the Drupal/CiviCRM system is unable to process form submissions, custom Google Forms will be used to collect information. Although Google Forms lacks adequate data validation and integration with CiviCRM entities (e.g., retrieve all schools), it can handle high throughput rates and offers the ability to hook into the CiviCRM API via HTTP POST calls.

## References
   * [CiviCRM Custom Fields](http://book.civicrm.org/user/current/organising-your-data/custom-fields/)
