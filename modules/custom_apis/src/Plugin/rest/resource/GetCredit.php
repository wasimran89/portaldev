<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;
use Drupal\group\Entity\Group;

/**
 * Provides a REST API for getting credit release and reset data.
 *
 * @RestResource(
 *   id = "credit_release_reset",
 *   label = @Translation("GET Credit Release Reset"),
 *   uri_paths = {
 *     "canonical" = "/api/credit",
 *   }
 * )
 */
class GetCredit extends ResourceBase {
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    $externalconnection = Database::getConnection('default', 'third');
    // Start building the query on the external database connection
    $query = $externalconnection->select('partner', 'p');

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
  public function get() {
    try {
      // *******************************
      // DISABLE PAGE CACHE (KILL SWITCH)
      // *******************************
      \Drupal::service('page_cache_kill_switch')->trigger();

      $defaultconnection = Database::getConnection('default', 'default');
      $externalconnection = Database::getConnection('default', 'third');

      $request = \Drupal::request();
      $apiKey = trim((string) $request->headers->get('x-api-key', ''));

      $label = NULL;
      
      // API Key lookup
      if ($apiKey !== '') {
        $query = $defaultconnection->select('groups_field_data', 'g');
        $query->fields('g', ['id', 'label']);
        $query->join('group__field_api_key', 'k', 'g.id = k.entity_id');
        $query->condition('k.field_api_key_value', $apiKey);
        $groupRows = $query->execute()->fetchAll();

        if (!empty($groupRows)) {
          $label = (string) $groupRows[0]->label;
        }

        // **********************
        // INVALID API KEY BLOCK
        // **********************
        if (empty($label)) {
          $error = [
            "header" => [
              "functionExecutionStatus" => ["status" => "Failed"]
            ],
            "message" => "Authentication required"
          ];

          $response = new ResourceResponse($error, Response::HTTP_UNAUTHORIZED);
          $response->getCacheableMetadata()->setCacheMaxAge(0);
          return $response;
        }
      }
      
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $roles = $user->getRoles();
      $user_display_name = $user->getEmail();

      if (!empty($label)) {
          $sim_partner_id = $externalconnection->select('partner', 'p')
            ->fields('p', ['partner_id'])
            ->condition('p.partner_name', $label)
            ->execute()
            ->fetchField();
      } else {
          if (empty($user_display_name)) {
            $error = [
              "header" => [
                "functionExecutionStatus" => ["status" => "Failed"]
              ],
              "message" => "Authentication required"
            ];

            $response = new ResourceResponse($error, Response::HTTP_UNAUTHORIZED);
            $response->getCacheableMetadata()->setCacheMaxAge(0);
            return $response;
          }

          $queryuser = $externalconnection->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
          $resultuser = $queryuser->fetchAll();
          $sim_partner_id = $resultuser[0]->sim_partner_id;
      }
      

      $all_related_partners = [];
      $this->fetchRelatedPartners($sim_partner_id, $all_related_partners);
      $all_related_partners[] = $sim_partner_id;

      /////
      $userdata = [];
      $u_billing_entity_id = [];
      foreach ($all_related_partners as $partner_id) {
        $queryp = $externalconnection->query("select partner_name from partner where partner_id=".$partner_id);
        $resultp = $queryp->fetchAll();
        foreach ($resultp as $keyp => $datap) {
          $partner_name = $datap->partner_name;
        }

        $query4 = $defaultconnection->query("select id, label from groups_field_data");
        $result4 = $query4->fetchAll();
        foreach ($result4 as $key4 => $data4) {
          //if group name and partner name 
          if (strtolower($data4->label) == strtolower($partner_name)) {
            $group_id = $data4->id;
          }            
        }
        //load specific group id
        $group = Group::load($group_id);
        if ($group) {
          $field_credit_reset = $group->get('field_credit_reset')->value;
          $field_credit_release = $group->get('field_credit_release')->value;
        } else {
          $field_credit_reset = 0;
          $field_credit_release = 0;
        }

        //$userdata[] = $data2;
        $userdata[] = array(
                'partner_name'=>$partner_name,
                'credit_release'=>$field_credit_release,
                'credit_reset'=>$field_credit_reset
              );
      } //foreach
      /////


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
            "list" => $userdata
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
}
