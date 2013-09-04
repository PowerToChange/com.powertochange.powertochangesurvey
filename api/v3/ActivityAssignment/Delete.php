<?php

/**
 * ActivityAssignment.Delete API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_activity_assignment_delete_spec(&$spec) {
  $spec['id']['api.required'] = 0;
  $spec['id']['title'] = 'Unique ID of the activity-contact relationship';
  $spec['activity_id']['title'] = 'Activity ID';
  $spec['assignee_contact_id']['title'] = 'Contact ID assigned to the activity';
}

/**
 * ActivityAssignment.Delete API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_activity_assignment_delete($params) {
  // Values to return to the caller
  $return_values = array();

  // Load values from the params
  $id = CRM_Utils_Array::value('id', $params, NULL);
  $activity_id = CRM_Utils_Array::value('activity_id', $params, NULL);
  $assignee_contact_id = CRM_Utils_Array::value('assignee_contact_id', $params, NULL);
  $debug = CRM_Utils_Array::value('debug', $params, 0);

  // Generate the SQL
  $filter = array();
  if ($id != NULL && $id > 0) {
    $filter[] = "id = " . CRM_Utils_Type::escape($id, 'Integer');
  }

  if ($activity_id != NULL && $activity_id > 0) {
    $filter[] = "activity_id = " . CRM_Utils_Type::escape($activity_id, 'Integer');
  }

  if ($assignee_contact_id != NULL && $assignee_contact_id > 0) {
    $filter[] = "assignee_contact_id = " . CRM_Utils_Type::escape($assignee_contact_id, 'Integer');
  }

  // Throw error if no IDs are provided
  $dao = NULL;
  if (!empty($filter)) {
    $sql = "DELETE FROM civicrm_activity_assignment WHERE ";
    $sql .= implode(" AND ", $filter);
    if ($debug) {
      print "{$sql}\n";
    }

    $dao = CRM_Core_DAO::executeQuery($sql);
  } else {
    throw new API_Exception('You must supply at least one of: id, activity_id, assignee_contact_id');
  }

  return civicrm_api3_create_success($return_values, $params, 'ActivityAssignment', 'Delete', $dao);
}
