<?php

// Load the extension configuration file
require_once __DIR__ . '/../../../conf/powertochangesurvey.settings.php';

// Globals
$civicrm_api3_ptc_activity_query_custom_field_config = array();

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
  // The API result that will be returned to the caller
  $return_values = array();

  // Extra return values to provide the caller
  $extra_return_values = array();

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

  // Activity-related tables for use by the activities entities sub-query
  $activities_tbls = $tbl_added;

  // Entities to return in the result
  $return_entities = array();
  if (!empty($params['entities'])) {
    $return_entities = explode(',', CRM_Utils_Array::value('entities', $params, ''));
  }

  // Add the requested entity tables to the result set
  foreach ($return_entities as $entity) {
    switch ($entity) {
      case 'target_contact':
        $tbl_added['civicrm_contact'] = TRUE;
        $tbl_added['civicrm_phone'] = TRUE;
        $tbl_added['civicrm_email'] = TRUE;
        break;

      default:
        break;
    }
  }

  // Fields to return in the result
  $return_fields = array();
  if (!empty($params['return'])) {
    $return_fields = explode(',', CRM_Utils_Array::value('return', $params, ''));
  }

  // If the activity_type_id is supplied and it is petition or survey, then
  // get all of the referenced fields from the CustomSurveyFields API Get method.
  if (isset($params['activity_type_id'])
      && ($params['activity_type_id'] == MYCRAVINGS_SURVEY_ID
          || $params['activity_type_id'] == MYCRAVINGS_PETITION_ID))
  {
    $get_params = array(
      'version' => 3,
      'activity_type_id' => $params['activity_type_id'],
    );

    // If the source_record_id is available then be more specific
    if (isset($params['source_record_id'])) {
      $get_params['source_record_id'] = $params['source_record_id'];
    }

    $get_result = civicrm_api('CustomSurveyFields', 'Get', $get_params);
    if (!$get_result['is_error']) {
      foreach ($get_result['values'] as $field_config) {
        $return_fields[] = $field_config['field_name'];
      }
    }
  }

  // Store unique fields
  $return_fields = array_unique($return_fields);

  // Map of the activity type ID to name
  $activity_type_map = CRM_Core_OptionGroup::values('activity_type');

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
        }

        if ($join_col != NULL && $filter_col != NULL) {
          // Add the table configuration
          $tbl_configs['civicrm_relationship'] = array(
            'cols' => array(),
            'col_aliases' => array(),
            'entity' => 'relationships',
            'join_type' => "LEFT",
            'join_condition' => "civicrm_activity_target.target_contact_id = civicrm_relationship.{$join_col}",
            'where_condition' => NULL,
          );
          $tbl_added['civicrm_relationship'] = TRUE;
        }
      }

      // If the caller did not supply contact A or B, then the table
      // was not joined to the query and it is not possible to apply the
      // filter. In such a case, it is possible that the caller desires to
      // pass the filter to the relationships entity sub-query.
      if (isset($tbl_added['civicrm_relationship'])) {
        $filter_field = $matches[1];
        if ($field == 'target_contact_relationship_type_id') {
          $filter_field = 'relationship_type_id';
        }

        if (strtoupper($value) == 'NULL') {
          $filter[] = "civicrm_relationship." . CRM_Utils_Type::escape($filter_field, 'String') . " IS NULL";
        } else {
          $filter[] = "civicrm_relationship." . CRM_Utils_Type::escape($filter_field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
        }
      }
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
        if (strtoupper($value) == 'NULL') {
          $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($matches[1], 'String') . " IS NULL";
        } elseif (preg_match('/^target_contact_(.+)_between$/', $field, $m)) {
          $v = explode('-', $value);
          $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($m[1], 'String') . " > '" . CRM_Utils_Type::escape($v[0], 'String') . "'";
          $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($m[1], 'String') . " < '" . CRM_Utils_Type::escape($v[1], 'String') . "'";
        } elseif (preg_match('/^target_contact_(.+)_low$/', $field, $m)) {
          $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($m[1], 'String') . " > '" . CRM_Utils_Type::escape($value, 'String') . "'";
        } elseif (preg_match('/^target_contact_(.+)_high$/', $field, $m)) {
          $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($m[1], 'String') . " < '" . CRM_Utils_Type::escape($value, 'String') . "'";
        } else {
          $filter[] = "civicrm_contact." . CRM_Utils_Type::escape($matches[1], 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
        }
      }
    } elseif (preg_match('/^custom_(\d+)$/', $field, $matches)) {
      // Retrieve the custom group table info
      $field_config = _ptc_get_custom_table_config($matches[1]);
      $custom_group_tbl = $field_config['group_table'];
      $custom_group_extends = $field_config['group_extends'];
      $custom_field_col = $field_config['field_column'];
      $custom_field_html_type = $field_config['field_html_type'];

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
              'where_condition' => NULL,
            );
            $tbl_added[$custom_group_tbl] = TRUE;
          } elseif ($custom_group_extends == 'Activity') {
            $tbl_configs[$custom_group_tbl] = array(
              'cols' => array(),
              'col_aliases' => array(),
              'entity' => 'activity',
              'join_type' => "LEFT",
              'join_condition' => "civicrm_activity.id = {$custom_group_tbl}.entity_id",
              'where_condition' => NULL,
            );
            $tbl_added[$custom_group_tbl] = TRUE;
            $activities_tbls[$custom_group_tbl] = TRUE;
          } else {
            throw new API_Exception('Unable to determine the table to join with custom group, ' . $custom_group_tbl);
          }
        }

        // Add the filter
        if (strtoupper($value) == 'NULL') {
          $filter[] = "${custom_group_tbl}.{$custom_field_col} IS NULL";
        } else {
          $field_name = "${custom_group_tbl}.${custom_field_col}";
          if ($custom_field_html_type == 'CheckBox') {
            // The CiviCRM multi-value separator
            $sep = CRM_Core_DAO::VALUE_SEPARATOR;

            // Split the value on a comma to support multi-value filter values
            // In CiviCRM terms, the API only uses search type "ANY"
            $field_values = explode(',', $value);
            $field_filter = array();
            foreach ($field_values as $field_value) {
              $field_filter[] = "${field_name} LIKE '%{$sep}" . CRM_Utils_Type::escape($field_value, 'String') . "{$sep}%'";
            }

            // Join all the multi-value LIKE clauses into a final nested 
            // sub-clause
            $filter[] = "(" . implode(" AND ", $field_filter) . ")";
          } else {
            $filter[] = "${field_name} = '" . CRM_Utils_Type::escape($value, 'String') . "'";
          }
        }
      }
    } elseif (array_search($field, $tbl_configs['civicrm_activity']['cols']) !== FALSE) {
      // Add the filter
      if ($field == 'id') {
        // It is possible to supply multiple civicrm_activity IDs as the filter
        $filter_ids = array();
        foreach (explode(',', $value) as $activity_id) {
          $filter_ids[] = CRM_Utils_Type::escape($activity_id, 'Integer');
        }

        if (!empty($filter_ids)) {
          $filter[] = "civicrm_activity.id IN (" . implode(',', $filter_ids) . ")";
        }
      } else {
        if (strtoupper($value) == 'NULL') {
          $filter[] = "civicrm_activity." . CRM_Utils_Type::escape($field, 'String') . " IS NULL";
        } else {
          $filter[] = "civicrm_activity." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
        }
      }
    } elseif (array_search($field, $tbl_configs['civicrm_activity_assignment']['cols']) !== FALSE) {
      // Add the filter
      if (strtoupper($value) == 'NULL') {
        $filter[] = "civicrm_activity_assignment." . CRM_Utils_Type::escape($field, 'String') . " IS NULL";
      } else {
        $filter[] = "civicrm_activity_assignment." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
      }
    } elseif (array_search($field, $tbl_configs['civicrm_activity_target']['cols']) !== FALSE) {
      // Add the filter
      if (strtoupper($value) == 'NULL') {
        $filter[] = "civicrm_activity_target." . CRM_Utils_Type::escape($field, 'String') . " IS NULL";
      } else {
        $filter[] = "civicrm_activity_target." . CRM_Utils_Type::escape($field, 'String') . " = '" . CRM_Utils_Type::escape($value, 'String') . "'";
      }
    }
  }

  // Add the custom fields to the SELECT clause
  foreach ($return_fields as $field) {
    if (preg_match('/^custom_(\d+)$/', $field, $matches)) {
      // Retrieve the custom group table info
      $field_config = _ptc_get_custom_table_config($matches[1]);
      $custom_group_tbl = $field_config['group_table'];
      $custom_group_extends = $field_config['group_extends'];
      $custom_field_col = $field_config['field_column'];

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
              'where_condition' => NULL,
            );
            $tbl_added[$custom_group_tbl] = TRUE;
          } elseif ($custom_group_extends == 'Activity') {
            $tbl_configs[$custom_group_tbl] = array(
              'cols' => array(),
              'col_aliases' => array(),
              'entity' => 'activity',
              'join_type' => "LEFT",
              'join_condition' => "civicrm_activity.id = {$custom_group_tbl}.entity_id",
              'where_condition' => NULL,
            );
            $tbl_added[$custom_group_tbl] = TRUE;
            $activities_tbls[$custom_group_tbl] = TRUE;
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
  $sql = "SELECT";

  // Parameter to denote whether to return the total number of available
  // rows without a LIMIT clause. This is useful when the client wants
  // to paginate results to the end user.
  $get_available_count = CRM_Utils_Array::value('availableRowCount', $params, 0);
  if ($get_available_count == 1) {
    $sql .= " SQL_CALC_FOUND_ROWS";
  }

  $col_count = 0;
  foreach ($tbl_added as $tbl_name => $status) {
    $tbl_config = $tbl_configs[$tbl_name];
    $col_aliases = $tbl_config['col_aliases'];

    foreach ($tbl_config['cols'] as $col) {
      if ($col_count > 0) {
        $sql .= ",";
      }
      $sql .= " {$tbl_name}.{$col}";
      $col_count++;

      // Map this field to its entity; use the alias if available
      if (isset($col_aliases[$col])) {
        $alias = $col_aliases[$col];
        $sql .= " AS " . $alias;
        $field_entity_map[$alias] = $tbl_config['entity'];
      } else {
        $field_entity_map[$col] = $tbl_config['entity'];
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

  // Check whether any of the added tables require a filter
  foreach ($tbl_added as $tbl_name => $status) {
    if ($tbl_configs[$tbl_name]['where_condition'] !== NULL) {
      $filter[] = $tbl_configs[$tbl_name]['where_condition'];
    }
  }

  if (count($filter) > 0) {
    $sql .= " WHERE ";
    $sql .= implode(" AND ", $filter);
  }

  // Generate SORT
  $sequential = CRM_Utils_Array::value('sequential', $params, FALSE);
  if ($sequential) {
    $sql .= " ORDER BY civicrm_activity.id";
  }

  // Generate LIMIT
  $limit = CRM_Utils_Array::value('rowCount', $params, 25);
  $sql .= " LIMIT " . $limit;

  // Generate OFFSET
  $offset = CRM_Utils_Array::value('offset', $params, 0);
  $sql .= " OFFSET " . $offset;

  // Print debug information
  if (isset($params['debug']) && $params['debug']) {
    print "{$sql}\n";
  }

  // Booleans to mark the retrieval of the other entities
  $get_relationships = array_search('relationships', $return_entities) === FALSE ? FALSE : TRUE;
  $get_notes = array_search('notes', $return_entities) === FALSE ? FALSE : TRUE;
  $get_activities = array_search('activities', $return_entities) === FALSE ? FALSE : TRUE;

  // Execute the query
  $dao = CRM_Core_DAO::executeQuery($sql);

  // Get the available row count - this query must be invoked immediately after
  if ($get_available_count == 1) {
    $sql_count = "SELECT FOUND_ROWS() AS available_count";
    $dao_count = CRM_Core_DAO::executeQuery($sql_count);
    if ($dao_count->fetch()) {
      $available_count = $dao_count->available_count;
      $extra_return_values['availableRowCount'] = $available_count;
    }
  }

  // Iterate the results
  while ($dao->fetch()) {
    $record = array();

    // Attach the row values to the proper entity; either the top-level 
    // activity entity or one of the sub-entities.
    $row = $dao->toArray();

    // Contact values
    $values_contact_entity = array();

    // Iterate the row and insert each value into the correct entity
    foreach ($row as $field => $value) {
      // Process the base activity and target_contact entities
      $field_entity = $field_entity_map[$field];
      switch ($field_entity) {
        case 'activity':
          $record[$field] = $value;
          break;

        case 'target_contact':
          $values_contact_entity[$field] = $value;
          break;

        default:
          break;
      }
    }

    // Custom field - activity_name which is the name corresponding to
    // the activity type ID.
    $activity_type_id = $row['activity_type_id'];
    $activity_name = '';
    if (isset($activity_type_map[$activity_type_id])) {
      $activity_name = $activity_type_map[$activity_type_id];
    }
    $record['activity_name'] = $activity_name;

    // Get the current activity ID which will be used as a filter
    // in the activities sub-entity query.
    $cur_activity_id = $row['id'];

    // Get the target contact ID which will be used as a filter
    // in the target_contact sub-entities.
    $target_contact_id = (int) $row['target_contact_id'];

    // Process the relationships entity
    if ($get_relationships) {
      // Result set for the relationships sub-entity
      $values_sub_entity = array();

      if ($target_contact_id > 0) {
        // Generate the sub-entity SQL
        $sql_sub_entity = "
          SELECT id,
                 contact_id_a,
                 contact_id_b,
                 relationship_type_id
          FROM civicrm_relationship
          WHERE (contact_id_a = {$target_contact_id}
            OR contact_id_b = {$target_contact_id})";

        // Check for the relationship_type_id filter
        if (isset($params['target_contact_relationship_type_id'])) {
          $target_contact_relationship_type_id = $params['target_contact_relationship_type_id'];
          $sql_sub_entity .= " AND civicrm_relationship.relationship_type_id = "
            . CRM_Utils_Type::escape($target_contact_relationship_type_id, 'Integer');
        }

        if (isset($params['debug']) && $params['debug']) {
          print "relationships SQL:\n";
          print "{$sql_sub_entity}\n";
        }

        // Execute the query
        $dao_sub_entity = CRM_Core_DAO::executeQuery($sql_sub_entity);
        while ($dao_sub_entity->fetch()) {
          if ($dao_sub_entity->contact_id_a == $target_contact_id) {
            $values_sub_entity[] = array(
              'id' => $dao_sub_entity->id,
              'contact_id' => $dao_sub_entity->contact_id_b,
              'relationship_type_id' => $dao_sub_entity->relationship_type_id,
            );
          } else {
            $values_sub_entity[] = array(
              'id' => $dao_sub_entity->id,
              'contact_id' => $dao_sub_entity->contact_id_a,
              'relationship_type_id' => $dao_sub_entity->relationship_type_id,
            );
          }
        }
      }

      // Attach the relationship sub-entity to the target
      $values_contact_entity['relationships'] = $values_sub_entity;
    }

    // Process the notes entity
    if ($get_notes) {
      // Result set for the notes sub-entity
      $values_sub_entity = array();

      if ($target_contact_id > 0) {
        // Generate the sub-entity SQL
        $sql_sub_entity = "
          SELECT id,
                 contact_id AS source_contact_id,
                 modified_date,
                 note,
                 privacy,
                 subject
          FROM civicrm_note
          WHERE entity_table = 'civicrm_contact'
            AND entity_id = {$target_contact_id}";

        if (isset($params['debug']) && $params['debug']) {
          print "notes SQL:\n";
          print "{$sql_sub_entity}\n";
        }

        // Execute the query
        $dao_sub_entity = CRM_Core_DAO::executeQuery($sql_sub_entity);
        while ($dao_sub_entity->fetch()) {
          $values_sub_entity[] = $dao_sub_entity->toArray();
        }
      }

      // Attach the notes sub-entity to the target
      $values_contact_entity['notes'] = $values_sub_entity;
    }

    // Process the activities entity
    if ($get_activities) {
      // Result set for the activities sub-entity
      $values_sub_entity = array();

      if ($target_contact_id > 0) {
        // Generate the SELECT clause
        $sql_sub_entity = "";
        foreach ($activities_tbls as $tbl_name => $status) {
          $tbl_config = $tbl_configs[$tbl_name];
          $col_aliases = $tbl_config['col_aliases'];

          foreach ($tbl_config['cols'] as $col) {
            if ($sql_sub_entity == "") {
              $sql_sub_entity = "SELECT {$tbl_name}.{$col}";
            } else{
              $sql_sub_entity .= ", {$tbl_name}.{$col}";
            }

            // Map this field to its entity; use the alias if available
            if (isset($col_aliases[$col])) {
              $sql_sub_entity .= " AS " . $col_aliases[$col];
            }
          }
        }

        // Generate the FROM clause
        $sql_sub_entity .= " FROM civicrm_activity";
        foreach ($activities_tbls as $tbl_name => $status) {
          if ($tbl_name != 'civicrm_activity') {
            $tbl_config = $tbl_configs[$tbl_name];
            $sql_sub_entity .= " " . $tbl_config['join_type'] . " JOIN " . $tbl_name . " ON " . $tbl_config['join_condition'];
          }
        }

        // Generate the WHERE clause
        $sql_sub_entity .= "
          WHERE civicrm_activity_target.target_contact_id = {$target_contact_id}
            AND civicrm_activity.id != {$cur_activity_id}";

        if (isset($params['debug']) && $params['debug']) {
          print "activities SQL:\n";
          print "{$sql_sub_entity}\n";
        }

        // Check whether any of the added tables require a filter
        foreach ($activities_tbls as $tbl_name => $status) {
          if ($tbl_configs[$tbl_name]['where_condition'] !== NULL) {
            $sql_sub_entity .= $tbl_configs[$tbl_name]['where_condition'];
          }
        }

        // Execute the query
        $dao_sub_entity = CRM_Core_DAO::executeQuery($sql_sub_entity);
        while ($dao_sub_entity->fetch()) {
          $row_values = $dao_sub_entity->toArray();

          // Custom field - activity_name
          $activity_type_id = $row_values['activity_type_id'];
          $activity_name = '';
          if (isset($activity_type_map[$activity_type_id])) {
            $activity_name = $activity_type_map[$activity_type_id];
          }
          $row_values['activity_name'] = $activity_name;

          $values_sub_entity[] = $row_values;
        }
      }

      // Attach the notes sub-entity to the target
      $values_contact_entity['activities'] = $values_sub_entity;
    }

    // Attach the contacts entity
    if (!empty($values_contact_entity)) {
      $record['contacts'] = array($values_contact_entity);
    }

    // Attach the final tuple to the API result set
    $return_values[] = $record;
  }

  $null_dao = NULL;
  return civicrm_api3_create_success($return_values, $params, 'PtcActivityQuery', 'Get', $null_dao, $extra_return_values);
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
      'subject',
      'activity_date_time',
    ),
    'col_aliases' => array(),
    'entity' => 'activity',
    'join_type' => NULL,
    'join_condition' => NULL,
    'where_condition' => NULL,
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
    'where_condition' => NULL,
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
    'where_condition' => NULL,
  );

  // civicrm_contact
  $tbl_configs['civicrm_contact'] = array(
    'cols' => array(
      'id',
      'contact_type',
      'contact_sub_type',
      'do_not_email',
      'do_not_phone',
      'do_not_mail',
      'do_not_sms',
      'is_opt_out',
      'display_name',
      'first_name',
      'last_name',
      'gender_id',
      'created_date',
      'modified_date',
    ),
    'col_aliases' => array(
      'id' => 'contact_id',
      'created_date' => 'contact_created_date',
      'modified_date' => 'contact_modified_date',
    ),
    'entity' => 'target_contact',
    'join_type' => "LEFT",
    'join_condition' => "civicrm_activity_target.target_contact_id = civicrm_contact.id",
    'where_condition' => NULL,
  );

  // civicrm_phone
  $tbl_configs['civicrm_phone'] = array(
    'cols' => array(
      'id',
      'phone',
      'phone_type_id',
    ),
    'col_aliases' => array(
      'id' => 'phone_id',
    ),
    'entity' => 'target_contact',
    'join_type' => "LEFT",
    'join_condition' => "civicrm_activity_target.target_contact_id = civicrm_phone.contact_id AND civicrm_phone.is_primary = 1",
    'where_condition' => NULL,
  );

  // civicrm_email
  $tbl_configs['civicrm_email'] = array(
    'cols' => array(
      'id',
      'email',
    ),
    'col_aliases' => array(
      'id' => 'email_id',
    ),
    'entity' => 'target_contact',
    'join_type' => "LEFT",
    'join_condition' => "civicrm_activity_target.target_contact_id = civicrm_email.contact_id AND civicrm_email.is_primary = 1",
    'where_condition' => NULL,
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
  global $civicrm_api3_ptc_activity_query_custom_field_config;
  if (isset($civicrm_api3_ptc_activity_query_custom_field_config[$custom_field_id])) {
    return $civicrm_api3_ptc_activity_query_custom_field_config[$custom_field_id];
  }

  // Custom field configuration
  $field_config = array();

  // Retrieve the custom field information
  $get_params = array(
    'version' => 3,
    'id' => $custom_field_id,
  );
  $get_result = civicrm_api('CustomField', 'Get', $get_params);
  if (!$get_result['is_error'] && $get_result['count'] == 1) {
    $custom_group_id = $get_result['values'][$custom_field_id]['custom_group_id'];
    $custom_field_col = $get_result['values'][$custom_field_id]['column_name'];
    $custom_field_html_type = $get_result['values'][$custom_field_id]['html_type'];

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

      $field_config['group_id'] = $custom_group_id;
      $field_config['group_table'] = $custom_group_tbl;
      $field_config['group_extends'] = $custom_group_extends;
      $field_config['field_column'] = $custom_field_col;
      $field_config['field_html_type'] = $custom_field_html_type;
    }
  }

  // Store the values
  $civicrm_api3_ptc_activity_query_custom_field_config[$custom_field_id] = $field_config;
  return $field_config;
}
