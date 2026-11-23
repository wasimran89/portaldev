<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\user\Entity\User;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\group\Entity\Group;
/**
 * SendToDestinationsForm class.
 */
class UserMgmt extends FormBase {
  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $instance = parent::create($container);
    $instance->pagerManager = $container->get('pager.manager');
    return $instance;
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'modal_form_example_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $session = \Drupal::request()->getSession();
    $groups = Group::loadMultiple();
    $grouplist= [];
    foreach($groups as $group){
      //$group->get('status')->value
      $id = $group->id();
      $label = $group->label();
      $grouplist[0] = "--None--";
      $grouplist[$id] = $label;
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

    $form['form']['filters']['group'] = [
      '#title'         => $this->t('Group'),
      '#type'          => 'select',
      #'#empty_value'   => 'none',
      #'#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => $grouplist,
      '#default_value' => 'none',
      '#attributes' => array('id' => array('custom-group-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['status'] = [
      '#title'         => $this->t('Status'),
      '#type'          => 'select',
      '#empty_value'   => 'none',
      '#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => ['1'=>'Active','0'=>'Blocked'],
      '#default_value' => 'none',
      '#attributes' => array('id' => array('custom-status-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
    ];

    $form['form']['filters']['column'] = [
      '#title'         => $this->t('Search Column'),
      '#type'          => 'select',
      #'#empty_value'   => 'none',
      #'#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => ['mail'=>'Username','name'=>'Name','field_group_type'=>'Group','status'=>'Status'],
      '#default_value' => 'none',
      '#attributes' => array('id' => array('user-mgmt-header-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button"))
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

    $addnewurl   = Url::fromUserInput('/group/add');
    $form['form']['filters']['actions']['new'] = [
      '#type' => 'link',
      '#title' => $this->t('New Tenant'),
      "#weight" => 1,
      '#url' => $addnewurl,
      '#attributes' => ['target' => '_self', 'class' => 'add-new button button--primary js-form-submit form-submit','title' => t("Add New")],
    ];

    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['names'] = [
      "#type"=>"table",
      "#tree"=>true,
      '#empty' => t('No data found'),
      '#header' =>[
        ["data"=>t("Username"),],
        ["data"=>t("Name"),],
        ["data"=>t("Group"),],
        ["data"=>t("Status"),],
      ],
      '#prefix'      => "<div id='table-wrapper' class='col-sm-12'><h1 class='uppar-case'>User Management</h1>",
      '#suffix'      => '</div>',
      '#attributes' => array('class' => array('custom-table'))
    ];

    try {    
      if ($session->get('drupal_field') != '') {
        $column = $session->get('drupal_column');
        $value = $session->get('drupal_field');
        if ($column == 'field_group_type') {
          $ids = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition($column, $value, '=')
            ->execute();
        } else {
          $ids = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            ->condition($column, '%' . $value . '%', 'LIKE')
            ->execute();
        }
        $users = User::loadMultiple($ids);
        $userlist=[];
        foreach ($users as $user) {
            $userlist[] = array(
              'mail'=>$user->get('mail')->getString(),
              'name'=>$user->get('name')->getString(),
              'group'=>$user->get('field_group_type')->getString(),
              'status'=>$user->get('status')->getString()              
            );
        }
      } else {
        $ids = \Drupal::entityQuery('user')
            ->accessCheck(TRUE)
            //->condition('status', 1)
            //->condition('roles', 'administrator')
            ->execute();
        $users = User::loadMultiple($ids);
        $userlist=[];
        foreach($users as $user){
            $userlist[] = array(
              'mail'=>$user->get('mail')->getString(),
              'name'=>$user->get('name')->getString(),
              'group'=>$user->get('field_group_type')->getString(),
              'status'=>$user->get('status')->getString()              
            );
        }
      }
    } catch (\Exception $e) {
      \Drupal::messenger()->addError($e->getMessage());
    }

    $rowPiece = $this->pagerArray($userlist, 10);
    //pagination removed data saving
    
    foreach ($rowPiece as $key=>$name_data){        
        $row = [
            '#attributes'=>[
                'class' => ['draggable'],
            ],

            "username"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data['mail'],
                "#disabled" => true,
                "#size" => 25
            ],

            "name"=>[
                "#type"             => "textfield",
                "#default_value"    => $name_data['name'],
                "#disabled" => true,
                "#size" => 25
            ],

            "group"=>[
                "#type"             => "select",
                "#default_value"    => $name_data['group'],
                '#options' => $grouplist,
                '#attributes' => ['style' => 'width: 15em']
            ],

            "status"=>[
                "#type"             => "select",
                "#default_value"    => $name_data['status'],
                '#options' => ['1'=>'Active','0'=>'Blocked',],
                '#attributes' => ['style' => 'width: 15em']
            ]        
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
    $form['pager'] = [
      '#type' => 'pager'
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    //$session->remove('drupal_field');
    //$session->remove('drupal_column');  
    return $form;
  }

  /**
 * Returns pager array.
 */
  public function pagerArray($items, $itemsPerPage) {
    // Get total items count.
    $total = count($items);
    // Get the number of the current page.
    $currentPage = $this->pagerManager->createPager($total, $itemsPerPage)->getCurrentPage();
    // Split an array into chunks.
    $chunks = array_chunk($items, $itemsPerPage);
    // Return current group item.
    $currentPageItems = $chunks[$currentPage];
    return $currentPageItems;
  }

  public function filterForm(array &$form, FormStateInterface $form_state) {
    $session = \Drupal::request()->getSession();
    if ($form['form']['filters']['column']['#value'] == 'mail') {
      $session->set('drupal_field', $form['form']['filters']['text']['#value']);
      $session->set('drupal_column', $form['form']['filters']['column']['#value']);
    }
    elseif ($form['form']['filters']['column']['#value'] == 'name') {
      $session->set('drupal_field', $form['form']['filters']['text']['#value']);
      $session->set('drupal_column', $form['form']['filters']['column']['#value']);
    }
    elseif ($form['form']['filters']['column']['#value'] == 'field_group_type') {
      $session->set('drupal_field', $form['form']['filters']['group']['#value']);
      $session->set('drupal_column', $form['form']['filters']['column']['#value']);
    }
    elseif ($form['form']['filters']['column']['#value']== 'status') {
      $session->set('drupal_field', $form['form']['filters']['status']['#value']);
      $session->set('drupal_column', $form['form']['filters']['column']['#value']);
    } else {
      $session->set('drupal_field', $form['form']['filters']['text']['#value']);
      $session->set('drupal_column', $form['form']['filters']['column']['#value']);
    }    
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
    $session = \Drupal::request()->getSession();
    $session->remove('drupal_field');
    $session->remove('drupal_column');  
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
        foreach ($names as $key => $value) {
          try {  
                $ids = \Drupal::entityQuery('user')
                    ->accessCheck(TRUE)
                    ->condition('mail',$value['username'])
                    ->execute();
                $users = User::loadMultiple($ids);
                foreach ($users as $user) {
                  $user->set('status', $value['status']);
                  $user->set('field_group_type', $value['group']);
                  $user->save();
                }
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
                'onclick' => 'window.open("/user-mgmt?page='.$_GET['page'].'","_self")',
              ],
            ],
        ];
        $response->addCommand(new OpenModalDialogCommand($title, $content, $optionss));
    }//else
    return $response;
  }//savemodalajax end
}
