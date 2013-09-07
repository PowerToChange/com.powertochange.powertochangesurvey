<?php

/**
 * CustomSurveyFields.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_custom_survey_fields_get_spec(&$spec) {
  $params['activity_type_id']['title'] = 'ActivityType ID';
  $params['campaign_id']['title'] = 'Campaign ID';
  $params['survey_id']['title'] = 'Survey ID';
}

/**
 * CustomSurveyFields.Get API
 *
 * Extract all of the CustomFields associated with the stored petitions.
 *
 * Important note: Profiles are not used to store the Individual and Activity
 * survey fields due to limitations in the survey/petition construction form
 * and overall usability issues (see design notes). Instead, petitions/surveys
 * are constructed in the Drupal Webform CiviCRM module - a cleaner, more 
 * intuitive, and CiviCRM-recommended utility. So, this API establishes a 
 * Drupal DB connection and extracts the relevant fields from the webform 
 * config. This API allows us to abstract the petition-field associated from
 * 3rd party apps (e.g., the Connect app).
 *
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_custom_survey_fields_get($params) {
  $returnValues = array();

  if (defined('CIVICRM_UF') && CIVICRM_UF == 'Drupal') {
    if (module_exists('webform_civicrm')) {
      // Extract and load/unserialize all of the Webform CiviCRM instances
      $forms = db_query('SELECT nid, data FROM {webform_civicrm_forms}');
      foreach ($forms as $form) {
        $survey_config = array();
        $webform_obj = unserialize($form->data);

        // Verify that an activity is associated with this form
        if (isset($webform_obj['activity'])) {
          $activity = $webform_obj['activity'][1]['activity'][1];

          // Retrieve the primary composite key: campaign, survey, activity 
          // type
          $activity_type_id = $activity['activity_type_id'];

          $campaign_id = NULL;
          if (isset($activity['activity_campaign_id'])) {
            $campaign_id = $activity['activity_campaign_id'];
          }

          $survey_id = NULL;
          if (isset($activity['activity_survey_id'])) {
            $survey_id = $activity['activity_survey_id'];
          }

          // Filter the results by the supplied params
          if (isset($params['activity_type_id'])
              && $activity_type_id !== $params['activity_type_id'])
          {
            continue;
          }

          if (isset($params['campaign_id'])
              && $campaign_id !== $params['campaign_id'])
          {
            continue;
          }

          if (isset($params['survey_id'])
              && $survey_id !== $params['survey_id'])
          {
            continue;
          }

          $survey_config = array(
            'activity_type_id' => $activity_type_id,
            'campaign_id' => $campaign_id,
            'survey_id' => $survey_id,
          );

          // Store contact field data
          $contact_fieldsets = array();

          // Fields vary by entity (e.g., contact, phone, email, activity, 
          // customgroup<ID>), and by type (e.g., activity, contact)
          //
          // NOTE: Only the student relationship fields are included in the 
          // response

          // Retrieve the custom groups and fields
          $form_fields = db_query(
            'SELECT form_key FROM {webform_component} WHERE nid = :nid',
            array('nid' => $form->nid));
          foreach ($form_fields as $form_field) {
            if (preg_match('/^civicrm_(\d{1})_([^_]+)_1_([^_]+)_(.+)$/', $form_field->form_key, $matches)) {
              // Entity index starts at one. Only Contact entities have more
              // than one index (e.g., Student and School contacts)
              $fieldset_index = $matches[1];
              $fieldset_type = $matches[2];
              $entity_type = $matches[3];
              $field_name = $matches[4];

              // Ignore fieldsets
              if ($entity_type == 'fieldset') {
                continue;
              }

              // Check whether this is a custom group, and if so, extract the 
              // ID
              $custom_group_id = NULL;
              $custom_field_id = NULL;
              $final_entity_type = $entity_type;
              if (preg_match('/^cg(\d+)$/', $entity_type, $cg_matches)) {
                $custom_group_id = $cg_matches[1];
                $final_entity_type = 'customgroup';

                // Extract the custom_field_id from the field name
                if (preg_match('/^custom_(\d+)$/', $field_name, $cf_matches)) {
                  $custom_field_id = $cf_matches[1];
                }
              }

              // If this is not a contact entity, add a row to the final result
              // Contacts are processed later since we need to filter out
              // Schools.
              if ($fieldset_type != 'contact') {
                $tuple_values = array_merge($survey_config, array('custom_field_id' => $custom_field_id));
                $tuple_key = implode(':', $tuple_values);
                $returnValues[$tuple_key] = array_merge(
                  $survey_config,
                  array(
                    'entity_type' => $final_entity_type,
                    'custom_group_id' => $custom_group_id,
                    'custom_field_id' => $custom_field_id,
                    'field_name' => $field_name,
                  )
                );
              } else {
                // fieldset_index: Index representing a unique set of contact fields
                // final_entity_type: contact, email, phone, customfield
                // field_key: Identify the key for this field
                $field_key = $field_name;
                if ($custom_group_id != NULL) {
                  $field_key = $custom_group_id . ':' . $custom_field_id;
                }
                $contact_fieldsets[$fieldset_index][$final_entity_type][$field_key] = $field_name;
              }
            }
          }

          // Remove the School contact - Organizations do not have
          // the first_name field, and we can assume that first_name
          // is included in this form to identify the contact submitting the
          // information.
          $student_contact_fieldset = array();
          for ($ind = 1; $ind <= count($contact_fieldsets); $ind++) {
            if (isset($contact_fieldsets[$ind]['contact']['first_name'])) {
              $student_contact_fieldset = $contact_fieldsets[$ind];
              break;
            }
          }

          // Finally, store the student fields
          foreach ($student_contact_fieldset as $entity_type => $entity_fieldset) {
            foreach ($entity_fieldset as $field_key => $field_name) {
              $custom_group_id = NULL;
              $custom_field_id = NULL;
              if ($entity_type == 'customgroup') {
                $key_subfields = explode(':', $field_key);
                $custom_group_id = $key_subfields[0];
                $custom_field_id = $key_subfields[1];
              }

              $tuple_values = array_merge($survey_config, array('custom_field_id' => $custom_field_id));
              $tuple_key = implode(':', $tuple_values);
              $returnValues[$tuple_key] = array_merge(
                $survey_config,
                array(
                  'entity_type' => $entity_type,
                  'custom_group_id' => $custom_group_id,
                  'custom_field_id' => $custom_field_id,
                  'field_name' => $field_name,
                )
              );
            }
          }
        }
      }
    } else {
      throw new API_Exception('Webform CiviCRM is not installed - unable to complete request.');
    }
  } else {
    throw new API_Exception('Drupal 7.x is not installed - unable to complete request.');
  }

  return civicrm_api3_create_success($returnValues, $params, 'CustomSurveyFields', 'Get');
}
