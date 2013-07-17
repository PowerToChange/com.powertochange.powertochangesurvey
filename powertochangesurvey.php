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
  return _powertochangesurvey_civix_civicrm_enable();
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
    $priority_id = _powertochangesurvey_get_customfield_id('mycravings_followup_priority');
    $state_id = _powertochangesurvey_get_customfield_id('mycravings_processing_state');

    $updateParams = array(
      'entityID' => $entity_id,
      'custom_' . $priority_id => $priority,
      'custom_' . $state_id => $state,
    );
    CRM_Core_BAO_CustomValueTable::setValues($updateParams);
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

  // Store the CustomGroup ID-name map to avoid multiple lookups.
  if ($_powertochangesurvey_customgroup_map === NULL) {
    $result = civicrm_api('CustomGroup', 'Get', array('version' => 3));
    if (!$result['is_error'] && $result['count'] > 0) {
      foreach ($result['values'] as $value) {
        $_powertochangesurvey_customgroup_map[$value['id']] = $value['name'];
      }
    }
  }

  $value = NULL;
  if (isset($_powertochangesurvey_customgroup_map[$id])) {
    $value = $_powertochangesurvey_customgroup_map[$id];
  }
  return $value;
}

/**
 * Load CustomField data into a global array
 */
function _powertochangesurvey_load_customfield_data() {
  global $_powertochangesurvey_customfield_map;

  // Store the CustomField ID-name map to avoid multiple lookups.
  if ($_powertochangesurvey_customfield_map === NULL) {
    $result = civicrm_api('CustomField', 'Get', array('version' => 3));
    if (!$result['is_error'] && $result['count'] > 0) {
      foreach ($result['values'] as $value) {
        $_powertochangesurvey_customfield_map[$value['id']] = array(
          'name' => $value['name'],
          'option_group_id' => isset($value['option_group_id']) ? $value['option_group_id'] : NULL,
        );
      }
    }
  }
}

/**
 * Get a CustomField column value given the CustomField ID and column name
 *
 * @param $id CustomField ID
 * @param $column CustomField column name
 *
 * @return CustomField name if the ID is present, NULL otherwise
 */
function _powertochangesurvey_get_customfield_column_value_by_id($id, $column) {
  global $_powertochangesurvey_customfield_map;

  // Load the data
  _powertochangesurvey_load_customfield_data();

  // Fetch the value
  $value = NULL;
  if (isset($_powertochangesurvey_customfield_map[$id])) {
    $value = $_powertochangesurvey_customfield_map[$id][$column];
  }
  return $value;
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

  // Load the data
  _powertochangesurvey_load_customfield_data();

  // Fetch the value
  $value = NULL;
  if ($_powertochangesurvey_customfield_map !== NULL) {
    foreach ($_powertochangesurvey_customfield_map as $id => $field_data) {
      if ($field_data['name'] == $name) {
        $value = $id;
        break;
      }
    }
  }
  return $value;
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
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_state') === MYCRAVINGS_STATE_COMPLETE) {
    return;
  }

  // Calculate follow-up priority
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority') === NULL) {
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
  if ($group_name != 'MyCravings_Common') {
    return;
  }

  // Store the CustomField value - perform OptionGroup lookup if necessary
  foreach ($field_values as $field_value) {
    $field_name = _powertochangesurvey_get_customfield_column_value_by_id(
      $field_value['custom_field_id'],
      'name'
    );
    _powertochangesurvey_set_entity_value($entity_id, $field_name, $field_value['value']);
  }

  // Retrieve the necessary fields for the follow-up priority calculation
  $magazine = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_magazine');
  $journey = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_journey');
  $gauge = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_gauge');

  // Translate the field values into a more usable format
  if ($magazine === NULL || $magazine === 'magazine-no') {
    $magazine = FALSE;
  } else {
    $magazine = TRUE;
  }

  if ($journey === NULL || $journey === 'journey-nothing') {
    $journey = FALSE;
  } else {
    $journey = TRUE;
  }

  if ($gauge === NULL || $gauge === 'gauge-1') {
    $gauge = 1;
  } else {
    if (preg_match('/^gauge-(\d+)$/', $gauge, $matches)) {
      $gauge = $matches[1];
    }
  }

  // Get the current followup priority - default to 'Mild'
  $priority = _powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority');
  if ($priority === NULL) {
    $priority = 'Mild';
  }

  // First check for non-interest
  if (!$magazine && $gauge == 1 && !$journey) {
    $priority = 'No';
  } elseif ($magazine && $gauge >= 4) {
    $priority = 'Hot';
  } elseif (!$magazine && $gauge >= 4 && $journey) {
    $priority = 'Hot';
  } elseif ($magazine && $gauge == 3) {
    $priority = 'Medium';
  } elseif (!$magazine && $gauge == 2 && $journey) {
    $priority = 'Medium';
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
        _powertochangesurvey_set_entity_value($entity_id, 'target_contact_first_name', $contact_data['first_name']);
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
    $do_not_sms = '1';
    $do_not_email = '1';

    $target_phone = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_phone');
    if ($target_phone !== NULL) {
      // Phone is valid
      $do_not_sms = '0';
    }

    $target_email = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_email');
    if ($target_email !== NULL) {
      // Email is valid
      $do_not_email = '0';
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

  if ($priority == 'No') {
    $do_not_sms = FALSE;
    $do_not_email = FALSE;

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

  // Replace the message template tokens
  $text_token = CRM_Utils_Token::getTokens($msg_template->msg_text);
  $html_token = CRM_Utils_Token::getTokens($msg_template->msg_html);
  $values = array('display_name' => $display_name);
  $filled_text = CRM_Utils_Token::replaceContactTokens($msg_template->msg_text, $values, FALSE, $text_token);
  $filled_html = CRM_Utils_Token::replaceContactTokens($msg_template->msg_html, $values, TRUE, $html_token);

  // Send the email
  $mail_params = array(
    'groupName' => 'Activity Email Sender',
    'from' => MYCRAVINGS_EMAIL_FROM_ADDRESS,
    'toName' => $display_name,
    'toEmail' => $email,
    'subject' => $msg_template->msg_subject,
    'cc' => "",
    'bcc' => "",
    'text' => $filled_text,
    'html' => $filled_html,
    'attachments' => array(),
  );

  if (CRM_Utils_Mail::send($mail_params)) {
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
  $first_name = _powertochangesurvey_get_entity_value($entity_id, 'target_contact_first_name');

  // Attempt to generate a YOURLS short link
  $encoded_contact = _powertochangesurvey_encode_url_prefix($contact_id);
  $url = $encoded_contact . MYCRAVINGS_SMS_MESSAGE_SHORT_URL_SUFFIX;
  $url_result = _powertochangesurvey_create_shortlink(MYCRAVINGS_SMS_MESSAGE_LONG_URL, $url);
  if ($url_result == FALSE) {
    // Failed to generate a shortened URL - use the full version
    $url = MYCRAVINGS_SMS_MESSAGE_LONG_URL;
  }

  // Send the SMS message - replace Contact field tokens
  $message_token = CRM_Utils_Token::getTokens($msg_template->msg_text);
  $values = array('first_name' => $first_name);
  $filled_text = CRM_Utils_Token::replaceContactTokens($msg_template->msg_text, $values, FALSE, $message_token);

  // Replace our custom token, MYCRAVINGS_URL_TOKEN, with the $url value
  $filled_text = preg_replace(MYCRAVINGS_URL_TOKEN_EXP, $url, $filled_text);

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

      if ($provider->send($phone, $params, $filled_text, NULL)) {
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
 * @param @short_url Shortened URL
 *
 * @return TRUE or FALSE
 */
function _powertochangesurvey_create_shortlink($long_url, $short_url) {
  $result = FALSE;

  $yourls_params = array(
    'signature' => MYCRAVINGS_YOURLS_SIGNATURE,
    'action' => 'shorturl',
    'format' => 'json',
    'url' => $long_url,
    'keyword' => $short_url,
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
