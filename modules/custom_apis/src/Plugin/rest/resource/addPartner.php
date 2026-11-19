<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\user\Entity\User;

/**
 * Provides a REST API for managing partner data.
 *
 * @RestResource(
 *   id = "add_partner_rest_resource",
 *   label = @Translation("Add Partner Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/partner/{id}",
 *     "create" = "/api/add-partner"
 *   }
 * )
 */
class addPartner extends ResourceBase {
  /**
   * AJAX callback to update grand_parent_partner_id options based on parent_partner_id.
   */
  public function update_grand_parent_partner_options($parentId, &$all_related_partners) {
    $connection = Database::getConnection('default', 'third');
    // Get the selected value from parent_partner_id.
    $selected_value = $parentId;
    if ($selected_value != 'NULL') {
      $all_related_partners = [];
      $this->grandPartners($selected_value, $all_related_partners);

      //$all_related_partners[] = $selected_value;
      $partners_list = implode(',', array_map('intval', $all_related_partners));

      // Build the query dynamically
      $querypid = $connection->query("
      SELECT partner_id, partner_name 
      FROM partner 
      WHERE partner_id in  ($partners_list)"
      );

      $resultpid = $querypid->fetchAll();
      $partner_pid = [];
      foreach ($resultpid as $keypid => $datapid) {      
        $partner_pid[$datapid->partner_id] = $datapid->partner_name;
      }

      if (!$partner_pid) {
        $partner_pid['NULL'] = '--None--';
      }
    } else {
      $partner_pid['NULL'] = '--Select--';
    }
  }

  public function grandPartners($partnerId, &$all_related_partners) {
    $connection = Database::getConnection('default', 'third');
    $partners = [];
    // Start building the query on the external database connection
    $query = $connection->select('partner', 'p');

    // Specify the field to retrieve (partner_id)
    $query->fields('p', ['parent_partner_id','grand_parent_partner_id']);

    // Add the OR condition group to the main query
    $query->condition('p.partner_id', $partnerId, '=');

    // Execute the query and fetch all results
    $results = $query->execute()->fetchAll();
    $partners[] = $results[0]->parent_partner_id;
    $partners[] = $results[0]->grand_parent_partner_id;

    // Loop through each result and add to the array
    foreach ($partners as $partner) {
        if (!in_array($partner, $all_related_partners)) {
            $all_related_partners[] = $partner;
            // Recursively call this function for further relationships
            $this->grandPartners($partner, $all_related_partners);
        }
    }
  }

  // Function to fetch related partners recursively
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    $connection = Database::getConnection('default', 'third');
    // Start building the query on the external database connection
    $query = $connection->select('partner', 'p');

    // Specify the field to retrieve (partner_id)
    $query->fields('p', ['partner_id']);

    // Add the OR condition for parent_partner_id and grand_parent_partner_id
    $orGroup = $query->orConditionGroup()
                      ->condition('p.parent_partner_id', $parentId, '=')
                      ->condition('p.grand_parent_partner_id', $parentId, '=');

    // Add the OR condition group to the main query
    $query->condition($orGroup);

    // Execute the query and fetch all results
    $results = $query->execute()->fetchAll();

    // Loop through each result and add to the array
    foreach ($results as $partner) {
        if (!in_array($partner->partner_id, $all_related_partners)) {
            $all_related_partners[] = $partner->partner_id;
            // Recursively call this function for further relationships
            $this->fetchRelatedPartners($partner->partner_id, $all_related_partners);
        }
    }
  }

  /**
   * GET method for retrieving custom data by ID.
   */
  public function get($id = NULL) {
    $connection = Database::getConnection('default', 'third');
    $user = \Drupal::currentUser();
    $user_display_name = $user->getEmail();
    $queryuser = $connection->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
    $resultuser = $queryuser->fetchAll();

    //child partner start
    $all_related_partners1 = [];
    $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners1);
    $all_related_partners1[] = $resultuser[0]->sim_partner_id;
    $partners_list = implode(',', array_map('intval', $all_related_partners1));

    // Build the query dynamically
    $query = $connection->query("
        SELECT partner_id, partner_name 
        FROM partner 
        WHERE partner_id in  ($partners_list)"
    );

    $result= $query->fetchAll();
    //child partner end

    $partner = [];
    foreach ($result as $key => $data) {
      $partner['NULL'] = '--Select--';
      $partner[$data->partner_id] = $data->partner_name;
    }

    if (array_key_exists($id, $partner)) {
      $all_related_partners2 = [];
      $this->update_grand_parent_partner_options($id, $all_related_partners2);
      //$all_related_partners2[] = $id;
      $partners_list2 = implode(',', array_map('intval', $all_related_partners2));

      // Build the query dynamically
      $query = $connection->query("
          SELECT partner_id, partner_name 
          FROM partner 
          WHERE partner_id in  ($partners_list2)"
      );

      $result= $query->fetchAll();

      $partner2 = [];
      foreach ($result as $key2 => $data2) {
        $partner2['NULL'] = '--Select--';
        $partner2[$data2->partner_id] = $data2->partner_name;
      }

      // Prepare the header with the execution status
      $header = [
          "header" => [
              "functionExecutionStatus" => [
                  "status" => "Executed-Success"
              ]
          ]
      ];

      // Combine the header with the data
      $responseData = [
          "header" => $header["header"],
          "parent_partner_id" => $partner,
          "grand_parent_partner_id" => $partner2
      ];

      // Create a ResourceResponse with the new structure
      $response = new ResourceResponse($responseData, Response::HTTP_OK);

      // Disable caching for the response
      $response->getCacheableMetadata()->setCacheMaxAge(0);

      // Return the response
      return $response;
    } else {
      $header = [
          "header" => [
              "functionExecutionStatus" => [
                  "status" => "Failed"
              ]
          ]
      ];

      // Combine the header with the data
      $responseData = [
          "header" => $header["header"],
          "message" => "partner id not allowed to this user."
      ];

      // Create a ResourceResponse with the new structure
      $response = new ResourceResponse($responseData, Response::HTTP_OK);

      // Disable caching for the response
      $response->getCacheableMetadata()->setCacheMaxAge(0);

      // Return the response
      return $response;
    }
  }

  /**
   * POST method for creating new custom data.
   */
  public function post($data) {
    $this->validateData($data);  // Validate data before inserting
    try {
      $connection = Database::getConnection('default', 'third');
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $user_display_name = $user->getEmail();
      
      $characters = '0123456789ABCDEF';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < 32; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
      }

      $partner_name = $data['partner_name'];
      $op = $data['op'] ? $data['op'] : $randomString;
      $json_secrets = $data['json_secrets'];
      $parent_partner_id = $data['parent_partner_id'];
      $grand_parent_partner_id = $data['grand_parent_partner_id'];
      $queryuser = $connection->query("INSERT INTO partner(partner_name, op, json_secrets, parent_partner_id, grand_parent_partner_id) values ('".$partner_name."', '".$op."', '".$json_secrets."', '".$parent_partner_id."', '".$grand_parent_partner_id."')");
    
      // Load the group type. Replace 'your_group_type' with the actual group type ID.
      $group_type = GroupType::load('tamu'); //group type machine name

      if ($group_type) {
        // Create a new group entity.
        $group = Group::create([
          'type' => $group_type->id(),  // Set the group type.
          'label' => $data['partner_name'], // Set the group title.
        ]);

        // Save the group entity.
        $group->save();

        if ($user) {
          // Add the user as a member to the newly created group.
          $group->addMember($user);
        }
        \Drupal::logger('custom_apis')->info('New group has been created with ID: ' . $group->id());
      }

      // Prepare the header with the execution status
      $header = [
          "header" => [
              "functionExecutionStatus" => [
                  "status" => "Executed-Success"
              ]
          ]
      ];

      // Combine the header with the data
      $responseData = [
          "header" => $header["header"],
          "message" => 'Data inserted successfully.'
      ];

      // Create a ResourceResponse with the new structure
      $response = new ResourceResponse($responseData, Response::HTTP_OK);

      // Disable caching for the response
      $response->getCacheableMetadata()->setCacheMaxAge(0);

      // Return the response
      return $response;
    } catch (\Exception $e) {
        \Drupal::logger('custom_apis')->error($e->getMessage());
        $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } },"message": "'.$e->getMessage().'" }';
        $dataoutput = json_decode($responseresult, true);
        return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
    }
  }

  /**
   * PUT method for updating custom data.
   */
  public function put($id) {    
    $request = \Drupal::request();
    $content = $request->getContent(); // Get the raw JSON content
    $data = json_decode($content, TRUE); // Convert JSON input to an associative array
    $this->validateData($data);  // Validate data before inserting
    
    try {
      $connection = Database::getConnection('default', 'third');
      $defaultconnection = \Drupal::database();
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $user_display_name = $user->getEmail();
      
      $characters = '0123456789ABCDEF';
      $charactersLength = strlen($characters);
      $randomString = '';
      for ($i = 0; $i < 32; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
      }
      $partner_id = $id;
      $partner_name = $data['partner_name'];
      $op = $data['op'] ? $data['op'] : $randomString;
      $json_secrets = $data['json_secrets'];
      $parent_partner_id = $data['parent_partner_id'];
      $grand_parent_partner_id = $data['grand_parent_partner_id'];

      //get name from partner tabel
      $querypname = $connection->query("select partner_name from partner where partner_id = '".$id."'");
      $resultpname = $querypname->fetchAll();
      //get name end

      //get group name
      $queryid = $defaultconnection->query("select id from groups_field_data where label = '".$resultpname[0]->partner_name."'");
      $resultid = $queryid->fetchAll();
      //get group name exit

      $queryuser = $connection->query("UPDATE partner
                    SET partner_name = '".$partner_name."',
                        op = '".$op."',
                        json_secrets = '".$json_secrets."',
                        parent_partner_id = '".$parent_partner_id."',
                        grand_parent_partner_id = '".$grand_parent_partner_id."'
                    WHERE partner_id = '".$partner_id."'");

      $group = Group::load($resultid[0]->id);

      if ($group) {
        // Update the group's label or other fields.
        $group->set('label', $partner_name);

        // Save the group entity.
        $group->save();

        if ($user) {
          // Add the user as a member to the newly created group.
          $group->addMember($user);
        }
        \Drupal::logger('custom_apis')->info('group has been updated with ID: ' . $group->id());
      }

      // Prepare the header with the execution status
      $header = [
          "header" => [
              "functionExecutionStatus" => [
                  "status" => "Executed-Success"
              ]
          ]
      ];

      // Combine the header with the data
      $responseData = [
          "header" => $header["header"],
          "message" => 'Data updated successfully.'
      ];

      // Create a ResourceResponse with the new structure
      $response = new ResourceResponse($responseData, Response::HTTP_OK);

      // Disable caching for the response
      $response->getCacheableMetadata()->setCacheMaxAge(0);

      // Return the response
      return $response;
    } catch (\Exception $e) {
        \Drupal::logger('custom_apis')->error($e->getMessage());
        $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } },"message": "'.$e->getMessage().'" }';
        $dataoutput = json_decode($responseresult, true);
        return (new ResourceResponse($dataoutput, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
    }
  }

  /**
   * DELETE method for removing custom data by ID.
   */
  public function delete($id) {
    $connection = Database::getConnection('default', 'third');
    $defaultconnection = \Drupal::database();
    
    try {
        if ($id) {
          //get name from partner tabel
          $querypname = $connection->query("select partner_name from partner where partner_id = '".$id."'");
          $resultpname = $querypname->fetchAll();
          //get name end

          //get group name
          $queryid = $defaultconnection->query("select id from groups_field_data where label = '".$resultpname[0]->partner_name."'");
          $resultid = $queryid->fetchAll();
          //get group name exit

          // Perform the delete query
          $result = $connection->delete('partner')
                              ->condition('partner_id', $id)
                              ->execute();

          $group = Group::load($resultid[0]->id);

          if ($group) {
            $group->delete();
            \Drupal::logger('custom_apis')->info('group has been deleted with ID: ' . $group->id());
          }
          $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Executed-Success" } },"message": "Data deleted successfully." }';
          print_r($responseresult);
          exit;
        } else {
          $responseresult = '{ "header": { "functionExecutionStatus": { "status": "Failed" } },"message": "id not found." }';
          print_r($responseresult);
          exit;
        }
    } catch (\Exception $e) {
        // Log error message for debugging (optional)
        \Drupal::logger('custom_api')->error($e->getMessage());

        // Return error response in JSON format
        return new ResourceResponse(['error' => 'Failed to delete entry.'], 500, ['Content-Type' => 'application/json']);
    }
  }


  /**
   * Validates the input data.
   */
  protected function validateData(array $data) {
    $errors = [];

    // Validate 'imsi_range_name' (example: required)
    if (empty($data['imsi_range_name'])) {
      $errors[] = 'The IMSI range name is required.';
    }

    // Validate 'mcc' (example: must be numeric and length 3)
    if (empty($data['mcc']) || !is_numeric($data['mcc']) || strlen($data['mcc']) != 3) {
      $errors[] = 'The MCC must be a numeric value with a length of 3.';
    }

    // Validate 'mnc' (example: must be numeric and length 2 or 3)
    if (empty($data['mnc']) || !is_numeric($data['mnc']) || (strlen($data['mnc']) < 2 || strlen($data['mnc']) > 3)) {
      $errors[] = 'The MNC must be a numeric value with a length of 2 or 3.';
    }

    if (empty($data['ibn']) || !is_numeric($data['ibn']) || (strlen($data['ibn']) !=4)) {
      $errors[] = 'The ibn must be a numeric value with a length of 4.';
    }

    // Validate other fields similarly...
  }
}
