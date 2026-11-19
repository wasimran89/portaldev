<?php

/**
 * Implements hook_views_data().
 */
function credit_allotment_history_views_data() {
  $data = [];

  // Define the table for Views.
  $data['credit_allotment_history']['table'] = [
    'group' => t('Credit Allotment History'),
    'base' => [
      'field' => 'id',
      'title' => t('Credit Allotment History'),
      'help' => t('Contains credit allotment history.'),
    ],
  ];

  // Define the fields.
  $data['credit_allotment_history']['id'] = [
    'title' => t('ID'),
    'help' => t('The unique identifier for each credit allotment activity.'),
    'field' => [
      'id' => 'numeric',
    ],
    'sort' => [
      'id' => 'numeric',  // Add sorting for the ID field.
    ],
  ];

  $data['credit_allotment_history']['from_partner_id'] = [
    'title' => t('From Group'),
    'help' => t('The group that send the credit.'),
    'field' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'title' => t('From Group'),
      'help' => t('Relate to groups_field_data to get group details.'),
      'base' => 'groups_field_data',       // Target table
      'base field' => 'id',                // Target table field (group ID)
      'relationship field' => 'from_partner_id',  // Local field in your table
      'id' => 'standard',
      'label' => t('From Group'),
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['credit_allotment_history']['to_partner_id'] = [
    'title' => t('To Group'),
    'help' => t('The group that received the credit.'),
    'field' => [
      'id' => 'standard',
    ],
    'relationship' => [
      'title' => t('To Group'),
      'help' => t('Relate to groups_field_data to get group details.'),
      'base' => 'groups_field_data',       // Target table
      'base field' => 'id',                // Target table field (group ID)
      'relationship field' => 'to_partner_id',  // Local field in your table
      'id' => 'standard',
      'label' => t('To Group'),
    ],
    'sort' => [
      'id' => 'standard',
    ],
  ];

  $data['credit_allotment_history']['operation_type'] = [
    'title' => t('Operation Type'),
    'help' => t('Description of the operation type.'),
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

  $data['credit_allotment_history']['credit_amount'] = [
    'title' => t('Credit Amount'),
    'help' => t('Description of the credit amount.'),
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

  $data['credit_allotment_history']['remarks'] = [
    'title' => t('Remarks'),
    'help' => t('Description of the remarks'),
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

  // Define the 'uid' field with a relationship to users_field_data table.
  $data['credit_allotment_history']['uid'] = [
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

  $data['credit_allotment_history']['timestamp'] = [
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
