<?php

require_once 'powertochangesurvey.civix.php';

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
define("P2C_STATE_FOLLOWUP_PRIORITY", 1);
define("P2C_STATE_COMPLETE", 2);

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
  $_powertochangesurvey_entity_data[$entity_id][$key] = $value;
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
 * Get a CustomField column value given the CustomField ID and column name
 *
 * @param $id CustomField ID
 * @param $column CustomField column name
 *
 * @return CustomField name if the ID is present, NULL otherwise
 */
function _powertochangesurvey_get_customfield_column_value($id, $column) {
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

  $value = NULL;
  if (isset($_powertochangesurvey_customfield_map[$id])) {
    $value = $_powertochangesurvey_customfield_map[$id][$column];
  }
  return $value;
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
  if (_powertochangesurvey_get_entity_value($entity_id, 'p2c_state') === P2C_STATE_COMPLETE) {
    return;
  }

  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority') === NULL) {
    _powertochangesurvey_set_entity_value($entity_id, 'p2c_state', P2C_STATE_FOLLOWUP_PRIORITY);
    _powertochangesurvey_calc_followup_priority($group_id, $entity_id, $params);
  }

  // Now have the follow-up priority - ready to proceed with the next steps
  // NOTE: The previous call to _powertochangesurvey_calc_followup_priority may 
  // have assigned the follow-up priority.
  if (_powertochangesurvey_get_entity_value($entity_id, 'mycravings_followup_priority') !== NULL) {
  }
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
    $field_name = _powertochangesurvey_get_customfield_column_value(
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
}
