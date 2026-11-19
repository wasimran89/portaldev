<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Drupal\user\Entity\User;
/**
 * SendToDestinationsForm class.
 */
class SimLineView extends FormBase {
  protected $currentUser;
  protected $languageManager;
  /**
   * The default database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $defaultDatabase;

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;

  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $defaultConnection
   *   The default database connection.
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  public function __construct(AccountProxyInterface $current_user, Connection $defaultConnection, Connection $externalConnection, LanguageManagerInterface $languageManager, SessionInterface $session, PagerManagerInterface $pagerManager) {
    $this->currentUser = $current_user;
    $this->defaultDatabase = $defaultConnection;
    $this->externalDatabase = $externalConnection;
    $this->languageManager = $languageManager;
    $this->session = $session;
    $this->pagerManager = $pagerManager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    // Create instances of the database connections.
    $current_user = $container->get('current_user');
    $defaultConnection = $container->get('database');
    $externalConnection = $container->get('mydata.external_database');
    $languageManager = $container->get('language_manager');
    $session = $container->get('session');
    $pagerManager = $container->get('pager.manager');

    // Return the controller object with the injected database connections.
    return new static($current_user, $defaultConnection, $externalConnection, $languageManager, $session,$pagerManager);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'sim_line_view_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $config = \Drupal::config('xai.settings');
    $base_path = $config->get('domain');
    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['form']['filters'] = [
      '#type'  => 'details',
      '#title' => $this->t('Search / Options'),
      '#open'  => false,
      '#attributes' => array('class' => array('fa'),'title' => t("Enter Search value, select the Column to search and click Search button."))
    ];

    $form['form']['filters']['text'] = [
      '#title'         => t('Search Value'),
      '#type'          => 'search',
      '#attributes'    => ['class' => ['custom-search']],
      '#states' => [
        'visible' => [
          ':input[name="column"]' => ['in' => ['iccid', 'imsi', 'msisdn', 'profile', 'orderid', 'locationid', 'simtype']],
        ],
      ],
    ];

    $query3 = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
    $result3 = $query3->fetchAll();
    $sim_partner_id = [];
    foreach ($result3 as $key3 => $data3) {
      $sim_partner_id[0] = "--Select--";
      $sim_partner_id[$data3->partner_id] = $data3->partner_name;
    }

    $form['form']['filters']['partner_id'] = [
      '#title'         => $this->t('Partner ID'),
      '#type'          => 'select',
      //'#empty_value'   => 'none',
      //'#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => $sim_partner_id,
      '#default_value' => 'none',
      '#attributes' => array('id' => array('custom-partnerid-colmn'),'title' => t("Enter the search value. Select a column to search. Press the Search Button")),
      '#states'        => [
        'visible' => [
          ':input[name="column"]' => ['value' => 'partner_id'],
        ],
      ],
    ];

    $form['form']['filters']['isactive'] = [
      '#title'         => t('Is Active'),
      '#type'          => 'select',
      '#empty_value'   => 'none',
      '#empty_option'  => '- None -',
      '#options'       => [
        '1' => t('YES'),
        '0' => t('NO'),
      ],
      '#default_value' => 'none',
      '#attributes'    => [
        'id'    => 'sim_line_view_isactivate',
        'title' => t('Select the is active to filter by.'),
      ],
      '#states'        => [
        'visible' => [
          ':input[name="column"]' => ['value' => 'isactive'],
        ],
      ],
    ];

    $form['form']['filters']['enabled'] = [
      '#title'         => t('Enabled'),
      '#type'          => 'select',
      '#empty_value'   => 'none',
      '#empty_option'  => '- None -',
      '#options'       => [
        '1' => t('YES'),
        '0' => t('NO'),
      ],
      '#default_value' => 'none',
      '#attributes'    => [
        'id'    => 'sim_line_view_enabled',
        'title' => t('Select the is suspend to filter by.'),
      ],
      '#states'        => [
        'visible' => [
          ':input[name="column"]' => ['value' => 'issuspend'],
        ],
      ],
    ];


    $form['form']['filters']['column'] = [
      '#title'         => t('Search Column'),
      '#type'          => 'select',
      '#empty_value'   => 'none',
      '#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => ["iccid"=>"iccid","imsi"=>"imsi","masterid"=>"masterid","partner_id"=>"partner_id", "profile"=>"profile","orderid"=>"orderid","locationid"=>"locationid","simtype"=>"simtype","isactive"=>"isactive","issuspend"=>"enabled"],
      '#default_value' => 'none',
      '#attributes' => array('id' => array('simlineview-header-colmn'),'title' => t("Enter Search value, select the Column to search and click Search button."))
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
      '#attributes' => array('class' => array('custom-filter-btn-billing-entity-id'),'title' => t("Enter Search value, select the Column to search and click Search button."))
    ];

    $form['form']['filters']['actions']['button2'] = [
      '#type'  => 'submit',
      '#submit' => [[$this, 'resetForm']],
      '#value' => $this->t('Reset'),
      '#button_type' => 'primary',
      '#default_value' => '',
      '#limit_validation_errors' => [], //no validation for back button
      '#attributes' => array('class' => array('custom-reset'),'title' => t("Reset"))
    ];
    //
    $user = User::load(\Drupal::currentUser()->id()); //current user load
    if ($user) {
      $group = $user->field_group_type->getValue(); //current user group load
      $type = $group[0]['target_id']; //current user group id

      $webform = \Drupal\webform\Entity\Webform::load('hss_profile');
      if ($webform->hasSubmissions()) {
        $query = \Drupal::entityQuery('webform_submission')
          ->condition('webform_id', 'hss_profile');
        $result = $query->execute();
        $submission_data = [];
        foreach ($result as $item) {
          $submission = \Drupal\webform\Entity\WebformSubmission::load($item);
          $submission_group = $submission->getData();
          if ($submission_group['group_type'] == $type) {          
            $submission_data[$submission_group['hss_udm_profile_name']] = $submission_group['hss_udm_profile_name'];
          }
        }
      }
    } else {
      $submission_data = ["No data found"=>"No data found"];
    }

    $form['form']['filters']['actions']['hss'] = [
      //'#title'         => 'Column',
      '#type'          => 'select',
      '#empty_value'   => 'none',
      '#empty_option'  => '- None -',
      '#size'          => 0,
      '#options'       => $submission_data,
      '#default_value' => 'none',
      '#attributes' => ['style' => 'width: 15em'],
      '#weight' => 15
    ];
    $form['form']['filters']['actions']['hssprofile'] = array(
      '#type'=>'submit',
      '#value' => t('Hss Profile'),
      '#weight' => 16,
      '#attributes' => array('title' => t("Hss Profile"))
    );

    
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    if ((in_array('administrator', $roles)) || (in_array('tenant_management', $roles))) {
      //partner
      $query3 = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
      $result3 = $query3->fetchAll();
      $sim_partner_id = [];
      foreach ($result3 as $key3 => $data3) {
        $sim_partner_id[0] = "--Select--";
        $sim_partner_id[$data3->partner_id] = $data3->partner_name;
      }

      //partner end
      $form['form']['filters']['actions']['partner'] = [
        //'#title'         => 'Column',
        '#type'          => 'select',
        '#size'          => 0,
        '#options'       => $sim_partner_id,
        '#default_value' => 'none',
        '#attributes' => ['style' => 'width: 15em'],
        '#weight' => 17
      ];

      $form['form']['filters']['actions']['partner_submit'] = array(
        '#type'=>'submit',
        '#submit' => [[$this, 'AssignPartner']],
        '#value' => t('Assign Partner'),
        '#weight' => 18,
        '#attributes' => array('title' => t("Assign Partner"))
      );
    }
    
    $form['form']['filters']['actions']['deactivate'] = array(
      '#type'=>'submit',
      '#value' => t('Deactivate'),
      '#submit' => [[$this, 'deactivateForm']],
      '#weight' => 19,
      '#attributes' => array('title' => t("Deactivate"))
    );

    $form['form']['filters']['actions']['suspend'] = array(
      '#type'=>'submit',
      '#value' => t('Suspend'),
      '#submit' => [[$this, 'suspendForm']],
      '#weight' => 20,
      '#attributes' => array('title' => t("Suspend"))
    );

    $form['form']['filters']['actions']['resume'] = array(
      '#type'=>'submit',
      '#value' => t('Resume'),
      '#submit' => [[$this, 'resumeForm']],      
      '#weight' => 21,
      '#attributes' => array('title' => t("Resume"))
    );

    $form['form']['filters']['actions']['locationtxt'] = [
      '#type'=>'textfield',
      "#size" => 15,
      '#weight' => 22,
      '#attributes' => array('title' => t("Location text"))
    ];

    $form['form']['filters']['actions']['location'] = array(
      '#type'=>'submit',
      '#value' => t('Location'),
      '#submit' => [[$this, 'locationForm']],
      '#weight' => 23,
      '#attributes' => array('title' => t("Location"))
    );

    $user_display_name = $user->getEmail();
    $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
    $resultuser = $queryuser->fetchAll();
    //child partner start
    $queryc = $this->externalDatabase->query("select count(*) from partner where parent_partner_id = {$resultuser[0]->sim_partner_id}");
    $resultc = $queryc->fetchAll();
    if ($resultc[0]->count) {
      $querypid = $this->externalDatabase->query("select partner_id from partner where parent_partner_id={$resultuser[0]->sim_partner_id}");
      $resultpid = $querypid->fetchAll();
      $partner_pid = [];
      foreach ($resultpid as $keypid => $datapid) {
        $partner_pid[] = $datapid->partner_id;
      }
    }
    //child partner end

    //grand parent partner start
    $queryc2 = $this->externalDatabase->query("select count(*) from partner where grand_parent_partner_id = {$resultuser[0]->sim_partner_id}");
    $resultc2 = $queryc2->fetchAll();
    if ($resultc2[0]->count) {
      $querypid2 = $this->externalDatabase->query("select partner_id from partner where grand_parent_partner_id={$resultuser[0]->sim_partner_id}");
      $resultpid2 = $querypid2->fetchAll();
      $partner_pid2 = [];
      foreach ($resultpid2 as $keypid2 => $datapid2) {
        $partner_pid2[] = $datapid2->partner_id;
      }
    }
    //grand parent partner end
    try {
        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        if (in_array('administrator', $roles)) { //Show all data for admin and tenant without filter
          if ($this->session->get('sim_field') != '') {
            //$query = $this->defaultDatabase->query("SELECT iccid,imsi,masterid,partner_id,profile,orderid,locationid,simtype,isactive,issuspend from lineview where ".$this->session->get('sim_column')."= '".$this->session->get('sim_field')."'");

            $sim_column = $this->session->get('sim_column');
            $sim_field = $this->session->get('sim_field');

            $query = $this->defaultDatabase->select('lineview', 'l')
            ->fields('l', ['iccid', 'imsi', 'masterid', 'partner_id', 'profile', 'orderid', 'locationid', 'simtype', 'isactive', 'issuspend']);
            //->condition($sim_column, $sim_field);
            if ($sim_column == 'partner_id') {
              $query->condition($sim_column, $sim_field);
            } else {
              $query->condition($sim_column, '%' . $sim_field . '%', 'LIKE');
            }

          } else {
            //$query = $this->defaultDatabase->query("SELECT iccid,imsi,masterid,partner_id,profile,orderid,locationid,simtype,isactive,issuspend from lineview");
            $query = $this->defaultDatabase->select('lineview', 'l');
            $query->fields('l', ['iccid', 'imsi', 'masterid', 'partner_id', 'profile', 'orderid', 'locationid', 'simtype', 'isactive', 'issuspend']);
          }
        } else { //Non admin user case
          if ($resultuser[0]->sim_partner_id) { //if partner assign
            if ($this->session->get('sim_field') != '') { //if filter session
              //$query = $this->defaultDatabase->query("SELECT iccid,imsi,masterid,partner_id,profile,orderid,locationid,simtype,isactive,issuspend from lineview where ".$this->session->get('sim_column')."= '".$this->session->get('sim_field')."' and partner_id='".$resultuser[0]->sim_partner_id."'");
              $sim_column = $this->session->get('sim_column');
              $sim_field = $this->session->get('sim_field');
              $partner_id = $resultuser[0]->sim_partner_id;
              $query = $this->defaultDatabase->select('lineview', 'l');
              $query->fields('l', ['iccid', 'imsi', 'masterid', 'partner_id', 'profile', 'orderid', 'locationid', 'simtype', 'isactive', 'issuspend']);
              //$query->condition($sim_column, $sim_field);
              if ($sim_column == 'partner_id') {
                $query->condition($sim_column, $sim_field);
              } else {
                $query->condition($sim_column, '%' . $sim_field . '%', 'LIKE');
              }
              $orCondition = $query->orConditionGroup()
              ->condition('l.partner_id', $partner_id, '=')
              ->condition('l.partner_id', $partner_pid, 'IN')
              ->condition('l.partner_id', $partner_pid2, 'IN');
              $query->condition($orCondition);
            } else {
              //$query = $this->defaultDatabase->query("SELECT iccid,imsi,masterid,partner_id,profile,orderid,locationid,simtype,isactive,issuspend from lineview where partner_id='".$resultuser[0]->sim_partner_id."'");
              $partner_id = $resultuser[0]->sim_partner_id;
              $query = $this->defaultDatabase->select('lineview', 'l');
              $query->fields('l', ['iccid', 'imsi', 'masterid', 'partner_id', 'profile', 'orderid', 'locationid', 'simtype', 'isactive', 'issuspend']);
              $orCondition = $query->orConditionGroup()
              ->condition('l.partner_id', $partner_id, '=')
              ->condition('l.partner_id', $partner_pid, 'IN')
              ->condition('l.partner_id', $partner_pid2, 'IN');
              $query->condition($orCondition);
            }
          } else {
              //$query = $this->defaultDatabase->query("SELECT iccid,imsi,masterid,partner_id,profile,orderid,locationid,simtype,isactive,issuspend from lineview where partner_id='-1000'");
              $query = $this->defaultDatabase->select('lineview', 'l')
              ->fields('l', ['iccid', 'imsi', 'masterid', 'partner_id', 'profile', 'orderid', 'locationid', 'simtype', 'isactive', 'issuspend'])
              ->condition('partner_id', -1000);
          }
        }

        //$result = $query->fetchAll();
        $result = $query->execute()->fetchAll();

    } catch (\Exception $e) {
      \Drupal::messenger()->addError($e);
    }
        
    $header_column = ["iccid"=>"iccid","imsi"=>"imsi","masterid"=>"masterid","partner_id"=>"partner_id","profile"=>"profile","orderid"=>"orderid","locationid"=>"locationid","simtype"=>"simtype","isactive"=>"isactive","issuspend"=>"issuspend"];

    if ($result) {
      $rows = [];
      foreach ($result as $key => $value) {   
        $rows[] = (array) $value;
        if ($value->partner_id) {
          $query4 = $this->externalDatabase->query("select partner_name from partner where partner_id={$value->partner_id}");
          $result4 = $query4->fetchAll();
          $rows[$key]['partner_id'] = $result4[0]->partner_name;
        } else {
          $rows[$key]['partner_id'] = 'NA';
        }
        if ($value->isactive == 1) {
            $rows[$key]['isactive'] = 'ON';
        }
        if ($value->isactive == 0) {
            $rows[$key]['isactive'] = 'OFF';
        }
        if ($value->issuspend == 1) {
            $url = Url::fromUri("{$base_path}admin/config/simlineview?issuspend=1&iccid={$value->iccid}&imsi={$value->imsi}");
                $link_options = array(
                  'attributes' => array(
                    'class' => array(
                      'use-ajax button',
                      'fa fa-toggle-on',
                      'simlineview-link',
                    ),
                  ),
                );
                $url->setOptions($link_options);
                $rows[$key]['issuspend'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
        }
        if ($value->issuspend == 0) {
            $url = Url::fromUri("{$base_path}admin/config/simlineview?issuspend=0&iccid={$value->iccid}&imsi={$value->imsi}");
                $link_options = array(
                  'attributes' => array(
                    'class' => array(
                      'use-ajax button',
                      'fa fa-toggle-off',
                      'simlineview-link',
                    ),
                  ),
                );
                $url->setOptions($link_options);
                $rows[$key]['issuspend'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
        }
      }   
      $rowPiece = $this->pagerArray($rows, 10);

      //remve key value 0 because it is considering in submission
      if ($rowPiece) {
        array_unshift($rowPiece,"");
        unset($rowPiece[0]);
      }

      $form['sim_line_view'] = [
          '#type' => 'tableselect',
          '#header' => $header_column,
          '#options' => $rowPiece,
          '#empty' => t('No data found'),
          '#multiple' => true,
          '#attributes' => array('class' => array('custom-table')),
          '#prefix' => "<h1 class='uppar-case'>".t('Sim Line View')."</h1>"
        ];
    } else {
      $form['sim_line_view'] = [
          '#type' => 'table',
          '#header' => $header_column,
          '#options' => $rowPiece,
          '#empty' => t('No data found'),
          '#multiple' => true,
          '#attributes' => array('class' => array('custom-table')),
          '#prefix' => "<h1 class='uppar-case'>".t('Sim Line View')."</h1>"
        ];
    } 

    $form['pager'] = [
          '#type' => 'pager',
        ];

    
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    
    $this->session->remove('sim_field');
    $this->session->remove('sim_column'); 
    return $form;
  }

  public function filterForm(array &$form, FormStateInterface $form_state) {
    if ($form['form']['filters']['column']['#value'] == 'partner_id'){
      $this->session->set('sim_field', $form['form']['filters']['partner_id']['#value']);
      $this->session->set('sim_column', $form['form']['filters']['column']['#value']);
    } elseif ($form['form']['filters']['column']['#value'] == "isactive") {
      $this->session->set('sim_field', $form['form']['filters']['isactive']['#value']);
      $this->session->set('sim_column', $form['form']['filters']['column']['#value']);
    } elseif ($form['form']['filters']['column']['#value'] == "issuspend") {
      $this->session->set('sim_field', $form['form']['filters']['enabled']['#value']);
      $this->session->set('sim_column', $form['form']['filters']['column']['#value']);
    } elseif ($form['form']['filters']['column']['#value'] == "none") {
      \Drupal::messenger()->addWarning(t('Please select search column.'));
    } else {
      $this->session->set('sim_field', $form['form']['filters']['text']['#value']);
      $this->session->set('sim_column', $form['form']['filters']['column']['#value']);
    }    
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->session->remove('sim_field');
    $this->session->remove('sim_column');    
  }

  public function deactivateForm(array &$form, FormStateInterface $form_state) { 
  $tests = array_filter($form_state->getValue('sim_line_view'));
    foreach ($tests as $key => $value) {
      if ($value) {
        $query = $this->defaultDatabase->query("update lineview set isactive = 0 where imsi=".$form['sim_line_view']['#options'][$key]['imsi']." and iccid=".$form['sim_line_view']['#options'][$key]['iccid']."");
        if ($query) {
          \Drupal::messenger()->addStatus(t('Successful Deactivated.'));
        }        
      }
    }
  }
  public function suspendForm(array &$form, FormStateInterface $form_state) {
    $tests = array_filter($form_state->getValue('sim_line_view'));
    foreach ($tests as $key => $value) {
      if ($value) {
        $query = $this->defaultDatabase->query("update lineview set issuspend= 1  where imsi=".$form['sim_line_view']['#options'][$key]['imsi']." and iccid=".$form['sim_line_view']['#options'][$key]['iccid']."");
        if ($query) {
          \Drupal::messenger()->addStatus(t('Successful Suspended.'));
        }        
      }
    }
  }
  public function resumeForm(array &$form, FormStateInterface $form_state) {
  $tests = array_filter($form_state->getValue('sim_line_view'));
    foreach ($tests as $key => $value) {
      if ($value) {
        $query = $this->defaultDatabase->query("update lineview set isactive = 1, issuspend= 0  where imsi=".$form['sim_line_view']['#options'][$key]['imsi']." and iccid=".$form['sim_line_view']['#options'][$key]['iccid']."");
        if ($query) {
          \Drupal::messenger()->addStatus(t('Successful Resumed.'));
        }        
      }
    }
  }
  public function locationForm(array &$form, FormStateInterface $form_state) {
    if ($form['form']['filters']['actions']['locationtxt']['#value'] == "") {
      \Drupal::messenger()->addError(t('Location field is empty!!!'));
    } else {
      $tests = array_filter($form_state->getValue('sim_line_view'));
        foreach ($tests as $key => $value) {
          if ($value) {
            $query = $this->defaultDatabase->query("update lineview set locationid = '".$form['form']['filters']['actions']['locationtxt']['#value']."' where imsi=".$form['sim_line_view']['#options'][$key]['imsi']." and iccid=".$form['sim_line_view']['#options'][$key]['iccid']."");
            if ($query) {
              \Drupal::messenger()->addStatus(t('Location changed.'));
            }        
          }
        }
    }
  }

  public function previousForm(array &$form, FormStateInterface $form_state) {
    $response = new RedirectResponse("mydata/oss/table3?table=portal_active_lines_vw");
    $response->send();
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

  /**
   * AJAX callback handler that displays any errors or a success message.
   */
  public function submitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $response->addCommand(new OpenModalDialogCommand("Success!", 'The modal form has been submitted.', ['width' => 700]));
    }
    return $response;
  }

  /**
  * Suspend AJAX callback handler that displays any errors or a success message.
  */
  public function removesubmitModalFormAjax(array $form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      try {
        $field = $form_state->getValues();        
      } catch (\Exception $e) {
        print_r($e->getMessage());
      }
    }

    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) { 
    if($form['form']['filters']['actions']['hss']['#value'] == "none")
    {
      \Drupal::messenger()->addError(t('Hss profile is not selected !!!'));
    } else {
      $tests = array_filter($form_state->getValue('sim_line_view'));
        foreach ($tests as $key => $value) {
          if ($value) {
            $query = $this->defaultDatabase->query("update lineview set profile = '".$form['form']['filters']['actions']['hss']['#value']."' where imsi=".$form['sim_line_view']['#options'][$key]['imsi']." and iccid=".$form['sim_line_view']['#options'][$key]['iccid']."");
            if ($query) {
              \Drupal::messenger()->addStatus(t('Hss Profile Updated.'));
            }        
          }
        }
    }
  }

  public function AssignPartner(array $form, FormStateInterface $form_state) {
    if ($form['form']['filters']['actions']['partner']['#value'] == 0) {
      \Drupal::messenger()->addError(t('Partner not selected!!!'));
    } else { 
      $tests = array_filter($form_state->getValue('sim_line_view'));
        foreach ($tests as $key => $value) {
          if ($value) {
            $query = $this->defaultDatabase->query("update lineview set partner_id = '".$form['form']['filters']['actions']['partner']['#value']."' where imsi=".$form['sim_line_view']['#options'][$key]['imsi']." and iccid=".$form['sim_line_view']['#options'][$key]['iccid']."");
            if ($query) {
              \Drupal::messenger()->addStatus(t('Partner Assigned Successfully'));
            }        
          }
        }
    }
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.sim_line_view_form'];
  }
}