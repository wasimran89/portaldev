<?php

namespace Drupal\jcius_custom\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class ApiKeyController extends ControllerBase {

  public function logCopy(Request $request) {
    // Get from URL query string
    $api_key = $request->request->get('api_key'); 
    $group_name = $request->request->get('group_name'); 
    $activity = $request->request->get('activity');
    // Save to database
    \Drupal::database()->insert('apikey_activity_log')
      ->fields([
        'uid' => \Drupal::currentUser()->id(),
        'group_name' => $group_name,
        'api_key_value' => $api_key,
        'activity' => $activity,
        'created_at' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    return new JsonResponse(['status' => 'success']);
  }
}

?>