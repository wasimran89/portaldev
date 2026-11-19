<?php

namespace Drupal\masquerade\Form;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\masquerade\Masquerade;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Url;
use Drupal\group\Entity\Group;

/**
 * Form builder for the masquerade form.
 */
class MasqueradeForm extends FormBase {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The masquerade service.
   *
   * @var \Drupal\masquerade\Masquerade
   */
  protected $masquerade;

  /**
   * Constructs a MasqueradeForm object.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $etm
   *   The entity type manager.
   * @param \Drupal\masquerade\Masquerade $masquerade
   *   The masquerade service.
   */
  public function __construct(EntityTypeManagerInterface $etm, Masquerade $masquerade) {
    $this->entityTypeManager = $etm;
    $this->masquerade = $masquerade;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('masquerade')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'masquerade_block_form';
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
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['autocomplete'] = [
      '#type' => 'container',
      '#attributes' => ['class' => ['container-inline']],
    ];
    $form['autocomplete']['masquerade_as'] = [
      '#type' => 'entity_autocomplete',
      '#target_type' => 'user',
      '#selection_settings' => [
        'include_anonymous' => FALSE,
        'match_operator' => 'STARTS_WITH',
      ],
      '#title' => $this->t('Username'),
      '#title_display' => 'invisible',
      '#required' => TRUE,
      '#placeholder' => $this->t('Masquerade as…'),
      '#size' => '18',
    ];
    $form['autocomplete']['actions'] = ['#type' => 'actions'];
    $form['autocomplete']['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Switch'),
    ];
    $form['#prefix'] = "<h1 class='uppar-case'>Masquerade</h1>";

    $form['autocomplete']['actions']['previous'] = [
      '#type' => 'submit',
      '#value' => $this->t('Previous'),
      '#submit' => [[$this, 'Previous']],
      '#limit_validation_errors' => [], //no validation for back button
      '#attributes' => array('class' => array('orevious'),'title' => t("Previous"))
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $user_id = $form_state->getValue('masquerade_as');
    if (empty($user_id)) {
      $form_state->setErrorByName('masquerade_as', $this->t('The user does not exist. Please enter a valid username.'));
      return;
    }
    $target_account = $this->entityTypeManager
      ->getStorage('user')
      ->load($user_id);

    //Custom logic start
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if (in_array('administrator', $roles)) {
      if ($error = masquerade_switch_user_validate($target_account)) {
        $form_state->setErrorByName('masquerade_as', $error);
      }
      else {
        $form_state->setValue('masquerade_target_account', $target_account);
      }
    } else {
      $user_display_name = $user->getEmail();
      $conn = \Drupal\Core\Database\Database::getConnection('default', 'third');

      if ($user_display_name) {
        $queryuser = $conn->query("SELECT sim_partner_id FROM drupal_user WHERE drupal_user = :user_display_name", [':user_display_name' => $user_display_name]);
        $resultuser = $queryuser->fetchAll();

        $all_related_partners = [];
        if (!empty($resultuser)) {
          $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
          $all_related_partners[] = $resultuser[0]->sim_partner_id;
        }
      }

      if ($target_account) {
        $queryuser2 = $conn->query("SELECT sim_partner_id FROM drupal_user WHERE drupal_user = :target_email", [':target_email' => $target_account->getEmail()]);
        $resultuser2 = $queryuser2->fetchAll();

        if (!empty($resultuser2)) {
          $targeuser_partner_id = $resultuser2[0]->sim_partner_id;

          // Check if $targeuser_partner_id is in $all_related_partners
          if (in_array($targeuser_partner_id, $all_related_partners)) {
            if ($error = masquerade_switch_user_validate($target_account)) {
              $form_state->setErrorByName('masquerade_as', $error);
            }
            else {
              $form_state->setValue('masquerade_target_account', $target_account);
            }
          } else {
            // If partner ID is not in the array
            $form_state->setErrorByName('masquerade_as', $this->t('You are not allowed to masquerade ' .$target_account->getEmail()));
            return;
          }
        }
      }      
    }
    //custom logic end     
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->masquerade->switchTo($form_state->getValue('masquerade_target_account'));
  }

  public function Previous(array $form, FormStateInterface $form_state) {
    //$response = new RedirectResponse("/user-billing-entity-id");
    //$response->send();
    $url = Url::fromRoute('mydata.user_billing_entity_id_display');
    return $form_state->setRedirectUrl($url);
  }

}
