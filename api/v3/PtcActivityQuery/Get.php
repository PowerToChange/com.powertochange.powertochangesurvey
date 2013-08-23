<?php

// Load the extension configuration file
require_once __DIR__ . '/../../../conf/powertochangesurvey.settings.php';

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

  // Entities to return in the result
  $return_entities = array();
  if (!empty($params['entities'])) {
    $return_entities = explode(',', CRM_Utils_Array::value('entities', $params, ''));
  }

  // Fields to return in the result
  $return_fields = array();
  if (!empty($params['return'])) {
    $return_fields = explode(',', CRM_Utils_Array::value('return', $params, ''));
  }

  // If the activity_type_id is supplied and it is petition or survey, and if 
  // the source_record_id is supplied (the survey/petition ID), then retrieve 
  // all of the referenced fields from the CustomSurveyFields API Get method.
  if (isset($params['activity_type_id'])
      && ($params['activity_type_id'] == MYCRAVINGS_SURVEY_ID
          || $params['activity_type_id'] == MYCRAVINGS_PETITION_ID)
      && isset($params['source_record_id']))
  {
    $get_params = array(
      'version' => 3,
      'activity_type_id' => $params['activity_type_id'],
      'source_record_id' => $params['source_record_id'],
    );
    $get_result = civicrm_api('CustomSurveyFields', 'Get', $get_params);
    if (!$get_result['is_error']) {
      foreach ($get_result['values'] as $field_config) {
        $return_fields[] = $field_config['field_name'];
      }
    }
  }

  // Configuration information for all non-custom tables referenced
  // by the SQL query.
  $tbl_configs = _ptc_get_table_configs();

  // Map to mark whether a table has been added to the JOIN
  // Initialize the map with necessary tables
  $tbl_added = array(
    'civicrm_activity' => TRUE,
    'civicrm_activity_assignment' => TRUE,
    'civicrm_activity_target' => TRUE,
  );

  // Store the filter conditions
  $filter = array();

  // Iterate the parameters and gather the table-specific columns
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

        // Add the table configuration
        $tbl_configs['civicrm_relationship'] = array(
          'cols' => array(),
          'col_aliases' => array(),
          'entity' => 'relationships',
          'join_type' => "LEFT",
          'join_condition' => "civicrm_activity_target.target_contact_id = civicrm_relationship.{$join_col}",
        );
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
          $tbl_added['civicrm_contact'] = TRUE;
        }

        // Add the filter
        $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($matches[1], 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
      }
    } elseif (preg_match('/^custom_(\d+)$/', $field, $matches)) {
      // Retrieve the custom group table info
      list($custom_group_tbl, $custom_group_extends, $custom_field_col) = _ptc_get_custom_table_config($matches[1]);

      if ($custom_group_tbl != NULL
        && $custom_field_col != NULL)
      {
        // Check whether this table has been joined
        if (!isset($tbl_added[$custom_group_tbl])) {
          if ($custom_group_extends == 'Contact') {
            $tbl_configs[$custom_group_tbl] = array(
              'cols' => array(),
              'col_aliases' => array(),
              'entity' => 'target_contact',
              'join_type' => "LEFT",
              'join_condition' => "civicrm_activity_target.target_contact_id = {$custom_group_tbl}.entity_id",
            );
            $tbl_added[$custom_group_tbl] = TRUE;
          } elseif ($custom_group_extends == 'Activity') {
            $tbl_configs[$custom_group_tbl] = array(
              'cols' => array(),
              'col_aliases' => array(),
              'entity' => 'activity',
              'join_type' => "LEFT",
              'join_condition' => "civicrm_activity.id = {$custom_group_tbl}.entity_id",
            );
            $tbl_added[$custom_group_tbl] = TRUE;
          } else {
            throw new API_Exception('Unable to determine the table to join with custom group, ' . $custom_group_tbl);
          }
        }

        // Add the filter
        $filter[] = "${custom_group_tbl}.{$custom_field_col} = '" . CRM_Utils_Type::escape($value, 'String') . "'";
      }
    } elseif (array_search($field, $tbl_configs['civicrm_activity']['cols']) !== FALSE) {
      // Add the filter
      $filter[] = "civicrm_activity." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
    } elseif (array_search($field, $tbl_configs['civicrm_activity_assignment']['cols']) !== FALSE) {
      // Add the filter
      $filter[] = "civicrm_activity_assignment." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
    } elseif (array_search($field, $tbl_configs['civicrm_activity_target']['cols']) !== FALSE) {
      // Add the filter
      $filter[] = "civicrm_activity_target." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
    }
  }

  // Add the custom fields to the SELECT clause
  foreach ($return_fields as $field) {
    if (preg_match('/^custom_(\d+)$/', $field, $matches)) {
      // Retrieve the custom group table info
      list($custom_group_tbl, $custom_group_extends, $custom_field_col) = _ptc_get_custom_table_config($matches[1]);

      if ($custom_group_tbl != NULL
        && $custom_field_col != NULL)
      {
        // Check whether this table has been joined
        if (!isset($tbl_added[$custom_group_tbl])) {
          if ($custom_group_extends == 'Contact') {
            $tbl_configs[$custom_group_tbl] = array(
              'cols' => array(),
              'col_aliases' => array(),
              'entity' => 'target_contact',
              'join_type' => "LEFT",
              'join_condition' => "civicrm_activity_target.target_contact_id = {$custom_group_tbl}.entity_id",
            );
            $tbl_added[$custom_group_tbl] = TRUE;
          } elseif ($custom_group_extends == 'Activity') {
            $tbl_configs[$custom_group_tbl] = array(
              'cols' => array(),
              'col_aliases' => array(),
              'entity' => 'activity',
              'join_type' => "LEFT",
              'join_condition' => "civicrm_activity.id = {$custom_group_tbl}.entity_id",
            );
            $tbl_added[$custom_group_tbl] = TRUE;
          } else {
            throw new API_Exception('Unable to determine the table to join with custom group, ' . $custom_group_tbl);
          }
        }

        // Add the SELECT fields
        if (array_search($custom_field_col, $tbl_configs[$custom_group_tbl]['cols']) === FALSE) {
          $tbl_configs[$custom_group_tbl]['cols'][] = $custom_field_col;
          $tbl_configs[$custom_group_tbl]['col_aliases'][$custom_field_col] = $field;
        }
      }
    }
  }

  // Map a field to its entity. This is used to generate sub-lists when 
  // iterating the result set and generating the API response.
  $field_entity_map = array();

  // Generate the SELECT clause
  $sql = "";
  foreach ($tbl_added as $tbl_name => $status) {
    $tbl_config = $tbl_configs[$tbl_name];
    $col_aliases = $tbl_config['col_aliases'];

    foreach ($tbl_config['cols'] as $col) {
      if ($sql == "") {
        $sql = "SELECT {$tbl_name}.{$col}";
      } else{
        $sql .= ", {$tbl_name}.{$col}";
      }

      // Map this field to its entity
      $field_entity_map[$col] = $tbl_config['entity'];

      // Add the alias
      if (isset($col_aliases[$col])) {
        $alias = $col_aliases[$col];
        $sql .= " AS " . $alias;
        $field_entity_map[$alias] = $tbl_config['entity'];
      }
    }
  }

  // Generate the FROM clause
  $sql .= " FROM civicrm_activity";
  foreach ($tbl_added as $tbl_name => $status) {
    if ($tbl_name != 'civicrm_activity') {
      $tbl_config = $tbl_configs[$tbl_name];
      $sql .= " " . $tbl_config['join_type'] . " JOIN " . $tbl_name . " ON " . $tbl_config['join_condition'];
    }
  }

  // Generate the WHERE clause
  if (count($filter) > 0) {
    $sql .= " WHERE ";
    $sql .= implode(" AND ", $filter);
  }

  // Generate LIMIT
  $limit = CRM_Utils_Array::value('rowCount', $params, 25);
  $sql .= " LIMIT " . $limit;

  // Generate OFFSET
  $offset = CRM_Utils_Array::value('offset', $params, 0);
  $sql .= " OFFSET " . $offset;

  // Print debug information
  if (isset($params['debug']) && $params['debug']) {
    print $sql;
  }

  // Execute the query
  $dao = CRM_Core_DAO::executeQuery($sql);
  while ($dao->fetch()) {
    $record = array();

    // Attach the row values to the proper entity; either the top-level 
    // activity entity or one of the sub-entities.
    $row = $dao->toArray();

    // Iterate the row and insert each value into the correct entity
    foreach ($row as $field => $value) {
      $field_entity = $field_entity_map[$field];
      switch ($field_entity) {
        case 'activity':
          $record[$field] = $value;
          break;

        case 'target_contact':
          $record['target_contact'][$field] = $value;
          break;

        default:
          break;
      }
    }

    // Attach the final tuple to the API result set
    $return_values[] = $record;
  }

  return civicrm_api3_create_success($return_values, $params, 'PtcActivityQuery', 'Get');
}

/*
 * Utility functions
 */

/**
 * Get configuration information for each table referenced by the Get SQL query
 *
 * @return array
 */
function _ptc_get_table_configs() {
  $tbl_configs = array();

  // civicrm_activity
  $tbl_configs['civicrm_activity'] = array(
    'cols' => array(
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
    ),
    'col_aliases' => array(),
    'entity' => 'activity',
    'join_type' => NULL,
    'join_condition' => NULL,
  );

  // civicrm_activity_assignment
  $tbl_configs['civicrm_activity_assignment'] = array(
    'cols' => array(
      'assignee_contact_id',
    ),
    'col_aliases' => array(),
    'entity' => 'activity',
    'join_type' => "LEFT",
    'join_condition' => "civicrm_activity.id = civicrm_activity_assignment.activity_id",
  );

  // civicrm_activity_target
  $tbl_configs['civicrm_activity_target'] = array(
    'cols' => array(
      'target_contact_id',
    ),
    'col_aliases' => array(),
    'entity' => 'activity',
    'join_type' => "LEFT",
    'join_condition' => "civicrm_activity.id = civicrm_activity_target.activity_id",
  );

  // civicrm_contact
  $tbl_configs['civicrm_contact'] = array(
    'cols' => array(),
    'col_aliases' => array(),
    'entity' => 'target_contact',
    'join_type' => "LEFT",
    'join_condition' => "civicrm_activity_target.target_contact_id = civicrm_contact.id",
  );

  return $tbl_configs;
}

/**
 * Retrieve the custom group table information associated with the specified
 * custom field ID. This information is used to construct the SELECT, JOIN and 
 * WHERE clauses in the Get SQL query.
 *
 * @param $custom_field_id
 *
 * @return list($custom_group_tbl, $custom_group_extends, $custom_field_col)
 */
function _ptc_get_custom_table_config($custom_field_id) {
  $custom_group_tbl = NULL;
  $custom_group_id = NULL;
  $custom_group_extends = NULL;
  $custom_field_col = NULL;

  // Retrieve the custom field information
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

  return array($custom_group_tbl, $custom_group_extends, $custom_field_col);
}
