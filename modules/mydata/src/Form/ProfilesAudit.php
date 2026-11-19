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

/**
 * SendToDestinationsForm class.
 */
class ProfilesAudit extends FormBase {
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
    return 'user_billing_entity_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    //$this->session->remove('webform_field');
    //$this->session->remove('webform_column');  
    // Create a query object.
    $conn = \Drupal\Core\Database\Database::getConnection('default', 'default');
    
    //echo "<pre>";
    //print_r($result);
    //exit;
    $query3 = $this->externaldatabase->query("select partner_id, partner_name from partner order by partner_name");
    $result3 = $query3->fetchAll();
    $sim_partner_id = [];
    foreach ($result3 as $key3 => $data3) {
      $sim_partner_id[0] = "--NA--";
      $sim_partner_id[$data3->partner_id] = $data3->partner_name;
    }

    $query4 = $conn->query("select id, label from groups_field_data");
    $result4 = $query4->fetchAll();
    $groups = [];
    foreach ($result4 as $key4 => $data4) {
      $groups[0] = "--NA--";
      $groups[$data4->id] = $data4->label;
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

    $form['form']['filters']['group_type'] = [
      '#title'         => $this->t('Group Type'),
      '#type'          => 'select',
      //'#empty_value'   => 'none',
      //'#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => $groups,
      '#default_value' => 'none',
      '#attributes' => array('id' => array('custom-grouppartner-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['partner_name'] = [
      '#title'         => $this->t('Partner Name'),
      '#type'          => 'select',
      //'#empty_value'   => 'none',
      //'#empty_option'  => '- None -',
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
      '#options'       => ['mail'=>'Username','wsd_sim_profile_name'=>'Sim Profile Name', 'wsd_group_type'=>'Group Type','wsd_partner_name'=>'Partner Name' ,'created'=>'Created'],
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

    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['names'] = [
      "#type"=>"table",
      "#tree"=>true,
      '#empty' => t('No users assigned Partner '.$this->session->get('var_partner_name1')."."),
      '#header' =>[
        /*["data"=>t("SID"),],*/
        ["data"=>t("Username"),],
        ["data"=>t("Profile"),],
        ["data"=>t("Group"),],
        ["data"=>t("Partner"),],
        ["data"=>t("Created Date"),],
      ],
      '#prefix'      => "<div id='table-wrapper' class='col-sm-12'><h1 class='uppar-case'>Profiles Audit</h1>",
      '#suffix'      => '</div>',
      '#attributes' => array('class' => array('custom-table'))
    ];

    

    try {    
      if ($this->session->get('webform_field')) {
      //echo "field:".$this->session->get('webform_column');
      //echo "column:".$this->session->get('webform_field');
      //exit;
        $query = $conn->select('webform_submission', 'ws');
        $query->fields('ws', ['sid']);
        $query->fields('ufd', ['mail']);
        $query->fields('wsd_sim_profile_name', ['value'], 'sim_profile_name');
        $query->fields('wsd_group_type', ['value'], 'group_type');
        $query->fields('wsd_partner_name', ['value'], 'partner_name');
        $query->fields('ws', ['created']);
        $query->leftJoin('webform_submission_data', 'wsd_group_type', 'ws.sid = wsd_group_type.sid AND wsd_group_type.name = :group_type_name', [':group_type_name' => 'group_type']);
        $query->leftJoin('webform_submission_data', 'wsd_partner_name', 'ws.sid = wsd_partner_name.sid AND wsd_partner_name.name = :partner_name', [':partner_name' => 'partner_name']);
        $query->leftJoin('webform_submission_data', 'wsd_sim_profile_name', 'ws.sid = wsd_sim_profile_name.sid AND wsd_sim_profile_name.name = :sim_profile_name', [':sim_profile_name' => 'sim_profile_name']);
        $query->leftJoin('users_field_data', 'ufd', 'ws.uid = ufd.uid');
        $query->condition('ws.webform_id', 'sim_profile');
        $query->condition($this->session->get('webform_column'), '%'.$this->session->get('webform_field').'%', 'like');

        // Execute the query.
        $result = $query->execute()->fetchAll();
        $userdata = [];
        foreach ($result as $key => $data) {
          $userdata[] = $data;
        }
      } else {
        $query = $conn->select('webform_submission', 'ws');
        $query->fields('ws', ['sid']);
        $query->fields('ufd', ['mail']);
        $query->fields('wsd_sim_profile_name', ['value'], 'sim_profile_name');
        $query->fields('wsd_group_type', ['value'], 'group_type');
        $query->fields('wsd_partner_name', ['value'], 'partner_name');
        $query->fields('ws', ['created']);
        $query->leftJoin('webform_submission_data', 'wsd_group_type', 'ws.sid = wsd_group_type.sid AND wsd_group_type.name = :group_type_name', [':group_type_name' => 'group_type']);
        $query->leftJoin('webform_submission_data', 'wsd_partner_name', 'ws.sid = wsd_partner_name.sid AND wsd_partner_name.name = :partner_name', [':partner_name' => 'partner_name']);
        $query->leftJoin('webform_submission_data', 'wsd_sim_profile_name', 'ws.sid = wsd_sim_profile_name.sid AND wsd_sim_profile_name.name = :sim_profile_name', [':sim_profile_name' => 'sim_profile_name']);
        $query->leftJoin('users_field_data', 'ufd', 'ws.uid = ufd.uid');
        $query->condition('ws.webform_id', 'sim_profile');

        // Execute the query.
        $result = $query->execute()->fetchAll();
        $userdata = [];
        foreach ($result as $key => $data) {
          $userdata[] = $data;
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

            /*
            "sid"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data->sid,
                "#disabled" => true,
                "#size" => 25
            ],
            */

            "email"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data->mail,
                "#disabled" => true,
                "#size" => 25
            ],

            "prifile"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data->value,
                "#disabled" => true,
                "#size" => 25
            ],

            "group_type"=>[
                "#type"             => "select",
                "#default_value"    => $name_data->wsd_group_type_value,
                "#disabled" => true,
                '#options' => $groups,
                '#attributes' => ['style' => 'width: 15em']
            ],

            "partner_name"=>[
                "#type"             => "select",
                "#default_value"    => $name_data->wsd_partner_name_value,
                "#disabled" => true,
                '#options' => $sim_partner_id,
                '#attributes' => ['style' => 'width: 15em']
            ],
            "created"=>[
                "#type"             => "textfield",
                "#default_value"    => date("Y-m-d H:i:s", $name_data->created),
                "#disabled" => true,
                "#size" => 25
            ],         
        ];        
        $form['names'][$key] = $row;        
    }

    /*
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
    */

      $form['actions']['previous'] = [
        '#type' => 'submit',
        '#attributes' => array(
          'onclick' => 'window.history.back();return false;',
          'class' => array('custom-reset','drupal-user-save-button'),
        ),
        '#submit' => [[$this, 'previousForm']],
        '#value' => 'Previous',
        '#limit_validation_errors' => [],        
    ];

      // Finally add the pager.
      $form['pager'] = array(
        '#type' => 'pager'
      );

      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      $this->session->remove('var_partner_name1');
      $this->session->remove('webform_field');
      $this->session->remove('webform_column');
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
    if ($form['form']['filters']['column']['#value'] == 'mail') {
      $this->session->set('webform_field', $form['form']['filters']['text']['#value']);
      $this->session->set('webform_column', $form['form']['filters']['column']['#value']);
    } elseif ($form['form']['filters']['column']['#value'] == 'wsd_sim_profile_name') {
      $this->session->set('webform_field', $form['form']['filters']['text']['#value']);
      $this->session->set('webform_column', 'wsd_sim_profile_name.value');
    } elseif ($form['form']['filters']['column']['#value'] == 'wsd_group_type') {
      $this->session->set('webform_field', $form['form']['filters']['group_type']['#value']);
      $this->session->set('webform_column', 'wsd_group_type.value');
    } elseif ($form['form']['filters']['column']['#value'] == 'wsd_partner_name') {
      $this->session->set('webform_field', $form['form']['filters']['partner_name']['#value']);
      $this->session->set('webform_column', 'wsd_partner_name.value');
      $queryp = $this->externaldatabase->query("select partner_name from partner where partner_id=".$form['form']['filters']['partner_name']['#value']."");
      $resultp = $queryp->fetchAll();
      $this->session->set('var_partner_name1', $resultp[0]->partner_name);
    } else if ($form['form']['filters']['column']['#value'] == 'none') {
      
    } else {
      $this->session->set('weform_field', $form['form']['filters']['text']['#value']);
      $this->session->set('webform_column', $form['form']['filters']['column']['#value']);
    }    
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->session->remove('webform_field');
    $this->session->remove('webform_column');  
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
