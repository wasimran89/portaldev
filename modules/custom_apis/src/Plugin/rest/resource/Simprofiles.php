<?php

namespace Drupal\custom_apis\Plugin\rest\resource;

use Drupal\rest\Plugin\ResourceBase;
use Drupal\rest\ResourceResponse;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\Response;
use Drupal\user\Entity\User;

/**
 * Provides a Simprofiles Resource
 *
 * @RestResource(
 *   id = "getsimprofile",
 *   label = @Translation("Get SIM profile by partner"),
 *   uri_paths = {
 *     "canonical" = "/api/simprofiles"
 *   }
 * )
 */
class Simprofiles extends ResourceBase
{
  /**
   * Post api
   */
  public function get() {
    $user = User::load(\Drupal::currentUser()->id());
    $group = $user->field_group_type->getValue(); //current user group load
    $type = $group[0]['target_id'];
    $roles = $user->getRoles();
    $storage = \Drupal::entityTypeManager()->getStorage('webform_submission');
    $webform_id = 'sim_profile';
    $query = $storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('webform_id', $webform_id)
      ->execute();    

    $submission_ids = array_values($query);
    $submissions = $storage->loadMultiple($submission_ids);

    $data = [];

    if(in_array('administrator', $roles)) {
      foreach ($submissions as $submission) {
        $data[] = $submission->getData();
      }
    } else {
      foreach ($submissions as $submission) {
        $submission_group = $submission->getData();
        if ($submission_group['group_type'] == $type) {
          $data[] = $submission->getData();
        }
      }
    }

    

    return new ResourceResponse($data, 200);
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
