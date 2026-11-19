<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Cache\CacheableMetadata;

/**
 * Provides a ESIMDataList Resource
 *
 * @RestResource(
 *   id = "esimdata",
 *   label = @Translation("eSim Data"),
 *   uri_paths = {
 *     "canonical" = "/api/list"
 *   }
 * )
 */
class ESIMDataList extends ResourceBase {

  /**
   * GET method.
   */
  public function get() {
    try {

      // *******************************
      // DISABLE PAGE CACHE (KILL SWITCH)
      // *******************************
      \Drupal::service('page_cache_kill_switch')->trigger();

      $request = \Drupal::request();
      $apiKey = trim((string) $request->headers->get('x-api-key', ''));

      $label = NULL;

      // API Key lookup
      if ($apiKey !== '') {
        $connection = \Drupal::database();
        $query = $connection->select('groups_field_data', 'g');
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

      $user = \Drupal::currentUser();
      $roles = $user->getRoles();
      $user_display_name = $user->getEmail();

      $conn = Database::getConnection('default', 'third');
      $result = [];

      // Admin flow
      if (in_array('administrator', $roles, TRUE)) {
        $query = $conn->select('esim_10t_status_vw', 'esv')->fields('esv');

        if (!empty($_GET['state'])) {
          $query->condition('esv.state', $_GET['state']);
        }

        if (!empty($_GET['partner_name'])) {
          $query->condition('esv.partner_name', '%' . $_GET['partner_name'] . '%', 'LIKE');
        }

        $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);
      }
      else {
        // Normal user → get partner id
        if (!empty($label)) {
          $sim_partner_id = $conn->select('partner', 'p')
            ->fields('p', ['partner_id'])
            ->condition('p.partner_name', $label)
            ->execute()
            ->fetchField();
        }
        else {
          $sim_partner_id = $conn->select('drupal_user', 'du')
            ->fields('du', ['sim_partner_id'])
            ->condition('du.drupal_user', $user_display_name)
            ->execute()
            ->fetchField();
        }

        if ($sim_partner_id) {

          $all_related_partners = [];
          $this->fetchRelatedPartners($sim_partner_id, $all_related_partners);
          $all_related_partners[] = $sim_partner_id;

          $query = $conn->select('esim_10t_status_vw', 'esv')
            ->fields('esv')
            ->condition('esv.partner_id', $all_related_partners, 'IN');

          if (!empty($_GET['state'])) {
            $query->condition('esv.state', $_GET['state']);
          }

          if (!empty($_GET['partner_name'])) {
            $query->condition('esv.partner_name', '%' . $_GET['partner_name'] . '%', 'LIKE');
          }

          if (!empty($label)) {
            $query->condition('esv.partner_name', $label);
          }

          $result = $query->execute()->fetchAll(\PDO::FETCH_ASSOC);

        } else {
          $data = [
            "header" => ["functionExecutionStatus" => ["status" => "Failed"]],
            "message" => "Authentication required",
          ];
          return $this->noCacheResponse($data, Response::HTTP_UNAUTHORIZED);
        }
      }

      // Final Response
      $responseData = [
        "header" => ["functionExecutionStatus" => ["status" => "Executed-Success"]],
        "list" => $result
      ];

      return $this->noCacheResponse($responseData);

    } catch (\Exception $e) {

      \Drupal::logger('custom_apis')->error($e->getMessage());

      $dataoutput = [
        "header" => ["functionExecutionStatus" => ["status" => "Failed"]],
        "message" => $e->getMessage()
      ];

      return $this->noCacheResponse($dataoutput, Response::HTTP_FORBIDDEN);
    }
  }

  /**
   * Recursively fetch related partners.
   */
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    $conn = Database::getConnection('default', 'third');

    $query = $conn->select('partner', 'p')
      ->fields('p', ['partner_id']);

    $orGroup = $query->orConditionGroup()
      ->condition('p.parent_partner_id', $parentId)
      ->condition('p.grand_parent_partner_id', $parentId);

    $query->condition($orGroup);

    $results = $query->execute()->fetchAll();

    foreach ($results as $partner) {
      if (!in_array($partner->partner_id, $all_related_partners)) {
        $all_related_partners[] = $partner->partner_id;
        $this->fetchRelatedPartners($partner->partner_id, $all_related_partners);
      }
    }
  }

  /**
   * Builds NO-CACHE response.
   */
  private function noCacheResponse($data, $status = 200) {

    // Create the response
    $response = new ResourceResponse($data, $status);

    // Cache metadata: max-age = 0
    $cache = new CacheableMetadata();
    $cache->setCacheMaxAge(0);

    // Attach to response
    $response->addCacheableDependency($cache);

    return $response;
  }

}
