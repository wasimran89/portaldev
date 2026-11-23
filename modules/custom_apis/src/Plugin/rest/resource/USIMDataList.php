<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;

/**
 * Provides a USIMDataList Resource
 *
 * @RestResource(
 *   id = "usimdata",
 *   label = @Translation("USIM Data"),
 *   uri_paths = {
 *     "canonical" = "/usim/api/list"
 *   }
 * )
 */
class USIMDataList extends ResourceBase {

  /**
   * Responds to entity GET requests.
   *
   * @return \Drupal\rest\ResourceResponse
   */
  public function get() {
    try {
      $user = \Drupal::currentUser();
      $user_display_name = $user->getEmail();
      $roles = $user->getRoles();
      $conn = Database::getConnection('default', 'third');
      
      $result = []; // Initialize an empty array for the result.

      if (in_array('administrator', $roles)) {
        // Administrator-specific query
        $query = $conn->select('usim_provision_vw', 'upv')->fields('upv');
        //filter by master_id
        if (isset($_GET['master_id']) && $_GET['master_id'] !== '') {
          $query->condition('upv.master_id', $_GET['master_id']);
        }

        //filter by msisdn
        if (isset($_GET['msisdn']) && $_GET['msisdn'] !== '') {
          $query->condition('upv.msisdn', $_GET['msisdn']);
        }

        //filter by iccid
        if (isset($_GET['iccid']) && $_GET['iccid'] !== '') {
          $query->condition('upv.iccid', $_GET['iccid']);
        }

        //filter by imsi
        if (isset($_GET['imsi']) && $_GET['imsi'] !== '') {
          $query->condition('upv.imsi', $_GET['imsi']);
        }
        //filter by partner_name
        if (isset($_GET['partner_name']) && $_GET['partner_name'] !== '') {
          $query->condition('upv.partner_name', '%' . $_GET['partner_name'] . '%', 'LIKE');
        }
        $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      } else {
        // Non-administrator query
        $queryuser = $conn->select('drupal_user', 'du')
                          ->fields('du', ['sim_partner_id'])
                          ->condition('du.drupal_user', $user_display_name)
                          ->execute()
                          ->fetchField();

        if ($queryuser) {
          $sim_partner_id = $queryuser;
          $all_related_partners = [];
          $this->fetchRelatedPartners($sim_partner_id, $all_related_partners);
          $all_related_partners[] = $sim_partner_id;

          $query = $conn->select('usim_provision_vw', 'upv')
                        ->fields('upv')
                        ->condition('upv.partner_id', $all_related_partners, 'IN');
          //filter by master_id
          if (isset($_GET['master_id']) && $_GET['master_id'] !== '') {
            $query->condition('upv.master_id', $_GET['master_id']);
          }

          //filter by msisdn
          if (isset($_GET['msisdn']) && $_GET['msisdn'] !== '') {
            $query->condition('upv.msisdn', $_GET['msisdn']);
          }

          //filter by iccid
          if (isset($_GET['iccid']) && $_GET['iccid'] !== '') {
            $query->condition('upv.iccid', $_GET['iccid']);
          }

          //filter by imsi
          if (isset($_GET['imsi']) && $_GET['imsi'] !== '') {
            $query->condition('upv.imsi', $_GET['imsi']);
          }
          //filter by partner_name
          if (isset($_GET['partner_name']) && $_GET['partner_name'] !== '') {
            $query->condition('upv.partner_name', '%' . $_GET['partner_name'] . '%', 'LIKE');
          }
          $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        } else {
          $response = [
            "header" => ["functionExecutionStatus" => ["status" => "Failed"]],
            "message" => "No SIM partner assigned to user"
          ];
          return (new ResourceResponse($response, Response::HTTP_FORBIDDEN))->addCacheableDependency(['max-age' => 0]);
        }
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
          "list" => $result // $result contains the array of eSIM profiles
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
   * Fetch related partners recursively.
   *
   * @param int $parentId
   *   The parent partner ID.
   * @param array $all_related_partners
   *   The array to store all related partner IDs.
   */
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');
    // Start building the query on the external database connection
    $query = $conn->select('partner', 'p');

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
}
