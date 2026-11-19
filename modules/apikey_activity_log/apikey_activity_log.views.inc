<?php

/**
 * Implements hook_views_data().
 */
function apikey_activity_log_views_data() {
  $data = [];

  // Define the table for Views.
  $data['apikey_activity_log']['table'] = [
    'group' => t('API Key Activity Log'),
    'base' => [
      'field' => 'id',
      'title' => t('API Key Activity Log'),
      'help' => t('Contains activity logs for API Key code operations.'),
    ],
  ];

  // Define the fields.
  $data['apikey_activity_log']['id'] = [
    'title' => t('ID'),
    'help' => t('The unique identifier for each activity log.'),
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'numeric',  // Add sorting for the ID field.
    ],
  ];

  $data['apikey_activity_log']['group_name'] = [
    'title' => t('Group'),
    'help' => t('The group associated with the activity.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['apikey_activity_log']['api_key_value'] = [
    'title' => t('API Key'),
    'help' => t('The API Key associated with the activity.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['apikey_activity_log']['activity'] = [
    'title' => t('Activity'),
    'help' => t('Description of the activity.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',  // Add sorting for Activity field.
    ],
  ];

  // Define the 'uid' field with a relationship to users_field_data table.
  $data['apikey_activity_log']['uid'] = [
    'title' => t('User ID'),
    'help' => t('The ID of the user who performed the activity.'),
    'field' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'base' => 'users_field_data', // Target table.
      'base field' => 'uid',        // Target field in users_field_data.
      'relationship field' => 'uid', // Local field in last_visited_pages.
      'id' => 'standard',
      'label' => t('User'),
    ],
    'sort' => [
      'id' => 'standard',  // Add sorting for UID field.
    ],
  ];

  $data['apikey_activity_log']['created_at'] = [
    'title' => t('Timestamp'),
    'help' => t('The time when the activity occurred.'),
    'field' => [
      'id' => 'date',
    ],
    'filter' => [
      'id' => 'date',
    ],
    'sort' => [
      'id' => 'date',  // Add sorting for Timestamp field.
    ],
  ];

  return $data;
}
