<?php

namespace Drupal\mydata\Form;

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
class UserBillingEntityId extends FormBase {
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

  public function __construct(Connection $externalconnection, PagerManagerInterface $pagermanager, SessionInterface $session) {
    $this->externaldatabase = $externalconnection;
    $this->pagermanager = $pagermanager;
    $this->session = $session;
  }

  public static function create(ContainerInterface $container) {
    $externalconnection = $container->get('mydata.external_database');
    $pagermanager = $container->get('pager.manager');
    $session = $container->get('session');
    return new static($externalconnection,$pagermanager,$session);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'user_billing_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $query3 = $this->externaldatabase->query("select partner_id, partner_name from partner order by partner_name");
    $result3 = $query3->fetchAll();
    $sim_partner_id = [];
    foreach ($result3 as $key3 => $data3) {
      $sim_partner_id[0] = "--Select--";
      $sim_partner_id[$data3->partner_id] = $data3->partner_name;
    }
   
    $form['form']['filters'] = [
    '#type'  => 'details',
    '#title' => $this->t('Search / Options'),
    '#open'  => false,
    '#attributes' => array('class' => array('fa'),'title' => t("Enter Search value. Select Column to search for value. Press Filter."))
    ];

    $form['form']['filters']['text'] = [
      '#title'         => $this->t('Search Value'),
      '#type'          => 'search',
      '#attributes' => array('class' => array('custom-search'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['sim_partner'] = [
      '#title'         => $this->t('Sim Partner'),
      '#type'          => 'select',
      '#empty_value'   => 'none',
      '#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => $sim_partner_id,
      '#default_value' => 'none',
      '#attributes' => array('id' => array('custom-simpartner-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['column'] = [
      '#title'         => $this->t('Search Column'),
      '#type'          => 'select',
      #'#empty_value'   => 'none',
      #'#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => ['drupal_user'=>'Username','sim_partner_id'=>'Sim Partner'],
      '#default_value' => 'none',
      '#attributes' => array('id' => array('custom-header-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['actions'] = [
      '#type'       => 'actions'
    ];

    $form['form']['filters']['actions']['submit'] = [
      '#type'  => 'submit',
      '#submit' => [[$this, 'filterForm']],
      '#value' => $this->t('Search'),
      '#button_type' => 'primary',
      '#default_value' => '',
      '#limit_validation_errors' => [], //no validation for back button
      '#attributes' => array('class' => array('custom-filter-btn-billing-entity-id'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['actions']['button2'] = [
      '#type'  => 'submit',
      '#submit' => [[$this, 'resetForm']],
      '#value' => $this->t('Reset'),
      '#button_type' => 'primary',
      '#default_value' => '',
      '#limit_validation_errors' => [], //no validation for back button
      '#attributes' => array('class' => array('custom-reset'),'title' => t("Reset search values and display all records. "))
    ];

    $form['form']['filters']['actions']['masquerade'] = [
      '#type' => 'submit',
      '#value' => $this->t('Masquerade'),
      '#submit' => [[$this, 'Masquerade']],
      '#attributes' => array('class' => array('masquerade'),'title' => t("Masquerade"))
    ];

    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['names'] = [
      "#type"=>"table",
      "#tree"=>true,
      '#empty' => t('No users assigned Partner '.$this->session->get('var_partner_name')."."),
      '#header' =>[
        ["data"=>t("Username"),],
        ["data"=>t("Name"),],
        ["data"=>t("Sim Partner(Prod)"),],
        ["data"=>t("Sim Partner(Preprod)"),],
      ],
      '#prefix'      => "<div id='table-wrapper' class='col-sm-12'><h1 class='uppar-case'>Partner Assignment</h1>",
      '#suffix'      => '</div>',
      '#attributes' => array('class' => array('custom-table'))
    ];

    try {    
      if ($this->session->get('field')) {
        //drupal_user    
        $query2 = $this->externaldatabase->query("select drupal_user,name,sim_partner_id,sim_partner_id_preprod from drupal_user where drupal_user != 'default_template' and ".$this->session->get('column')." like '%".$this->session->get('field')."%' order by drupal_user asc");
        $result2 = $query2->fetchAll();
        $userdata = [];
        $u_billing_entity_id = [];
        foreach ($result2 as $key2 => $data2) {
          $userdata[] = $data2;
        }
      } else {
        //drupal_user    
        $query2 = $this->externaldatabase->query("select drupal_user,name,sim_partner_id,sim_partner_id_preprod from drupal_user where drupal_user != 'default_template' order by drupal_user asc");
        $result2 = $query2->fetchAll();
        $userdata = [];
        $u_billing_entity_id = [];
        foreach ($result2 as $key2 => $data2) {
          $userdata[] = $data2;
        }
      } 
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }

    $rowPiece = $this->pagerArray($userdata, 10);
    //pagination removed data saving
    foreach ($rowPiece as $key=>$name_data) {        
        $row = [
            '#attributes'=>[
                'class' => ['draggable'],
            ],

            "username"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data->drupal_user,
                "#disabled" => true,
                "#size" => 25
            ],

            "name"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data->name,
                "#disabled" => true,
                "#size" => 25
            ],

            "sim_partner_id_prod"=>[
                "#type"             => "select",
                "#default_value"    => $name_data->sim_partner_id,
                '#options' => $sim_partner_id,
                '#attributes' => ['style' => 'width: 15em']
            ],
            "sim_partner_id_preprod"=>[
                "#type"             => "select",
                "#default_value"    => $name_data->sim_partner_id_preprod,
                '#options' => $sim_partner_id,
                '#attributes' => ['style' => 'width: 15em']
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
        '#submit' => [[$this, 'resetForm']],
        '#value' => $this->t('Previous'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#limit_validation_errors' => [], //no validation for back button
        '#attributes' => array('class' => array('custom-reset','drupal-user-save-button'),'title' => t("Reset search values and display all records. "))
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
    if ($form['form']['filters']['column']['#value'] == 'drupal_user') {
      $this->session->set('field', $form['form']['filters']['text']['#value']);
      $this->session->set('column', $form['form']['filters']['column']['#value']);
    } elseif ($form['form']['filters']['column']['#value'] == 'sim_partner_id_prod') {
      $this->session->set('field', $form['form']['filters']['sim_partner']['#value']);
      $this->session->set('column', $form['form']['filters']['column']['#value']);
    } elseif ($form['form']['filters']['column']['#value'] == 'sim_partner_id') {
      $this->session->set('field', $form['form']['filters']['sim_partner']['#value']);
      $this->session->set('column', $form['form']['filters']['column']['#value']);
      $queryp = $this->externaldatabase->query("select partner_name from partner where partner_id=".$form['form']['filters']['sim_partner']['#value']."");
      $resultp = $queryp->fetchAll();
      $this->session->set('var_partner_name', $resultp[0]->partner_name);
    } else if ($form['form']['filters']['column']['#value'] == 'none') {
      
    } else {
      $this->session->set('field', $form['form']['filters']['text']['#value']);
      $this->session->set('column', $form['form']['filters']['column']['#value']);
    }    
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->session->remove('field');
    $this->session->remove('column');  
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
        $user = \Drupal::currentUser();    
        $username = $user->getDisplayName();
        foreach ($names as $key => $value) {
          //Insert
          try {  
              $query3 = $this->externaldatabase->query("update drupal_user set name = '".$value['name']."',sim_partner_id = ".$value['sim_partner_id_prod'].",sim_partner_id_preprod = ".$value['sim_partner_id_preprod']." where drupal_user = '".$value['username']."'");
              //update user in drupal start
              // Load the user by email.
              $users = \Drupal::entityTypeManager()
                ->getStorage('user')
                ->loadByProperties(['mail' => $value['username']]);
              // Check if the user exists.
              if (!empty($users)) {
                // Get the first user matching the email.
                $user = reset($users);
                if ($user instanceof User) {
                  $queryp = $this->externaldatabase->select('partner', 'p')
                            ->fields('p', ['partner_name'])
                            ->condition('p.partner_id', $value['sim_partner_id_prod']);
                  $resultp = $queryp->execute()->fetchAll();
                  if (!empty($resultp) && isset($resultp[0]->partner_name)) {
                     $groups = Group::loadMultiple();
                      foreach ($groups as $group) {
                        $label = trim($group->label());
                        if($label ==  trim($resultp[0]->partner_name)){
                          $group_id = $group->id();
                          break;
                        } else {
                          $group_id = 0;
                        }
                      }                      
                  }

                  // Set the new value for the 'field_group_type' field.
                  $user->set('field_group_type', $group_id);

                  // Save the user entity.
                  $user->save();
                }
              }
              //update user in drupal end
          } catch (\Exception $e) {
            \Drupal::messenger()->addError($e->getMessage());
          }
        }//for

        $title = 'Success';
        $content = "User data successfully updated";
        $optionss = [
          'buttons' => [
              'button1' => [
                'text' => $this->t('Ok'),
                'id' => 'close-button',
                'onclick' => 'window.open("/user-billing-entity-id?page='.$_GET['page'].'","_self")',
              ],
            ],
        ];
        $response->addCommand(new OpenModalDialogCommand($title, $content, $optionss));
    } //else
    return $response;
  } //savemodalajax end

  //masquerade
  public function Masquerade(array $form, FormStateInterface $form_state) {
    $url = Url::fromRoute('masquerade.block');
    return $form_state->setRedirectUrl($url);
    /*
    $user_id=175;
    $target_account = \Drupal\user\Entity\User::load($user_id);   
    $form_state->setValue('masquerade_target_account', $target_account);
    \Drupal::service('masquerade')->switchTo($form_state->getValue('masquerade_target_account'));
    */
  }//masquerade end
}
