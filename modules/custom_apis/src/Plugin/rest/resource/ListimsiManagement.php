<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a REST API for managing List IMSI Management data.
 *
 * @RestResource(
 *   id = "list_imsi_management_rest_resource",
 *   label = @Translation("List IMSI Management Rest Resource"),
 *   uri_paths = {
 *     "canonical" = "/api/list-imsi-management",
 *   }
 * )
 */
class ListimsiManagement extends ResourceBase {
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
  public function get() {
    try {
      $connection = Database::getConnection('default', 'third');
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $roles = $user->getRoles();
      $user_display_name = $user->getEmail();
      $queryuser = $connection->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
      $resultuser = $queryuser->fetchAll();

      $all_related_partners = [];
      $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
      $all_related_partners[] = $resultuser[0]->sim_partner_id;

      $query = $connection->select('imsi_range', 'ir')
                          ->fields('ir');

      if (!in_array('administrator', $roles)) {  
        $query->condition('partner_id',$all_related_partners, 'IN');
      }

      $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

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
            "list" => $result
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
