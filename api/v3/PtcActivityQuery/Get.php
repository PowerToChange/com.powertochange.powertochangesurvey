<?php

/**
 * PtcActivityQuery.Get API specification (optional)
 * This is used for documentation and validation.
 *
 * @param array $spec description of fields supported by this API call
 * @return void
 * @see http://wiki.civicrm.org/confluence/display/CRM/API+Architecture+Standards
 */
function _civicrm_api3_ptc_activity_query_get_spec(&$spec) {
  $params['target_contact_<COLUMN>']['title'] = 'Column attached to the target Contact entity associated with this Activity';
  $params['target_contact_relationship_<COLUMN>']['title'] = 'Column attached to the target Contact Relationship entity associated with this Activity';
  $params['custom_<ID>']['title'] = 'Custom field where <ID> is the CustomField ID';
}

/*
re: 2 How can I filter activities by specific contact attribute (i.e. Give me all activities with target contact that is Male)
re 3. How can I filter activities by specific custom value attribute (i.e. Give me all activities that has a custom value "Year in School" (custom_57) of 1997)
re 4. How can I filter activities by specific contact that is of a specific campus (i.e. Give me all activities with target contact from McGill University)
 */

/**
 * PtcActivityQuery.Get API
 *
 * @param array $params
 * @return array API result descriptor
 * @see civicrm_api3_create_success
 * @see civicrm_api3_create_error
 * @throws API_Exception
 */
function civicrm_api3_ptc_activity_query_get($params) {
  $return_values = array();

  // Base Activity fields to include in every result set
  $activity_cols = array(
    'id',
    'campaign_id',
    'source_record_id',
    'activity_type_id',
    'status_id',
    'priority_id',
    'engagement_level',
    'activity_date_time',
    'details',
    'source_contact_id',
  );
  $assignee_contact_cols = array(
    'assignee_contact_id',
  );
  $target_contact_cols = array(
    'target_contact_id',
  );

  // The aggregated SQL statement
  $sql = "SELECT";
  $sql .= " civicrm_activity." . implode(", civicrm_activity." , $activity_cols);
  $sql .= ", civicrm_activity_assignment." . implode(", civicrm_activity_assignment." , $assignee_contact_cols);
  $sql .= ", civicrm_activity_target." . implode(", civicrm_activity_target." , $target_contact_cols);
  $sql .= " FROM civicrm_activity";
  $sql .= " LEFT JOIN civicrm_activity_assignment ON civicrm_activity.id = civicrm_activity_assignment.activity_id";
  $sql .= " LEFT JOIN civicrm_activity_target ON civicrm_activity.id = civicrm_activity_target.activity_id";

  // Iterate the parameters and gather the table-specific columns
  $filter = array();
  $tbl_added = array();
  foreach ($params as $field => $value) {
    // Note: Order of if statements matters - from most to least specific
    // Cannot use other table delimiters - auto-replaced by CiviCRM
    if (preg_match('/^target_contact_relationship_(.+)$/', $field, $matches)) {
      // Add the JOIN to the Relationship entity
      if (!isset($tbl_added['civicrm_relationship'])) {
        // Determine the side of the Relationship to join on - the opposite of 
        // the param specified.
        $join_col = NULL;
        $filter_col = NULL;
        if (isset($params['target_contact_relationship_contact_id_a'])) {
          $join_col = 'contact_id_b';
          $filter_col = 'contact_id_a';
        } elseif (isset($params['target_contact_relationship_contact_id_b'])) {
          $join_col = 'contact_id_a';
          $filter_col = 'contact_id_b';
        } else {
          throw new API_Exception('You must specify contact_id_a or contact_id_b as a filter on target_contact_relationship. The opposite side of the relationship is used in the join to the target contact associated with the Activity.');
        }

        // Generate the SQL
        $sql .= " LEFT JOIN civicrm_relationship ON civicrm_activity_target.target_contact_id = civicrm_relationship.{$join_col}";
        $tbl_added['civicrm_relationship'] = TRUE;
      }

      // Add the filter
      $filter[] = "civicrm_relationship." . CRM_Utils_Type::escape($matches[1], 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
    } elseif (preg_match('/^target_contact_(.+)$/', $field, $matches)) {
      // Target contact ID filter applied to the civicrm_activity_target table 
      // and not the civicrm_contact table
      if ($matches[1] == 'id') {
        // Add the filter
        $filter[] = "civicrm_activity_target.target_contact_id = " . CRM_Utils_Type::escape($value, 'Integer');
      } else {
        // Add the JOIN to the Contact entity
        if (!isset($tbl_added['civicrm_contact'])) {
          $sql .= " LEFT JOIN civicrm_contact ON civicrm_activity_target.target_contact_id = civicrm_contact.id";
          $tbl_added['civicrm_contact'] = TRUE;
        }

        // Add the filter
        $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($matches[1], 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
      }
    } elseif (preg_match('/^custom_(\d+)$/', $field, $matches)) {
      // Need to fetch the CustomGroup associated with this CustomField in 
      // order to obtain the CustomGroup table that stores the data and can be 
      // used in the JOIN and filter(s)
      $custom_group_tbl = NULL;
      $custom_group_id = NULL;
      $custom_group_extends = NULL;
      $custom_field_id = $matches[1];
      $custom_field_col = NULL;
      $get_params = array(
        'version' => 3,
        'id' => $custom_field_id,
      );
      $get_result = civicrm_api('CustomField', 'Get', $get_params);
      if (!$get_result['is_error'] && $get_result['count'] == 1) {
        $custom_group_id = $get_result['values'][$custom_field_id]['custom_group_id'];
        $custom_field_col = $get_result['values'][$custom_field_id]['column_name'];

        // Get the CustomGroup table name
        $get_params = array(
          'version' => 3,
          'id' => $custom_group_id,
        );
        $get_result = civicrm_api('CustomGroup', 'Get', $get_params);
        if (!$get_result['is_error'] && $get_result['count'] == 1) {
          $custom_group_tbl = $get_result['values'][$custom_group_id]['table_name'];
          if (preg_match('/^Contact/', $get_result['values'][$custom_group_id]['extends'])
           || preg_match('/^Individual/', $get_result['values'][$custom_group_id]['extends'])
           || preg_match('/^Organization/', $get_result['values'][$custom_group_id]['extends']))
          {
            $custom_group_extends = 'Contact';
          } elseif (preg_match('/^Activity/', $get_result['values'][$custom_group_id]['extends'])) {
            $custom_group_extends = 'Activity';
          }
        }
      }

      if ($custom_group_tbl !== NULL
        && $custom_field_col !== NULL)
      {
        // Check whether this table has been joined
        if (!isset($tbl_added[$custom_group_tbl])) {
          if ($custom_group_extends == 'Contact') {
            $sql .= " LEFT JOIN {$custom_group_tbl} ON civicrm_activity_target.target_contact_id = {$custom_group_tbl}.entity_id";
          } elseif ($custom_group_extends == 'Activity') {
            $sql .= " LEFT JOIN {$custom_group_tbl} ON civicrm_activity.id = {$custom_group_tbl}.entity_id";
          } else {
            throw new API_Exception('Unable to determine the table to join with custom group, ' . $custom_group_tbl);
          }
          $tbl_added[$custom_group_tbl] = TRUE;
        }

        // Add the filter
        $filter[] = "${custom_group_tbl}.{$custom_field_col} = '" . CRM_Utils_Type::escape($value, 'String') . "'";
      }
    } elseif (array_search($field, $activity_cols) !== FALSE) {
      // Add the filter
      $filter[] = "civicrm_activity." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
    } elseif (array_search($field, $assignee_contact_cols) !== FALSE) {
      // Add the filter
      $filter[] = "civicrm_activity_assignment." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
    }
  }

  // Append the WHERE clause
  if (count($filter) > 0) {
    $sql .= " WHERE ";
    $sql .= implode(" AND ", $filter);
  }

  // Print debug information
  if (isset($params['debug']) && $params['debug']) {
    print $sql;
  }

  // Execute the query
  $dao = CRM_Core_DAO::executeQuery($sql);
  while ($dao->fetch()) {
    $return_values[] = $dao->toArray();
  }

  return civicrm_api3_create_success($return_values, $params, 'PtcActivityQuery', 'Get');
}
