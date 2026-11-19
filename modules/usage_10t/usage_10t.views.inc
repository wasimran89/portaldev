<?php

/**
 * Implements hook_views_data().
 */
function usage_10t_views_data() {
  $data = [];

  // Define the table for Views.
  $data['10t_usage']['table'] = [
    'group' => t('10T Usage Log'),
    'base' => [
      'field' => 'id',
      'title' => t('10T Usage Log'),
      'help' => t('Contains activity logs for 10T.'),
    ],
  ];

  // Define the fields.
  $data['10t_usage']['id'] = [
    'title' => t('ID'),
    'help' => t('The unique identifier for each activity log.'),
    'field' => [
      'id' => 'numeric',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'numeric',  // Add sorting for the ID field.
    ],
  ];

  $data['10t_usage']['iccid'] = [
    'title' => t('ICCID'),
    'help' => t('The ICCID associated with the activity.'),
    'field' => [
      'id' => 'standard',
    ],
    'filter' => [
      'id' => 'string',
    ],
    'sort' => [
      'id' => 'standard',  // Add sorting for ICCID field.
    ],
  ];

  $data['10t_usage']['path'] = [
    'title' => t('Path'),
    'help' => t('path of the activity.'),
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

  $data['10t_usage']['activity'] = [
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
  $data['10t_usage']['uid'] = [
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

  $data['10t_usage']['timestamp'] = [
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
