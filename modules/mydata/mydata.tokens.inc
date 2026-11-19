<?php
/**
 * @file
 */
use Drupal\Core\Render\BubbleableMetadata;
/**
 * Implements hook_token_info().
 */
function mydata_token_info() {
  //Setup up a new type.
  //The type would be used infront of the token.
  //E.g custom-type:custom-token, just like site:name
  $types['custom-type'] = [
    'name' => t("Custom type"),
    'description' => t("Custom type grouping for tokens"),
  ];

  //Setup a custom site wide token.
  $custom['partner-id'] = [
    'name' => t("Partner id"),
    'description' => t("Associate partner id"),
  ];

  return [
    'types' => $types,
    'tokens' => [
      'custom-type' => $custom,
    ],
  ];
}


/**
 * Implements hook_tokens().
 */
function mydata_tokens($type, $tokens, array $data, array $options,
                                               BubbleableMetadata $bubbleable_metadata) {

  $replacements = [];
  //If the type is the one that we want.
  if ($type == 'custom-type') {
    foreach ($tokens as $name => $original) {
      switch ($name) {
        case 'partner-id':
          //If token equals the custom token that we setup in the other function.
          //Grab the value from the general settings config that we want to display when the token is used.
          //
          $current_user = \Drupal::currentUser(); //returns an AccountProxyInterface object and not a UserInterface object
          //set variable for billing name on header
          $user_display_name = $current_user->getEmail();
          if($user_display_name){
            \Drupal\Core\Database\Database::setActiveConnection();
            $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');
            $query = $conn->query("select sim_partner_id from drupal_user where drupal_user= '".$user_display_name."'");
            $result = $query->fetchAll();
          }
          //$state = \Drupal::state();
          //$bubbleable_metadata->addCacheableDependency($result);
          //Add the value to the array.
          $replacements[$original] = $result[0]->sim_partner_id;
          break;
      }
    }
  }

  //Return the result so that we can now use the token.
  return $replacements;
}