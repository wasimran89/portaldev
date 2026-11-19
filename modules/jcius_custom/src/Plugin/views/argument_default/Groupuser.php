<?php

namespace Drupal\jcius_custom\Plugin\views\argument_default;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheableDependencyInterface;
use Drupal\Core\Plugin\Context\ContextProviderInterface;
use Drupal\views\Plugin\views\argument_default\ArgumentDefaultPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\Group;

/**
 * Default argument plugin to extract a group ID.
 *
 * @ViewsArgumentDefault(
 *   id = "user_group_id",
 *   title = @Translation("User Group ID")
 * )
 */
class Groupuser extends ArgumentDefaultPluginBase implements CacheableDependencyInterface {

  /**
   * The group entity from the route.
   *
   * @var \Drupal\group\Entity\GroupInterface
   */
  protected $group;

  /**
   * Constructs a new Group instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Plugin\Context\ContextProviderInterface $context_provider
   *   The group route context.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ContextProviderInterface $context_provider) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    /** @var \Drupal\Core\Plugin\Context\ContextInterface[] $contexts */
    $contexts = $context_provider->getRuntimeContexts(['group']);
    $this->group = $contexts['group']->getContextValue();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('group.group_route_context')
    );
  }

  // Function to fetch related partners recursively
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

  /**
   * {@inheritdoc}
   */
  public function getArgument() {      
      /*
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $field_value = $user->get('field_group_type')->getValue();
      if($field_value){
        $userStorage = \Drupal::entityTypeManager()->getStorage('user');
        $query = $userStorage->getQuery();
        $uids = $query
          ->condition('status', '1')
          ->condition('field_group_type', $field_value[0]['target_id'])
          ->execute();
        $uid = implode(', ', $uids);
        return $uid;
      }
      else{
        $uid = $user->id();
        return $uid;
      }
      */
      $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
      $roles = $user->getRoles();
      $user_display_name = $user->getEmail();
      $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');
      if ($user_display_name) {
        $queryuser = $conn->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();

        $all_related_partners = [];
        $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
        $all_related_partners[] = $resultuser[0]->sim_partner_id;      
      }

      //parent and grand parent id from current user sim_partner_id
      //print_r($all_related_partners);

      $arp = implode(',', $all_related_partners);
      $queryp = $conn->query("SELECT partner_id, partner_name FROM partner WHERE partner_id IN ($arp)");
      $resultp = $queryp->fetchAll();
      $partner_name = [];
      foreach ($resultp as $keyp => $datap) {
        $partner_name[$datap->partner_name] = $datap->partner_id;
      }

      //partner name with partner_id from partner table
      //print_r($partner_name);

      $group_ids = []; // Array to store matching group IDs
      $groups = Group::loadMultiple();

      //maping in group list in drupal application
      foreach ($groups as $group) {
          $id = $group->id(); 
          $label = trim($group->label());

          // Check if the label exists as a key in the partner_name array
          if (isset($partner_name[$label])) {
              $group_ids[$label] = $id; // Store the matching group ID
          }
      }

      $userStorage = \Drupal::entityTypeManager()->getStorage('user');
      $query = $userStorage->getQuery();

      if (in_array('administrator', $roles)) {
        $uids = $query
        ->condition('status', '1')
        ->execute();
        $uid = implode(', ', $uids);
      } else { //non admin user
        if (!$group_ids) {
          $group_ids= 0;
        }
        $uids = $query
        ->condition('status', '1')
        ->condition('field_group_type', $group_ids, 'IN')
        ->execute();
        $uid = implode(', ', $uids);
      }
      
      return $uid;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge() {
    return Cache::PERMANENT;
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheContexts() {
    // We cache the result on the route instead of the URL so that path aliases
    // can all use the same cache context. If you look at ::getArgument() you'll
    // see that we actually get the group ID from the route, not the URL.
    return ['route'];
  }

}
