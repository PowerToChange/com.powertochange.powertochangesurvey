<?php

require_once 'powertochangesurvey.civix.php';

// Load the configuration file
require_once __DIR__ . '/conf/powertochangesurvey.settings.php';

/**
 * Implementation of hook_civicrm_config
 */
function powertochangesurvey_civicrm_config(&$config) {
  _powertochangesurvey_civix_civicrm_config($config);
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function powertochangesurvey_civicrm_xmlMenu(&$files) {
  _powertochangesurvey_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function powertochangesurvey_civicrm_install() {
  return _powertochangesurvey_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function powertochangesurvey_civicrm_uninstall() {
  return _powertochangesurvey_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function powertochangesurvey_civicrm_enable() {
  $result = _powertochangesurvey_civix_civicrm_enable();
  _powertochangesurvey_provision_entities();
  return $result;
}

/**
 * Implementation of hook_civicrm_disable
 */
function powertochangesurvey_civicrm_disable() {
  return _powertochangesurvey_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function powertochangesurvey_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _powertochangesurvey_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function powertochangesurvey_civicrm_managed(&$entities) {
  return _powertochangesurvey_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_tokens
 *
 * This hook is called to allow custom tokens to be defined.
 * Their values will need to be supplied by hook_civicrm_tokenValues.
 */
function powertochangesurvey_civicrm_tokens(&$tokens) {
  // WARNING: I first attempted to use the CRM_Contact_DAO::export
  // function but it returned fields that do not match the civicrm_contact 
  // table
  $contact_school_cols = array(
    'id',
    'contact_type',
    'contact_sub_type',
    'legal_identifier',
    'external_identifier',
    'sort_name',
    'display_name',
    'nick_name',
    'legal_name',
    'first_name',
    'middle_name',
    'last_name',
    'job_title',
    'birth_date',
    'deceased_date',
    'household_name',
    'organization_name',
  );
  $token_cols = array();
  foreach ($contact_school_cols as $col) {
    $token_cols['contact_relationship_school.' . $col] = 'School contact column ' . $col;
  }
  $tokens['contact_relationship_school'] = $token_cols;
}

/**
 * Implementation of hook_civicrm_tokenValues
 *
 * This hook is called to get all the values for the tokens registered.
 * Use it to overwrite or reformat existing token values, or supply the
 * values for custom tokens you have defined in hook_civicrm_tokens()
 */
function powertochangesurvey_civicrm_tokenValues(&$values, $cids, $job = null, $tokens = array(), $context = null) {
  if (!empty($tokens['contact_relationship_school'])) {
    // Extract the column names
    $school_cols = array();
    foreach ($tokens['contact_relationship_school'] as $col => $desc) {
      if (preg_match('/^contact_relationship_school\.(.+)/', $col, $matches)) {
        $school_cols[] = $matches[1];
      }
    }

    // Result columns
    $sql = "SELECT civicrm_contact." . implode(', civicrm_contact.', $school_cols);
    $sql .= ", civicrm_relationship.contact_id_a";

    // Joins
    $sql .= " FROM civicrm_contact";
    $sql .= " INNER JOIN civicrm_relationship ON civicrm_contact.id = civicrm_relationship.contact_id_b";
    $sql .= " INNER JOIN civicrm_relationship_type ON civicrm_relationship.relationship_type_id = civicrm_relationship_type.id";

    // Filters
    $sql .= " WHERE civicrm_relationship_type.contact_type_b = '" . MYCRAVINGS_RELATIONSHIP_SCHOOL_TYPE_B . "'";
    $sql .= " AND civicrm_relationship_type.contact_sub_type_b = '" . MYCRAVINGS_RELATIONSHIP_SCHOOL_SUBTYPE_B . "'";
    $sql .= " AND civicrm_relationship.contact_id_a IN (" . implode(',', $cids) . ")";

    // Map to check whether the contact ID has been processed
    $contact_done = array();

    // Execute the query and assign the values to the contacts
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $row = $dao->toArray();
      $individual_id = $row['contact_id_a'];
      if (!empty($contact_done[$individual_id])) {
        continue;
      }

      // Prefix the columns with contact_relationship_school
      $prefixed_row = array();
      foreach ($row as $col_name => $col_value) {
        $prefixed_row['contact_relationship_school.' . $col_name] = $col_value;
      }

      // Assign the values
      $values[$individual_id] = empty($values[$individual_id]) ? $prefixed_row : $values[$individual_id] + $prefixed_row;
      $contact_done[$individual_id] = TRUE;
    }
  }
}

/**
 * Implementation of hook_civicrm_customFieldOptions
 *
 * This hook is called when CiviCRM needs to edit/display
 * a custom field with options (select, radio, checkbox,
 * adv multiselect)
 */
function powertochangesurvey_civicrm_customFieldOptions($fieldID, &$options, $detailedFormat = false) {
  if ($fieldID == MYCRAVINGS_RELATED_SURVEY_CUSTOMFIELD_ID) {
    // Remove existing option values in the event that someone mistakenly
    // added values in the CiviCRM GUI.
    foreach ($options as $key => $value) {
      unset($options[$key]);
    }

    // Retrieve all of the Campaign-Petition/Survey pairs
    $sql = "
      SELECT civicrm_campaign.title AS campaign_title,
             civicrm_survey.id AS survey_id,
             civicrm_survey.title AS survey_title
      FROM civicrm_campaign
        INNER JOIN civicrm_survey ON civicrm_campaign.id = civicrm_survey.campaign_id";
    $dao = CRM_Core_DAO::executeQuery($sql);
    while ($dao->fetch()) {
      $value = $dao->survey_id;
      $label = $dao->campaign_title . ' : ' . $dao->survey_title;

      if ($detailedFormat) {
        $id = 'survey-' . $dao->survey_id;
        $options[$id] = array(
          'id' => $id,
          'value' => $value,
          'label' => $label,
        );
      } else {
        $options[$value] = $label;
      }
    }
  }
}

/**
 * Provision the system with the entities required by this extension
 *
 * The following entities are populated/modified by this function:
 *  - Message Templates (SMS, Email)
 *  - CustomGroup "MyCravings - Common" and related CustomFields
 *
 * @return TRUE or FALSE
 */
function _powertochangesurvey_provision_entities() {
  $result = TRUE;

  // Message Template (SMS)
  $get_params = array('msg_title' => MYCRAVINGS_SMS_MESSAGE_TEMPLATE);
  $msg_template = CRM_Core_BAO_MessageTemplates::retrieve($get_params, $defaults);
  if ($msg_template == NULL) {
    $add_params = array(
      'msg_title' => MYCRAVINGS_SMS_MESSAGE_TEMPLATE,
      'msg_subject' => MYCRAVINGS_SMS_MESSAGE_TEMPLATE,
      'msg_text' => '{contact.first_name}, thanks for completing a Power to Change survey! Explore our blog on cravings & topics that matter: http://p2c.sh/blogs',
      'is_active' => 1,
    );
    $add_result = CRM_Core_BAO_MessageTemplates::add($add_params);
    if ($add_result == NULL) {
      $result = FALSE;
    }
  }

  // Message Template (Email)
  $get_params = array('msg_title' => MYCRAVINGS_EMAIL_MESSAGE_TEMPLATE);
  $msg_template = CRM_Core_BAO_MessageTemplates::retrieve($get_params, $defaults);
  if ($msg_template == NULL) {
    $add_params = array(
      'msg_title' => MYCRAVINGS_EMAIL_MESSAGE_TEMPLATE,
      'msg_subject' => MYCRAVINGS_EMAIL_MESSAGE_TEMPLATE,
      'msg_text' => '{contact.first_name}, got your cravings survey! We\'ll call 2 connect -PowertoChange',
      'is_active' => 1,
    );
    $add_result = CRM_Core_BAO_MessageTemplates::add($add_params);
    if ($add_result == NULL) {
      $result = FALSE;
    }
  }

  // OptionGroups and OptionValues
  $option_groups = array(
    array(
      'group_name' => MYCRAVINGS_OPTION_GROUP_PRIORITY_NAME,
      'group_title' => MYCRAVINGS_OPTION_GROUP_PRIORITY_TITLE,
      'group_values' => array(
        array(
          'option_name' => 'Hot',
          'option_label' => 'Hot',
          'option_value' => MYCRAVINGS_OPTION_PRIORITY_HOT,
        ),
        array(
          'option_name' => 'Medium',
          'option_label' => 'Medium',
          'option_value' => MYCRAVINGS_OPTION_PRIORITY_MEDIUM,
        ),
        array(
          'option_name' => 'Mild',
          'option_label' => 'Mild',
          'option_value' => MYCRAVINGS_OPTION_PRIORITY_MILD,
        ),
        array(
          'option_name' => 'Not_Interested',
          'option_label' => 'Not Interested',
          'option_value' => MYCRAVINGS_OPTION_PRIORITY_NO_INTEREST,
        ),
        array(
          'option_name' => 'N/A',
          'option_label' => 'N/A',
          'option_value' => MYCRAVINGS_OPTION_PRIORITY_NA,
        ),
      ),
    ),
    array(
      'group_name' => MYCRAVINGS_OPTION_GROUP_PROCESSING_STATE_NAME,
      'group_title' => 'MyCravings - Processing state (internal)',
      'group_values' => array(
        array(
          'option_name' => 'Follow-up priority',
          'option_label' => 'Follow-up priority',
          'option_value' => MYCRAVINGS_STATE_FOLLOWUP_PRIORITY,
        ),
        array(
          'option_name' => 'Load contact',
          'option_label' => 'Load contact',
          'option_value' => MYCRAVINGS_STATE_LOAD_CONTACT,
        ),
        array(
          'option_name' => 'Send message',
          'option_label' => 'Send message',
          'option_value' => MYCRAVINGS_STATE_SEND_MESSAGE,
        ),
        array(
          'option_name' => 'Complete',
          'option_label' => 'Complete',
          'option_value' => MYCRAVINGS_STATE_COMPLETE,
        ),
        array(
          'option_name' => 'Complete - no message sent',
          'option_label' => 'Complete - no message sent',
          'option_value' => MYCRAVINGS_STATE_COMPLETE_NO_MESSAGE_SENT,
        ),
        array(
          'option_name' => 'Error - invalid contact info',
          'option_label' => 'Error - invalid contact info',
          'option_value' => MYCRAVINGS_STATE_ERROR_INVALID_CONTACT_INFO,
        ),
        array(
          'option_name' => 'Error - failed to send message',
          'option_label' => 'Error - failed to send message',
          'option_value' => MYCRAVINGS_STATE_ERROR_MESSAGE_SEND,
        ),
      ),
    ),
    array(
      'group_name' => MYCRAVINGS_OPTION_GROUP_MAGAZINE_NAME,
      'group_title' => MYCRAVINGS_OPTION_GROUP_MAGAZINE_TITLE,
      'group_values' => array(
        array(
          'option_name' => 'no_thanks',
          'option_label' => 'no thanks',
          'option_value' => MYCRAVINGS_OPTION_MAGAZINE_NO_VALUE,
        ),
      ),
    ),
    array(
      'group_name' => MYCRAVINGS_OPTION_GROUP_JOURNEY_NAME,
      'group_title' => MYCRAVINGS_OPTION_GROUP_JOURNEY_TITLE,
      'group_values' => array(
        array(
          'option_name' => 'do_nothing_right_now',
          'option_label' => 'do nothing right now',
          'option_value' => MYCRAVINGS_OPTION_JOURNEY_NO_VALUE,
        ),
      ),
    ),
    array(
      'group_name' => MYCRAVINGS_OPTION_GROUP_GAUGE_NAME,
      'group_title' => MYCRAVINGS_OPTION_GROUP_GAUGE_TITLE,
      'group_values' => array(
        array(
          'option_name' => '1_No',
          'option_label' => '1) No',
          'option_value' => MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-1',
        ),
        array(
          'option_name' => '2',
          'option_label' => '2)',
          'option_value' => MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-2',
        ),
        array(
          'option_name' => '3_Maybe',
          'option_label' => '3) Maybe',
          'option_value' => MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-3',
        ),
        array(
          'option_name' => '4',
          'option_label' => '4)',
          'option_value' => MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-4',
        ),
        array(
          'option_name' => '5_Very',
          'option_label' => '5) Very',
          'option_value' => MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-5',
        ),
      ),
    ),
  );

  foreach ($option_groups as $opt_group) {
    // Does the group exist? Create if needed
    $get_params = array('version' => 3, 'name' => $opt_group['group_name']);
    $get_result = civicrm_api('OptionGroup', 'Get', $get_params);
    $opt_group_id = 0;

    if (!$get_result['is_error']) {
      if ($get_result['count'] == 0) {
        $add_params = array(
          'version' => 3,
          'name' => $opt_group['group_name'],
          'title' => $opt_group['group_title'],
          'is_active' => 1,
        );
        $add_result = civicrm_api('OptionGroup', 'Create', $add_params);
        if ($add_result['is_error']) {
          $result = FALSE;
        } else {
          $opt_group_id = $add_result['id'];
        }
      } else {
        $opt_group_id = $get_result['id'];
      }
    }

    // Do the values exist? Create if needed
    foreach ($opt_group['group_values'] as $opt_value) {
      // Note: The value is in the configuration file, not the name
      $get_params = array(
        'version' => 3,
        'value' => $opt_value['option_value'],
        'option_group_id' => $opt_group_id,
      );
      $get_result = civicrm_api('OptionValue', 'Get', $get_params);
      if (!$get_result['is_error'] && $get_result['count'] == 0) {
        $add_params = array(
          'version' => 3,
          'option_group_id' => $opt_group_id,
          'name' => $opt_value['option_name'],
          'label' => $opt_value['option_label'],
          'value' => $opt_value['option_value'],
          'is_active' => 1,
        );
        $add_result = civicrm_api('OptionValue', 'Create', $add_params);
        if ($add_result['is_error']) {
          $result = FALSE;
        }
      }
    }
  }

  // Create the CustomFields and associate the fields with the OptionGroups
  $custom_groups = array(
    array(
      'group_name' => MYCRAVINGS_CUSTOM_GROUP_COMMON_NAME,
      'group_title' => MYCRAVINGS_CUSTOM_GROUP_COMMON_TITLE,
      'group_fields' => array(
        array(
          'field_name' => MYCRAVINGS_CUSTOM_FIELD_MAGAZINE_NAME,
          'field_label' => 'Would you like to receive a free magazine?',
          'field_type' => 'String',
          'field_html_type' => 'CheckBox',
          'field_option_group_name' => MYCRAVINGS_OPTION_GROUP_MAGAZINE_NAME,
        ),
        array(
          'field_name' => MYCRAVINGS_CUSTOM_FIELD_JOURNEY_NAME,
          'field_label' => 'Would you like to speak to someone about following Jesus?',
          'field_type' => 'String',
          'field_html_type' => 'CheckBox',
          'field_option_group_name' => MYCRAVINGS_OPTION_GROUP_JOURNEY_NAME,
        ),
        array(
          'field_name' => MYCRAVINGS_CUSTOM_FIELD_GAUGE_NAME,
          'field_label' => 'How interested are you in following Jesus?',
          'field_type' => 'String',
          'field_html_type' => 'Radio',
          'field_option_group_name' => MYCRAVINGS_OPTION_GROUP_GAUGE_NAME,
        ),
        array(
          'field_name' => MYCRAVINGS_CUSTOM_FIELD_PROCESSING_STATE_NAME,
          'field_label' => 'MyCravings - Processing state (internal)',
          'field_type' => 'String',
          'field_html_type' => 'Radio',
          'field_option_group_name' => MYCRAVINGS_OPTION_GROUP_PROCESSING_STATE_NAME,
        ),
      ),
    ),
  );

  foreach ($custom_groups as $custom_group) {
    // Does the group exist? Create if needed
    $get_params = array('version' => 3, 'name' => $custom_group['group_name']);
    $get_result = civicrm_api('CustomGroup', 'Get', $get_params);
    $custom_group_id = 0;

    if (!$get_result['is_error']) {
      if ($get_result['count'] == 0) {
        $add_params = array(
          'version' => 3,
          'name' => $custom_group['group_name'],
          'title' => $custom_group['group_title'],
          'extends' => 'Activity',
          'is_active' => 1,
        );
        $add_result = civicrm_api('CustomGroup', 'Create', $add_params);
        if ($add_result['is_error']) {
          $result = FALSE;
        } else {
          $custom_group_id = $add_result['id'];
        }
      } else {
        $custom_group_id = $get_result['id'];
      }
    }

    // Iterate the fields associated with this CustomGroup
    foreach ($custom_group['group_fields'] as $field) {
      // Does the field exist? Create if needed
      $get_params = array(
        'version' => 3,
        'name' => $field['field_name'],
        'custom_group_id' => $custom_group_id,
      );
      $get_result = civicrm_api('CustomField', 'Get', $get_params);
      if (!$get_result['is_error'] && $get_result['count'] == 0) {
        // Get the OptionGroup ID
        $optgroup_get_params = array('version' => 3, 'name' => $field['field_option_group_name']);
        $optgroup_get_result = civicrm_api('OptionGroup', 'Get', $get_params);
        if (!$optgroup_get_result['is_error'] && $optgroup_get_result['count'] == 1) {
          $option_group_id = $optgroup_get_result['id'];

          $add_params = array(
            'version' => 3,
            'custom_group_id' => $custom_group_id,
            'name' => $field['field_name'],
            'label' => $field['field_label'],
            'data_type' => $field['field_type'],
            'html_type' => $field['field_html_type'],
            'option_group_id' => $option_group_id,
            'is_active' => 1,
          );
          $add_result = civicrm_api('CustomField', 'Create', $add_params);
          if ($add_result['is_error']) {
            $result = FALSE;
          }
        }
      }
    }
  }

  return $result;
}

// State field name
define("MYCRAVINGS_CUSTOM_FIELD_PROCESSING_STATE_NAME", "mycravings_processing_state");
define("MYCRAVINGS_OPTION_GROUP_PROCESSING_STATE_NAME", "mycravings_processing_state");

// State constants
define("MYCRAVINGS_STATE_FOLLOWUP_PRIORITY", 1);
define("MYCRAVINGS_STATE_LOAD_CONTACT", 2);
define("MYCRAVINGS_STATE_SEND_MESSAGE", 3);
define("MYCRAVINGS_STATE_COMPLETE", 4);
define("MYCRAVINGS_STATE_COMPLETE_NO_MESSAGE_SENT", 5);
define("MYCRAVINGS_STATE_ERROR_INVALID_CONTACT_INFO", 6);
define("MYCRAVINGS_STATE_ERROR_MESSAGE_SEND", 7);

// Array of CustomField values (provided and calculated) keyed by Activity 
// entity ID. This must be stored in a global variable since multiple 
// CustomGroups may be referenced by a survey form, and the complete set of 
// required CustomFields may not be available until the 2nd to Nth call
// to powertochangesurvey_civicrm_custom.
$_powertochangesurvey_entity_data = NULL;

// Array of CustomGroup ID to Name values
$_powertochangesurvey_customgroup_map = NULL;

// Array of CustomField ID to Name values
$_powertochangesurvey_customfield_map = NULL;

// Array of CustomField Name to ID values
$_powertochangesurvey_customfield_map_byname = NULL;

/**
 * Get a value associated with a given entity-arraykey pair
 *
 * @param $entity_id The Activity entity ID
 * @param $key Key to the array
 *
 * @return Array key mapped to the key. NULL if the key does not exist.
 */
function _powertochangesurvey_get_entity_value($entity_id, $key) {
  global $_powertochangesurvey_entity_data;

  $value = NULL;
  if (isset($_powertochangesurvey_entity_data[$entity_id][$key])) {
    $value = $_powertochangesurvey_entity_data[$entity_id][$key];
  }
  return $value;
}

/**
 * Set a value associated with a given entity-arraykey pair
 *
 * @param $entity_id The Activity entity ID
 * @param $key Key to the array
 * @param $value Value to assign to the entity ID and Key
 */
function _powertochangesurvey_set_entity_value($entity_id, $key, $value) {
  global $_powertochangesurvey_entity_data;

  $cur_value = NULL;
  if (isset($_powertochangesurvey_entity_data[$entity_id][$key])) {
    $cur_value = $_powertochangesurvey_entity_data[$entity_id][$key];
  }

  // Only write if different and set the dirty flag
  if ($cur_value !== $value && $key !== 'dirty') {
    $_powertochangesurvey_entity_data[$entity_id]['dirty'] = TRUE;
  }
  $_powertochangesurvey_entity_data[$entity_id][$key] = $value;
}

/*
 * Update the state information for this entity. This is typically called after 
 * each powertochangesurvey_civicrm_custom hook call.
 *
 * @param $entity_id Entity ID of the Activity-based CustomGroup.
 */
function _powertochangesurvey_write_entity_data($entity_id) {
  // Only write state if the cache is dirty, otherwise infinite recursion will 
  // occur: a write to the mycravings customvalue table will fire a 
  // powertochangesurvey_civicrm_custom call, which in turn calls this function 
  // to write state ...
  if (_powertochangesurvey_get_entity_value($entity_id, 'dirty')) {
    $priority = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority');
    $state = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_state');

    // Reset the dirty flag before the setValues call, otherwise it will not be 
    // honoured.
    _powertochangesurvey_set_entity_value($entity_id, 'dirty', FALSE);

    // Retrieve the CustomField IDs
    $state_id = _powertochangesurvey_get_customfield_id(MYCRAVINGS_CUSTOM_FIELD_PROCESSING_STATE_NAME);

    $updateParams = array(
      'entityID' => $entity_id,
      'custom_' . $state_id => $state,
    );
    CRM_Core_BAO_CustomValueTable::setValues($updateParams);

    // Update the activity priority
    $api_params = array(
      'version' => '3',
      'id' => $entity_id,
      'priority' => $priority,
    );
    civicrm_api('Activity', 'update', $api_params);
  }
}

/**
 * Get the name of a CustomGroup given a CustomGroup ID.
 *
 * @param $id The CustomGroup ID
 *
 * @return CustomGroup name if the ID is present, NULL otherwise
 */
function _powertochangesurvey_get_customgroup_name($id) {
  global $_powertochangesurvey_customgroup_map;

  $result = NULL;
  if (isset($_powertochangesurvey_customgroup_map[$id])) {
    $result = $_powertochangesurvey_customgroup_map[$id];
  } else {
    $get_params = array('version' => 3, 'id' => $id);
    $get_result = civicrm_api('CustomGroup', 'Get', $get_params);
    if (!$get_result['is_error'] && $get_result['count'] > 0) {
      foreach ($get_result['values'] as $value) {
        // Take the first one
        $_powertochangesurvey_customgroup_map[$id] = $value['name'];
        $result = $value['name'];
        break;
      }
    }
  }

  return $result;
}

/**
 * Get a CustomField column value given the CustomField ID and column name
 *
 * @param $id CustomField ID
 *
 * @return CustomField name if the ID is present, NULL otherwise
 */
function _powertochangesurvey_get_customfield_name($id) {
  global $_powertochangesurvey_customfield_map;
  global $_powertochangesurvey_customfield_map_byname;

  $result = NULL;
  if (isset($_powertochangesurvey_customfield_map[$id])) {
    $result = $_powertochangesurvey_customfield_map[$id];
  } else {
    $get_params = array('version' => 3, 'id' => $id);
    $get_result = civicrm_api('CustomField', 'Get', $get_params);
    if (!$get_result['is_error'] && $get_result['count'] > 0) {
      foreach ($get_result['values'] as $value) {
        // Take the first one, and store in the by Name map
        $_powertochangesurvey_customfield_map_byname[$value['name']] = $id;
        $_powertochangesurvey_customfield_map[$id] = $value['name'];
        $result = $value['name'];
        break;
      }
    }
  }

  return $result;
}

/**
 * Get a CustomField ID by the name of the CustomField
 *
 * @param $name CustomField name
 *
 * @return CustomField ID if present, NULL otherwise
 */
function _powertochangesurvey_get_customfield_id($name) {
  global $_powertochangesurvey_customfield_map;
  global $_powertochangesurvey_customfield_map_byname;

  $result = NULL;
  if (isset($_powertochangesurvey_customfield_map_byname[$name])) {
    $result = $_powertochangesurvey_customfield_map_byname[$name];
  } else {
    $get_params = array('version' => 3, 'name' => $name);
    $get_result = civicrm_api('CustomField', 'Get', $get_params);
    if (!$get_result['is_error'] && $get_result['count'] > 0) {
      foreach ($get_result['values'] as $value) {
        // Take the first one, and store in the by ID map
        $_powertochangesurvey_customfield_map_byname[$name] = $value['id'];
        $_powertochangesurvey_customfield_map[$value['id']] = $name;
        $result = $value['id'];
        break;
      }
    }
  }

  return $result;
}

/**
 * Get the mobile phone type ID
 *
 * @return integer
 */
function _powertochangesurvey_get_mobile_phone_type() {
  $mobile_phone_type_id = NULL;

  // Retrieve the OptionGroup ID - if there is 0 or more than 1, we have a 
  // problem. In this case, fallback to email (very unlikely situation, though)
  $group_result = civicrm_api('OptionGroup', 'get', array('version' => '3', 'name' => 'phone_type'));
  if (!$group_result['is_error'] && $group_result['count'] == 1) {
    // Use a foreach, since the indices are the OptionGroup IDs, and the ID is 
    // unknown at this point
    foreach ($group_result['values'] as $group_data) {
      // Get the option value
      $api_params = array(
        'version' => '3',
        'option_group_id' => $group_data['id'],
        'name' => 'Mobile',
      );
      $value_result = civicrm_api('OptionValue', 'get', $api_params);
      if (!$value_result['is_error'] && $value_result['count'] == 1) {
        foreach ($value_result['values'] as $value_data) {
          $mobile_phone_type_id = $value_data['value'];
        }
      }
    }
  }

  return $mobile_phone_type_id;
}

/**
 * Implementation of hook_civicrm_custom
 *
 * NOTE: This hook is called AFTER the DB write on a custom table.
 *
 * In general, survey entities are processed in the following order:
 *  o Create Individual entity (survey respondent)
 *  o Populate Individual custom fields (e.g., Student Demographics)
 *  o Create Organization - School entity (if necessary)
 *  o Create Email entity for the Individual
 *  o Create Phone entity for the Individual
 *  o Create Relationship entity (Individual is student of Organization-School)
 *  o Create Activity entity for the Individual
 *  o Populate Activity custom fields >= 1 CustomGroups
 *
 * This hook is fired for every CustomGroup definition associated with
 * the activity. Since the number of Activity-based CustomGroups is unknown
 * at the moment of the trigger, each instance will iterate all available
 * fields to perform the desired actions, and abort if the fields are
 * unavailable with the expectation that a subsequent hook will be fired.
 *
 * @param $op Type of operation (e.g., create, edit, etc.)
 * @param $groupID CustomGroup ID
 * @param $entityID Entity ID associated with this CustomGroup. This hook 
 *                  primarily deals with Individual and Activity entities.
 * @param $params Additional parameters
 */
function powertochangesurvey_civicrm_custom($op, $groupID, $entityID, &$params) {
  $groupName = _powertochangesurvey_get_customgroup_name($groupID);
  if (preg_match('/^MyCravings.*/', $groupName)) {
    if ($op == 'create' || $op == 'edit') {
      _powertochangesurvey_process_cravings_customgroup($op, $groupID, $entityID, $params);
    }
  }
}

/**
 * Top-level function that executes the custom operations for every MyCravings
 * survey submission or edit.
 *
 * @param $op Type of operation (e.g., create, edit, etc.)
 * @param $group_id CustomGroup ID
 * @param $entity_id Entity ID associated with this CustomGroup. This hook 
 *                   primarily deals with Individual and Activity entities.
 * @param $params Additional parameters
 */
function _powertochangesurvey_process_cravings_customgroup($op, $group_id, $entity_id, &$params) {
  // Check whether the app finished processing this Activity entity ID
  // Return immediately if there is no outstanding work.
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_state') == MYCRAVINGS_STATE_COMPLETE) {
    return;
  }

  // Calculate Activity follow-up priority
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority') == NULL) {
    _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_FOLLOWUP_PRIORITY);
    _powertochangesurvey_calc_followup_priority($group_id, $entity_id, $params);
  }

  // Load contact
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_state') == MYCRAVINGS_STATE_LOAD_CONTACT) {
    _powertochangesurvey_load_contact($entity_id);
  }

  // Send a message
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_state') == MYCRAVINGS_STATE_SEND_MESSAGE) {
    _powertochangesurvey_send_contact_message($entity_id);
  }

  // Write the entity data to CiviCRM
  _powertochangesurvey_write_entity_data($entity_id);
}

/*
 * Determine the Contact's follow-up priority based on the survey response.
 *
 * Use algorithm created by Russ and Terra (May 2012)
 *
 * @param $group_id CustomGroup ID
 * @param $entity_id Entity ID of the Activity-based CustomGroup.
 * @param $fieldValues CustomField values associated with group_id and entity_id
 */
function _powertochangesurvey_calc_followup_priority($group_id, $entity_id, $field_values) {
  // Ignore irrelevant CustomGroups
  $group_name = _powertochangesurvey_get_customgroup_name($group_id);
  if ($group_name != MYCRAVINGS_CUSTOM_GROUP_COMMON_NAME) {
    return;
  }

  // Store the CustomField value - perform OptionGroup lookup if necessary
  foreach ($field_values as $field_value) {
    $field_name = _powertochangesurvey_get_customfield_name(
      $field_value['custom_field_id']
    );
    _powertochangesurvey_set_entity_value($entity_id, $field_name, $field_value['value']);
  }

  // Retrieve the necessary fields for the follow-up priority calculation
  $magazine = _powertochangesurvey_get_entity_value($entity_id, MYCRAVINGS_CUSTOM_FIELD_MAGAZINE_NAME);
  $journey = _powertochangesurvey_get_entity_value($entity_id, MYCRAVINGS_CUSTOM_FIELD_JOURNEY_NAME);
  $gauge = _powertochangesurvey_get_entity_value($entity_id, MYCRAVINGS_CUSTOM_FIELD_GAUGE_NAME);

  // If all of the necessary custom fields (magazine, journey, gauge) are NULL,
  // do not leave the followup priority calculation state. Testing on the staging
  // server revealed that the powertochangesurvey_civicrm_custom hook can be called
  // multiple times, with the necessary custom field values arriving on the second
  // or third call (not sure why, though)
  if ($magazine == NULL && $journey == NULL && $gauge ==  NULL) {
    return;
  }

  // Parse the magazine, journey and gauge fields into a format that 
  // facilitates easy comparison (bool, bool, int, respectively). The magazine 
  // and journey custom fields are multi-value so the individual values must be
  // extracted by using the CiviCRM value separator (Ctrl-A by default).
  //
  // Note: The CiviCRM value separator prepends and appends the concatenated 
  // string which warrants the use of the substr function to ignore the first 
  // and last characters.

  // Default to FALSE to handle the case of $magazine == NULL
  $wants_magazine = FALSE;
  $magazine_values = explode(CRM_Core_DAO::VALUE_SEPARATOR, substr($magazine, 1, -1));
  foreach ($magazine_values as $magazine_val) {
    if ($magazine_val == MYCRAVINGS_OPTION_MAGAZINE_NO_VALUE) {
      // If the user states "no" then exit immediately with FALSE
      $wants_magazine = FALSE;
      break;
    } elseif ($magazine_val != "") {
      $wants_magazine = TRUE;
    }
  }

  // Default to FALSE to handle the case of $journey == NULL
  $wants_journey = FALSE;
  $journey_values = explode(CRM_Core_DAO::VALUE_SEPARATOR, substr($journey, 1, -1));
  foreach ($journey_values as $journey_val) {
    if ($journey_val == MYCRAVINGS_OPTION_JOURNEY_NO_VALUE) {
      // If the user does not want a journey then exit immediately with FALSE
      $wants_journey = FALSE;
      break;
    } elseif ($journey_val != "") {
      $wants_journey = TRUE;
    }
  }

  if ($gauge == NULL || $gauge == MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-1') {
    $gauge = 1;
  } else {
    $gauge_exp = '/^' . MYCRAVINGS_OPTION_GAUGE_VALUE_PREFIX . '-(\d+)$/';
    if (preg_match($gauge_exp, $gauge, $matches)) {
      $gauge = $matches[1];
    } else {
      $gauge = 1;
    }
  }

  // Get the current followup priority - default to 'Mild'
  $priority = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority');
  if ($priority == NULL) {
    $priority = MYCRAVINGS_OPTION_PRIORITY_MILD;
  }

  // First check for non-interest
  if (!$wants_magazine && $gauge == 1 && !$wants_journey) {
    $priority = MYCRAVINGS_OPTION_PRIORITY_NO_INTEREST;
  } elseif ($wants_magazine && $gauge >= 4) {
    $priority = MYCRAVINGS_OPTION_PRIORITY_HOT;
  } elseif (!$wants_magazine && $gauge >= 4 && $wants_journey) {
    $priority = MYCRAVINGS_OPTION_PRIORITY_HOT;
  } elseif ($wants_magazine && $gauge == 3) {
    $priority = MYCRAVINGS_OPTION_PRIORITY_MEDIUM;
  } elseif (!$wants_magazine && $gauge == 2 && $wants_journey) {
    $priority = MYCRAVINGS_OPTION_PRIORITY_MEDIUM;
  }

  // Store the priority
  _powertochangesurvey_set_entity_value($entity_id, 'mycravings_followup_priority', $priority);

  // Move to the next step
  _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_LOAD_CONTACT);
}

/*
 * Retrieve, validate and load the Contact associated with the Activity
 *
 * @param $entity_id Entity ID of the Activity
 */
function _powertochangesurvey_load_contact($entity_id) {
  // Mobile phone option value
  $mobile_phone_type_id = _powertochangesurvey_get_mobile_phone_type();

  // Multiple contacts may be associated with an Activity. Choose the first 
  // Contact of type 'Individual'
  $target_contacts = CRM_Activity_BAO_ActivityTarget::retrieveTargetIdsByActivityId($entity_id);
  foreach ($target_contacts as $contact_id) {
    $contact_result = civicrm_api('Contact', 'get', array('version' => '3', 'id' => $contact_id));
    if (!$contact_result['is_error'] && $contact_result['count'] > 0) {
      $contact_data = $contact_result['values'][$contact_id];
      if ($contact_data['contact_type'] == 'Individual') {
        // Store this contact ID, first_name and display_name
        _powertochangesurvey_set_entity_value($entity_id, 'target_contact_id', $contact_id);
        _powertochangesurvey_set_entity_value($entity_id, 'target_contact_display_name', $contact_data['display_name']);

        // Retrieve and validate phone information. Only retrieve Mobile phones.
        if ($mobile_phone_type_id !== NULL) {
          $api_params = array(
            'version' => '3',
            'contact_id' => $contact_id,
            'phone_type_id' => $mobile_phone_type_id,
          );

          $phone_result = civicrm_api('Phone', 'get', $api_params);
          if (!$phone_result['is_error'] && $phone_result['count'] > 0) {
            foreach ($phone_result['values'] as $phone_data) {
              // Validate and sanitize the phone number
              // CiviCRM already strips unnecessary characters and stores the 
              // result in phone_numeric
              $phone = $phone_data['phone'];
              $phone_numeric = $phone_data['phone_numeric'];

              $num_digits = strlen($phone_numeric);
              if ($num_digits == 10) {
                // Assume that the user forgot the leading 1. Prepend the 1 and 
                // update the Phone entity
                $phone = '1-' . $phone;
                $phone_numeric = '1' . $phone_numeric;
                $num_digits = 11;

                $api_params = array(
                  'version' => '3',
                  'id' => $phone_data['id'],
                  'phone' => $phone,
                  'phone_numeric' => $phone_numeric,
                );
                civicrm_api('Phone', 'update', $api_params);
              }

              // If the phone number is valid, store it in the entity data
              if ($num_digits == 11) {
                _powertochangesurvey_set_entity_value($entity_id, 'target_contact_phone', $phone_numeric);
              }
            }
          }
        }

        // Retrieve and validate email information
        $api_params = array(
          'version' => '3',
          'contact_id' => $contact_id,
        );
        $email_result = civicrm_api('Email', 'get', $api_params);
        if (!$email_result['is_error'] && $email_result['count'] > 0) {
          foreach ($email_result['values'] as $email_data) {
            // If the email is invalid, mark it as "on hold"
            if (!filter_var($email_data['email'], FILTER_VALIDATE_EMAIL)) {
              $api_params = array(
                'version' => '3',
                'id' => $email_data['id'],
                'on_hold' => '1',
              );
              civicrm_api('Email', 'update', $api_params);
            } else {
              // Email is valid - store in the entity data
              _powertochangesurvey_set_entity_value($entity_id, 'target_contact_email', $email_data['email']);
            }
          }
        }
      }
    }
  }

  // Individual contact ID that will be used for communications
  $target_contact_id = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_id');
  if ($target_contact_id !== NULL) {
    // Update the do_not_sms and do_not_email Contact attributes
    $do_not_sms = TRUE;
    $do_not_email = TRUE;

    $target_phone = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_phone');
    if ($target_phone !== NULL) {
      // Phone is valid
      $do_not_sms = FALSE;
    }

    $target_email = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_email');
    if ($target_email !== NULL) {
      // Email is valid
      $do_not_email = FALSE;
    }

    // Update the Contact entity
    $api_params = array(
      'version' => '3',
      'id' => $target_contact_id,
      'do_not_sms' => $do_not_sms,
      'do_not_email' => $do_not_email,
    );
    civicrm_api('Contact', 'update', $api_params);

    // Update the entity data
    _powertochangesurvey_set_entity_value($entity_id, 'target_contact_do_not_sms', $do_not_sms);
    _powertochangesurvey_set_entity_value($entity_id, 'target_contact_do_not_email', $do_not_email);

    // Update the state
    if ($do_not_sms && $do_not_email) {
      _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_ERROR_INVALID_CONTACT_INFO);
    } else {
      _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_SEND_MESSAGE);
    }
  }
}

/*
 * Send an email or SMS message to the contact
 *
 * It is assumed that this function is called when a valid email or mobile 
 * phone is assigned to the Individual Contact associated with the Activity 
 * entity denoted by entity_id.
 *
 * The do_not_sms and do_not_email Contact attributes are modified according to 
 * the follow-up priority assigned to this user.
 *
 * SMS takes precedence over email: if do_not_sms is FALSE then send a text 
 * message; elseif do_not_email is FALSE then send an email message; else send 
 * nothing.
 *
 * @param $entity_id Entity ID of the Activity
 */
function _powertochangesurvey_send_contact_message($entity_id) {
  // Update do_not_sms and do_not_email based on the calculated follow-up 
  // priority
  $priority = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority');
  $target_contact_id = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_id');
  $do_not_sms = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_do_not_sms');
  $do_not_email = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_do_not_email');

  if ($priority == MYCRAVINGS_OPTION_PRIORITY_NO_INTEREST) {
    $do_not_sms = TRUE;
    $do_not_email = TRUE;

    // Update the Contact entity
    $api_params = array(
      'version' => '3',
      'id' => $target_contact_id,
      'do_not_sms' => $do_not_sms,
      'do_not_email' => $do_not_email,
    );
    civicrm_api('Contact', 'update', $api_params);

    _powertochangesurvey_set_entity_value($entity_id, 'target_contact_do_not_sms', $do_not_sms);
    _powertochangesurvey_set_entity_value($entity_id, 'target_contact_do_not_email', $do_not_email);
  }

  // If do_not_sms and do_not_email are FALSe then move into the completed 
  // state
  if ($do_not_sms && $do_not_email) {
    _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_COMPLETE_NO_MESSAGE_SENT);
  } else {
    // SMS takes precedence
    if (!$do_not_sms) {
      $msg_template = _powertochangesurvey_get_message_template('sms');
      $send_result = _powertochangesurvey_send_contact_message_sms($entity_id, $msg_template);
    } else {
      $msg_template = _powertochangesurvey_get_message_template('email');
      $send_result = _powertochangesurvey_send_contact_message_email($entity_id, $msg_template);
    }

    if ($send_result) {
      _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_COMPLETE);
    } else {
      _powertochangesurvey_set_entity_value($entity_id, 'mycravings_state', MYCRAVINGS_STATE_ERROR_MESSAGE_SEND);
    }
  }
}

/**
 * Get the message template for a given transport type (sms, email)
 *
 * @param $transport One of sms or email
 *
 * @return CRM_Core_DAO_MessageTemplates object
 */
function _powertochangesurvey_get_message_template($transport) {
  if ($transport == 'sms') {
    $params = array('msg_title' => MYCRAVINGS_SMS_MESSAGE_TEMPLATE);
    $msg_template = CRM_Core_BAO_MessageTemplates::retrieve($params, $defaults);
  } elseif ($transport == 'email') {
    $params = array('msg_title' => MYCRAVINGS_EMAIL_MESSAGE_TEMPLATE);
    $msg_template = CRM_Core_BAO_MessageTemplates::retrieve($params, $defaults);
  }

  return $msg_template;
}

/**
 * Send Email message to a Contact from the provided MessageTemplate
 *
 * @param $entity_id Entity ID of the Activity
 * @param $msg_template CRM_Core_DAO_MessageTemplates object
 *
 * @return TRUE on success, otherwise FALSE
 */
function _powertochangesurvey_send_contact_message_email($entity_id, $msg_template) {
  $result = FALSE;

  // Contact that will receive the message
  $contact_id = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_id');
  $display_name = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_display_name');
  $email = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_email');

  // Send the SMS message - replace Contact field tokens in the subject and 
  // body
  $filled_subject = $msg_template->msg_subject;
  $filled_text = $msg_template->msg_text;
  $filled_html = $msg_template->msg_html;
  $contact_result = civicrm_api('Contact', 'get', array('version' => '3', 'id' => $contact_id));
  if (!$contact_result['is_error'] && $contact_result['count'] > 0) {
    // Get the tokens in the strings
    $subject_tokens = CRM_Utils_Token::getTokens($msg_template->msg_subject);
    $text_tokens = CRM_Utils_Token::getTokens($msg_template->msg_text);
    $html_tokens = CRM_Utils_Token::getTokens($msg_template->msg_html);

    // Replace the message template tokens
    $contact_data = $contact_result['values'];
    $filled_subject = CRM_Utils_Token::replaceContactTokens($filled_subject, $contact_data, FALSE, $subject_tokens);
    $filled_text = CRM_Utils_Token::replaceContactTokens($filled_text, $contact_data, FALSE, $text_tokens);
    $filled_html = CRM_Utils_Token::replaceContactTokens($filled_html, $contact_data, TRUE, $html_tokens);

    // Process the hook tokens
    CRM_Utils_Hook::tokens($hook_tokens);
    $categories = array_keys($hook_tokens);
    CRM_Utils_Hook::tokenValues($hook_token_values, array($contact_id), NULL, $hook_tokens, NULL);
    $filled_subject = CRM_Utils_Token::replaceHookTokens($filled_subject, $hook_token_values[$contact_id], $categories, FALSE);
    $filled_text = CRM_Utils_Token::replaceHookTokens($filled_text, $hook_token_values[$contact_id], $categories, FALSE);
    $filled_html = CRM_Utils_Token::replaceHookTokens($filled_html, $hook_token_values[$contact_id], $categories, TRUE);
  }

  // Send the email
  $mail_params = array(
    'groupName' => 'Activity Email Sender',
    'from' => MYCRAVINGS_EMAIL_FROM_ADDRESS,
    'toName' => $display_name,
    'toEmail' => $email,
    'subject' => $filled_subject,
    'cc' => "",
    'bcc' => "",
    'text' => $filled_text,
    'html' => $filled_html,
    'attachments' => array(),
  );

  if (CRM_Utils_Mail::send($mail_params) === TRUE) {
    $result = TRUE;
  }

  return $result;
}

/**
 * Send SMS message to a Contact from the provided MessageTemplate
 *
 * @param $entity_id Entity ID of the Activity
 * @param $msg_template CRM_Core_DAO_MessageTemplates object
 *
 * @return TRUE on success, otherwise FALSE
 */
function _powertochangesurvey_send_contact_message_sms($entity_id, $msg_template) {
  $result = FALSE;

  // Contact that will receive the message
  $contact_id = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_id');

  // Attempt to generate a YOURLS short link
  //$encoded_contact = _powertochangesurvey_encode_url_prefix($contact_id);
  //$keyword = $encoded_contact . MYCRAVINGS_SMS_MESSAGE_SHORT_URL_SUFFIX;
  //$url = MYCRAVINGS_SMS_MESSAGE_SHORT_URL_PREFIX . $keyword;
  //$url_result = _powertochangesurvey_create_shortlink(MYCRAVINGS_SMS_MESSAGE_LONG_URL, $keyword);
  //if ($url_result == FALSE) {
    // Failed to generate a shortened URL - use the full version
    //$url = MYCRAVINGS_SMS_MESSAGE_LONG_URL;
  //}

  // Send the SMS message - replace Contact field tokens
  $filled_text = $msg_template->msg_text;
  $contact_result = civicrm_api('Contact', 'get', array('version' => '3', 'id' => $contact_id));
  if (!$contact_result['is_error'] && $contact_result['count'] > 0) {
    $contact_data = $contact_result['values'];
    $message_tokens = CRM_Utils_Token::getTokens($msg_template->msg_text);
    $filled_text = CRM_Utils_Token::replaceContactTokens($filled_text, $contact_data, FALSE, $message_tokens);

    // Process the custom tokens
    CRM_Utils_Hook::tokens($hook_tokens);
    $categories = array_keys($hook_tokens);
    CRM_Utils_Hook::tokenValues($hook_token_values, array($contact_id), NULL, $hook_tokens, NULL);
    $filled_text = CRM_Utils_Token::replaceHookTokens($filled_text, $hook_token_values[$contact_id], $categories, FALSE);
  }

  // Replace our custom token, MYCRAVINGS_URL_TOKEN, with the $url value
  //$filled_text = preg_replace(MYCRAVINGS_URL_TOKEN_EXP, $url, $filled_text);

  // Send the message
  try {
    $filter_params = array('title' => MYCRAVINGS_SMS_PROVIDER_NAME);
    $provider_data = CRM_SMS_BAO_Provider::getProviders(NULL, $filter_params, TRUE);
    if (count($provider_data) > 0) {
      $provider_id = $provider_data[0]['id'];
      $provider = CRM_SMS_Provider::singleton(array('provider_id' => $provider_id));

      $phone = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_phone');
      $params = array(
        'To' => $phone,
        'contact_id' => $contact_id,
      );

      // If the submitter is Anonymous, use the target contact ID as the source 
      // contact ID in the SMS Activity.
      $session = CRM_Core_Session::singleton();
      if ($session->get('userID') == NULL) {
        $params['Contact'] = MYCRAVINGS_ADMIN_CONTACT_ID;
      }

      $send_result = $provider->send($phone, $params, $filled_text, NULL);
      if (!PEAR::isError($send_result)) {
        $result = TRUE;
      }
    }
  } catch (Exception $e) {
    // Re-throw non-routing exceptions
    if (!$e->getMessage() == "ERR: 114, Cannot route message") {
      throw $e;
    }
  }

  return $result;
}

/**
 * Via a YOURLS server request, generate a unique short link from the provided 
 * URL
 *
 * @param @long_url URL to shorten
 * @param @keyword Keyword to append after the YOURLS server URL e.g., http://p2c.com/{keyword}
 *
 * @return TRUE or FALSE
 */
function _powertochangesurvey_create_shortlink($long_url, $keyword) {
  $result = FALSE;

  $yourls_params = array(
    'signature' => MYCRAVINGS_YOURLS_SIGNATURE,
    'action' => 'shorturl',
    'format' => 'json',
    'url' => $long_url,
    'keyword' => $keyword,
  );

  // Send the YOURLS request
  $ch = curl_init(MYCRAVINGS_YOURLS_URL);
  if ($ch !== FALSE) {
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, TRUE);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $yourls_params);

    $reply = curl_exec($ch);
    if ($reply !== FALSE) {
      $yourls_data = json_decode($reply, TRUE);
      if ($yourls_data['status'] == 'success') {
        $result = TRUE;
      }
    }
    curl_close($ch);
  }

  return $result;
}

/**
 * Map the digits of the contact ID to alphabetical chars
 * This will be used as the URL prefix.
 *
 * @param @contact_id Contact ID that will receive the URL
 *
 * @return The URL prefix for this contact ID
 */
function _powertochangesurvey_encode_url_prefix($contact_id) {
  // Use a string of prime length to increase uniqueness
  $dict = 'abcdefghijklmnopqrstuvw';
  $dict_len = strlen($dict);
  $res = '';
  $id = $contact_id;

  while ($id > 0) {
    $digit = (int) ($id % $dict_len);
    $res = $dict[$digit] . $res;
    $id = (int) ($id / $dict_len);
  }

  // Make sure prefix is at least 4 characters long
  $left = 4 - strlen($res);
  while ($left > 0) {
    $res = 'a' . $res;
    $left--;
  }

  return $res;
}
