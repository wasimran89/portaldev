<?php

namespace Drupal\user_credit_tabs\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
//use Drupal\masquerade\Masquerade;
use Drupal\group\Entity\Group;

/**
 * SendToDestinationsForm class.
 */
class CreditAllotment extends FormBase {
  /**
   * The default database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $defaultdatabase;
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externaldatabase;
  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   * @param \Drupal\Core\Database\Connection $externalconnection
   *   The external database connection.
   */
  protected $pagermanager;

  protected $session;

  public function __construct(Connection $defaultconnection, Connection $externalconnection, PagerManagerInterface $pagermanager, SessionInterface $session) {
    $this->defaultdatabase = $defaultconnection;
    $this->externaldatabase = $externalconnection;
    $this->pagermanager = $pagermanager;
    $this->session = $session;
  }

  public static function create(ContainerInterface $container) {
    $defaultconnection = $container->get('database');
    $externalconnection = $container->get('mydata.external_database');
    $pagermanager = $container->get('pager.manager');
    $session = $container->get('session');
    return new static($defaultconnection, $externalconnection, $pagermanager, $session);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'credit_allotment_form';
  }

  // Function to fetch related partners recursively
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    // Start building the query on the external database connection
    $query = $this->externaldatabase->select('partner', 'p');

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
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['names'] = [
      "#type"=>"table",
      "#tree"=>true,
      '#empty' => t('No users assigned Partner '.$this->session->get('var_partner_name')."."),
      '#header' => [
        ["data"=>t("Partner Name"),],
        ["data"=>t("Available Credit Release"),],
        ["data"=>t("Available Credit Reset"),],
        ["data"=>t("Credit Release"),],
        ["data"=>t("Credit Reset"),],
      ],
      '#prefix'      => "<div id='table-wrapper' class='col-sm-12'><h1 class='uppar-case'>Credit Allotment</h1>",
      '#suffix'      => '</div>',
      '#attributes' => array('class' => array('custom-table'))
    ];

    try {    
      //$query_group_name = $_GET['group_name'];
      $query_group_name = $this->session->get('current_partner_name');
      if ($query_group_name) {
        $query_part= $this->externaldatabase->select('partner', 'part')
                    ->fields('part', ['partner_id'])
                    ->condition('part.partner_name', $query_group_name);
        $result_part = $query_part->execute()->fetchAll();          
        $exist_partner_id = $result_part[0]->partner_id;

        if ($exist_partner_id) {
          // Initialize the main query (assuming you want to select from 'employees' table with alias 'e')
          $query = $this->externaldatabase->select('partner', 'p')
            ->fields('p',['partner_id']);

          $all_related_partners = [];
          $this->fetchRelatedPartners($exist_partner_id, $all_related_partners);
          $all_related_partners[] = $exist_partner_id;
          $query->condition('p.partner_id', $all_related_partners, 'IN'); 

          // Execute the query and fetch results
          $results2 = $query->execute()->fetchAll();

          $userdata = [];
          $u_billing_entity_id = [];
          foreach ($results2 as $key2 => $data2) {
            $queryp = $this->externaldatabase->query("select partner_name from partner where partner_id=".$data2->partner_id);
            $resultp = $queryp->fetchAll();
            foreach ($resultp as $keyp => $datap) {
              $partner_name = $datap->partner_name;
            }

            $query4 = $this->defaultdatabase->query("select id, label from groups_field_data");
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
        } //if specifi partner exist
      } //if group name exist
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }

    $rowPiece = $this->pagerArray($userdata, 20);
    //pagination removed data saving
    foreach ($rowPiece as $key=>$name_data) {     
        $row = [
            '#attributes'=>[
                'class' => ['draggable'],
            ],

            "partner_name"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data['partner_name'],
                "#disabled" => true,
                "#size" => 25
            ],

            "available_credit_release"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data['credit_release'],
                '#options' => $name_data->credit_release,
                "#disabled" => true,
                '#attributes' => ['style' => 'width: 5em']
            ],
            "available_credit_reset"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data['credit_reset'],
                '#options' => $name_data->credit_reset,
                "#disabled" => true,
                '#attributes' => ['style' => 'width: 5em']
            ],
            "credit_release"=>[
                "#type"             => "number",
                "#default_value"    => 0,
                "#min"              => 0,
                '#attributes' => ['style' => 'width: 5em']
            ],
            "credit_reset"=>[
                "#type"             => "number",
                "#default_value"    => 0,
                "#min"              => 0,
                '#attributes' => ['style' => 'width: 5em']
            ],           
        ];  
        $form['names'][$key] = $row;        
    }

    $form['save'] = [
      '#type' => 'button',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'class' => [
          'use-ajax',
          'drupal-user-save-button',
        ],
        'title' => t("Save"),
      ],
      '#ajax' => [
        'callback' => [$this, 'saveModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['previous'] = [
      '#type'  => 'submit',
      //'#submit' => [[$this, 'resetForm']],
      '#value' => $this->t('Back'),
      '#button_type' => 'primary',
      '#default_value' => '',
      '#limit_validation_errors' => [], //no validation for back button
      '#attributes' => [
          'class' => ['custom-reset', 'drupal-user-save-button'],
          'title' => $this->t("Back"),
          'onclick' => 'window.history.back(); return false;', // JavaScript function to go back
      ],
    ];

    // Finally add the pager.
    $form['pager'] = array(
      '#type' => 'pager'
    );

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    $this->session->remove('var_partner_name');
    return $form;
  }

  /**
  * Returns pager array.
  */
  public function pagerArray($items, $itemsPerPage) {
    // Get total items count.
    $total = count($items);
    // Get the number of the current page.
    $currentPage = $this->pagermanager->createPager($total, $itemsPerPage)->getCurrentPage();
    // Split an array into chunks.
    $chunks = array_chunk($items, $itemsPerPage);
    // Return current group item.
    $currentPageItems = $chunks[$currentPage];
    return $currentPageItems;
  }

  public function filterForm(array &$form, FormStateInterface $form_state) {    
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
  }

  public function previousForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
  
  /**
  * Active AJAX callback handler that displays any errors or a success message.
  */
  public function saveModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
        $names = $form_state->getValue("names");
        $user = User::load(\Drupal::currentUser()->id());
        $cgroup = $user->field_group_type->getValue(); //current user group load
        $cgroup_id = $cgroup[0]['target_id']; //current user group id
        $current_group = Group::load($cgroup_id);
        $current_group_label = $current_group->label();
        $release_credit = [];
        $reset_credit = [];
        try {
            foreach ($names as $key => $value) {
              //Insert          
              $query = $this->defaultdatabase->query("select id, label from groups_field_data");
              $result4 = $query->fetchAll();
              foreach ($result as $key => $data) {
                //if group name and partner name 
                if (strtolower($data->label) == strtolower($value['partner_name'])) {
                    $group_id = $data->id;
                } 
              }

              if ($group_id != $cgroup_id) { //current group should not be count
                $group = Group::load($group_id);
                $group_release_value =$value['credit_release'];
                $group_reset_value =$value['credit_reset'];
                $release_credit[] = $group_release_value;
                $reset_credit[]  = $group_reset_value;               
              }              
            } //for
        
            $total_release_credit =  array_sum($release_credit);
            $total_reset_credit =  array_sum($reset_credit);
            
            $current_group_credit_release = $current_group->get('field_credit_release')->value;
            $current_group_credit_reset = $current_group->get('field_credit_reset')->value;

            // Initialize variables for the modal
            $title = '';
            $content = '';

            //RELEASE
            if ($total_release_credit <= $current_group_credit_release) {
              //release start
              $names4 = $form_state->getValue("names");
              foreach ($names4 as $key4 => $value4) {         
                $query44 = $this->defaultdatabase->query("select id, label from groups_field_data");
                $result44 = $query44->fetchAll();
                foreach ($result44 as $key44 => $data44) {                 
                  //if group name and partner name 
                  if (strtolower($data44->label) == strtolower($value4['partner_name'])) {
                      $group_id4 = $data44->id;
                  } 
                }

                if($group_id4 != $cgroup_id) { //should not be current groupid
                  $group4 = Group::load($group_id4);
                  $group_release_value4 = $group4->get('field_credit_release')->value;
                  $field_credit_release_value4 = $group_release_value4 + $value4['credit_release'];
                  $current_group = Group::load($cgroup_id);

                  $current_group_credit_release = $current_group->get('field_credit_release')->value;

                  $update_current_credit_release4 = $current_group_credit_release - $value4['credit_release'];

                  $current_group->set('field_credit_release', $update_current_credit_release4);
                  $current_group->save();

                  $group4->set('field_credit_release', $field_credit_release_value4);
                  $group4->save();

                  //credit allotment history start                  
                  if ($value4['credit_release']) {
                    $schema = \Drupal::database()->schema();
                    if ($schema->tableExists('credit_allotment_history')) {
                      $querycah = \Drupal::database()->insert('credit_allotment_history')
                                                    ->fields([
                                                      'from_partner_id',
                                                      'to_partner_id',
                                                      'operation_type',
                                                      'credit_amount',
                                                      'remarks',
                                                      'uid',
                                                      'timestamp'
                                                    ])
                                                    ->values(array(
                                                      $cgroup_id,
                                                      $group_id4,
                                                      'eSIM Release credit allotted',
                                                      $value4['credit_release'],
                                                      'RELEASE ALLOTMENT',
                                                      $user->id(),
                                                      REQUEST_TIME
                                                    ))
                                                    ->execute();
                    }
                  }
                  //$resultcah = $querycah->fetchAll();
                  //credit allotment history end
                }
              }
              $title = $this->t('Success'); // Translate the 'Success' title
              $content .= "<h2>" . $this->t('Release Credit:') . "</h2><p>" . $this->t('Release credits have been sent successfully.') . "</p><br/>";
              //release end
            } else {
              // Append error message for release credits
              $title = $this->t('Error'); // Translate the title 'Error'
              $content .= "<h2>" . $this->t('Release Credit') . "</h2><p>" . $this->t('There are not enough release credits to complete this transaction.') . "</p><br>";

            }

            //RESET
            if ($total_reset_credit <= $current_group_credit_reset) {
              //Reset start
              $names4 = $form_state->getValue("names");
              foreach ($names4 as $key4 => $value4) {         
                $query44 = $this->defaultdatabase->query("select id, label from groups_field_data");
                $result44 = $query44->fetchAll();
                foreach ($result44 as $key44 => $data44) {                 
                  //if group name and partner name 
                  if (strtolower($data44->label) == strtolower($value4['partner_name'])) {
                      $group_id4 = $data44->id;
                  } 
                }

                if($group_id4 != $cgroup_id) { //should not be current groupid
                  $group4 = Group::load($group_id4);
                  $group_reset_value4 = $group4->get('field_credit_reset')->value;
                  $field_credit_reset_value4 = $group_reset_value4 + $value4['credit_reset'];
                  $current_group = Group::load($cgroup_id);

                  $current_group_credit_reset = $current_group->get('field_credit_reset')->value;

                  $update_current_credit_reset4 = $current_group_credit_reset - $value4['credit_reset'];

                  $current_group->set('field_credit_reset', $update_current_credit_reset4);
                  $current_group->save();

                  $group4->set('field_credit_reset', $field_credit_reset_value4);
                  $group4->save();

                  //credit allotment history start
                  if ($value4['credit_reset']) {
                    $schema = \Drupal::database()->schema();
                    if ($schema->tableExists('credit_allotment_history')) {
                        $querycah = \Drupal::database()->insert('credit_allotment_history')
                                    ->fields([
                                      'from_partner_id',
                                      'to_partner_id',
                                      'operation_type',
                                      'credit_amount',
                                      'remarks',
                                      'uid',
                                      'timestamp'
                                    ])
                                    ->values(array(
                                      $cgroup_id,
                                      $group_id4,
                                      'eSIM Reset credit allotted',
                                      $value4['credit_reset'],
                                      'RESET ALLOTMENT',
                                      $user->id(),
                                      REQUEST_TIME
                                    ))
                                    ->execute();
                    }
                  }
                  //$resultcah = $querycah->fetchAll();
                  //credit allotment history end
                }
              }
              $title = $this->t('Success'); // Translate the 'Success' title
              $content .= "<h2>" . $this->t('Reset Credit:') . "</h2><p>" . $this->t('Reset credits have been sent successfully.') . "</p><br/>";

              //Reset end
            } else {
              // Append error message for reset credits
              $title = $this->t('Error'); // Translate the title 'Error'
              $content .= "<h2>" . $this->t('Reset Credit') . "</h2><p>" . $this->t('There are not enough reset credits to complete this transaction.') . "</p><br>";

            }

            // Define options for the modal
            $optionss = [
                'buttons' => [
                    'button1' => [
                        'text' => $this->t('Ok'),
                        'id' => 'close-button',
                        'onclick' => 'window.open("/credit-allotment?group_name='.$_GET['group_name'].'","_self")',
                    ],
                ],
                'width' =>600
            ];  

        } catch (\Exception $e) {
            $title = 'Exception';
            $content = $e->getMessage();
            $optionss = [
              'buttons' => [
                  'button1' => [
                    'text' => $this->t('Ok'),
                    'id' => 'close-button',
                    'onclick' => 'window.open("/credit-allotment?group_name='.$_GET['group_name'].'","_self")',
                  ],                  
              ],
              'width' =>600
            ];
        }        
        $response->addCommand(new OpenModalDialogCommand($title, $content, $optionss));
    } //else
    return $response;
  } //savemodalajax end
}
