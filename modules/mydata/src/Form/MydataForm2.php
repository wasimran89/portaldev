<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Database\Database;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\HtmlCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\group\Entity\Group;
use Drupal\group\Entity\GroupType;
use Drupal\user\Entity\User;

/**
 * Class MydataForm2.
 *
 * @package Drupal\mydata\Form
 */
class MydataForm2 extends FormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;

  /**
   *
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
  public function __construct(Connection $externalConnection) {
    $this->externalDatabase = $externalConnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');

    // Return the controller object with the injected database connections.
    return new static($externalConnection);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'edit_mydata_form';
  }

  // Function to fetch related partners recursively
  public function fetchRelatedPartners($parentId, &$all_related_partners) {
    // Start building the query on the external database connection
    $query = $this->externalDatabase->select('partner', 'p');

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

  public function grandPartners($partnerId, &$all_related_partners) {
    $partners = [];
    // Start building the query on the external database connection
    $query = $this->externalDatabase->select('partner', 'p');

    // Specify the field to retrieve (partner_id)
    $query->fields('p', ['parent_partner_id','grand_parent_partner_id']);

    // Add the OR condition group to the main query
    $query->condition('p.partner_id', $partnerId, '=');

    // Execute the query and fetch all results
    $results = $query->execute()->fetchAll();
    $partners[] = $results[0]->parent_partner_id;
    $partners[] = $results[0]->grand_parent_partner_id;

    // Loop through each result and add to the array
    foreach ($partners as $partner) {
        if (!in_array($partner, $all_related_partners)) {
            $all_related_partners[] = $partner;
            // Recursively call this function for further relationships
            $this->grandPartners($partner, $all_related_partners);
        }
    }
  }

  /**
   * AJAX callback to update grand_parent_partner_id options based on parent_partner_id.
   */
  public function update_grand_parent_partner_options(array &$form, FormStateInterface $form_state) {
    // Get the selected value from parent_partner_id.
    $selected_value = $form_state->getValue('parent_partner_id');
    if ($selected_value != 'NULL') {
      $all_related_partners = [];
      $this->grandPartners($selected_value, $all_related_partners);

      //$all_related_partners[] = $selected_value;
      $partners_list = implode(',', array_map('intval', $all_related_partners));

      // Build the query dynamically
      $querypid = $this->externalDatabase->query("
      SELECT partner_id, partner_name 
      FROM partner 
      WHERE partner_id in  ($partners_list)"
      );

      $resultpid = $querypid->fetchAll();
      $partner_pid = [];
      foreach ($resultpid as $keypid => $datapid) {      
        $partner_pid[$datapid->partner_id] = $datapid->partner_name;
      }

      if (!$partner_pid) {
        $partner_pid['NULL'] = '--None--';
      }
    } else {
      $partner_pid['NULL'] = '--Select--';
    }

    $form['grand_parent_partner_id']['#options'] = $partner_pid;

    // Return the updated field.
    return $form['grand_parent_partner_id'];
  }



  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    $record = array();
    //Encryption logic
    $decrypted = '';
    if (strlen($_GET['table']) > 50 ) {
      $encryption_profile = EncryptionProfile::load('url');
      $decrypted = \Drupal::service('encryption')->decrypt($_GET['table'], $encryption_profile);
      $r = $decrypted;
    }

    if ($decrypted == '') {
      $r = $_GET['table'];
    }

    $config = \Drupal::config('xai.settings');
    $str = json_decode($config->get('bio'));
    $header_table = $str->$r->table_listing_fields;
    $primary_key = $str->$r->primary_key;
    $composite_key = $str->$r->composite_key;
    $language = \Drupal::languageManager()->getCurrentLanguage()->getId();

    if ($language=='en') {
      $header_alias = $str->$r->alias_en;
      $title = $str->$r->title_en;
    } else {
      $header_alias = $str->$r->alias_ja;
      $title = $str->$r->title_ja;
    }

    if ((isset($_GET['num'])) && ($r != 'input_element')) {
      $query = $this->externalDatabase->select($r, 'm')
                                      ->condition($primary_key, $_GET['num'])
                                      ->fields('m');
      $record = $query->execute()->fetchAssoc();
    }

    //Added one more condition for input_element table
    if ((isset($_GET['num'])) && ($r == 'input_element')) {
      $query = $this->externalDatabase->select($r, 'm')
              ->condition($primary_key, $_GET['num'])
              ->condition($composite_key, $_GET['num1'])
              ->fields('m');
      $record = $query->execute()->fetchAssoc();
    }  
    
    if (($r == 'partner')||($r=='input_file') ||($r=='input_file_detail') ||($r=='input_element') || ($r == 'imsi_range') || ($r == 'esim_profile_type')) {
      $query = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
      $result = $query->fetchAll();
      $partner_id = [];
      foreach ($result as $key => $data) {
        $partner_id[0] = "--SELECT--";
        $partner_id[$data->partner_id] = $data->partner_name;
      }

      //last partner_id
      $results2 = $this->externalDatabase->query("select max(partner_id) from partner");
      foreach ($results2 as $res) {
        $partner_id_last = $res->max + 1;
      }

      //Removed column which should not be inserted
      if ($r == 'input_file') {
        $removevalue = ['partner_name','date_created','Input_Elements','ESIM_File_Detail'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }

      //Removed column parent_parnter_name which should not be inserted
      if ($r == 'partner') {
        $removevalue = ['parent_partner_name', 'grand_parent_partner_name'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }

      //Imsi Range
      if ($r == 'imsi_range') {
        $removevalue = ['imsi_range_id','partner name','Stats'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }
      //esim_profile_type
      if ($r == 'esim_profile_type') {
        $removevalue = ['created_at','partner name'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }
      $form['#prefix'] = '<div id="modal_example_form">';
      $form['#suffix'] = '</div>';

      foreach ($header_table as $column) {
        if (($column != 'Edit') && ($column != 'Delete')) {
          if (empty($record) && $column != $primary_key) { //primary key should not show in insert
            $form['title'] = [
            '#type' => 'item',
            '#prefix' => "<h1 class='uppar-case'>Add New ".$title."</h1>"
            ];
            ////////////////// INSERT /////////////////////////////////
            ////////////////// INSERT /////////////////////////////////
            ////////////////// INSERT /////////////////////////////////
            //land after add new click
            if (($column == 'partner_id') && ($r == 'input_file')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner_id,
                '#cache' => array('max-age' => 0)
              );
            } elseif(($column == 'rpad_offset') && ($r == 'input_file')) {
              $form[$column] = array(
                  '#type' => 'number',
                  '#title' => t(str_replace("_", ' ', $column)),
                  '#cache' => array('max-age' => 0),
                  '#attributes' => array('pattern' => array('[0-9]+'),'min' => '0','max' => '49'),
                  '#description' => 'Default for ESIM is 21, USIM 13.'
              );
            } elseif (($column == 'input_file_type') && ($r == 'input_file')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => ['--SELECT--'=>'--SELECT--','ESIM'=> 'ESIM','USIM'=> 'USIM'],
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'environment') && ($r == 'input_file')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => ['--SELECT--'=>'--SELECT--','PROD'=> 'PROD','PREPROD'=> 'PREPROD'],
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'partner_name') && ($r == 'partner')) {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } else if(($column == 'op') && ($r == 'partner')) {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t('Enter customer provided OP or leave blank to randomly generate.'),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                //'#cache' => array('max-age' => 0),
                '#attributes' => array('pattern' => array('[0-9A-F]{32}')),
                '#description' => '32 digit hex value'
              );
            } else if(($column == 'k4') && ($r == 'partner')) {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t('K4 transport key'),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                //'#cache' => array('max-age' => 0),
                '#attributes' => array('pattern' => array('[0-9A-F]{32}')),
                '#description' => '32 digit hex value'
              );
            } elseif(($column == 'json_secrets') && ($r == 'partner')) {       
              $form[$column] = array(
                '#type' => 'checkbox',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'parent_partner_id') && ($r == 'partner')) {
              $user_display_name = $user->getEmail();
              $roles = $user->getRoles();

              if (in_array('administrator', $roles)) {
                $query = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
                $result = $query->fetchAll();
                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
              } else {
                $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
                $resultuser = $queryuser->fetchAll();

                //child partner start
                $all_related_partners = [];
                $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
                $all_related_partners[] = $resultuser[0]->sim_partner_id;
                $partners_list = implode(',', array_map('intval', $all_related_partners));

                // Build the query dynamically
                $query = $this->externalDatabase->query("
                    SELECT partner_id, partner_name 
                    FROM partner 
                    WHERE partner_id in  ($partners_list)"
                );

                $result= $query->fetchAll();
                //child partner end

                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
              }

              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner,
                '#ajax' => array(
                  'callback' => [$this, 'update_grand_parent_partner_options'],
                  'wrapper' => 'grand-parent-wrapper',
                  // The wrapper that will be updated
                ),
                '#cache' => array('max-age' => 0),
              );
            } elseif (($column == 'grand_parent_partner_id') && ($r == 'partner')) {
              $user_display_name = $user->getEmail();
              $roles = $user->getRoles();

              if (in_array('administrator', $roles)) {
                $query = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
                $result = $query->fetchAll();
                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
              } else {
                $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
                $resultuser = $queryuser->fetchAll();

                //child partner start
                $all_related_partners = [];
                $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
                $all_related_partners[] = $resultuser[0]->sim_partner_id;
                $partners_list = implode(',', array_map('intval', $all_related_partners));

                // Build the query dynamically
                $query = $this->externalDatabase->query("
                    SELECT partner_id, partner_name 
                    FROM partner 
                    WHERE partner_id in  ($partners_list)"
                );

                $result= $query->fetchAll();
                //child partner end

                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
              }

              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner,
                '#prefix' => '<div id="grand-parent-wrapper">',  // Define the wrapper
                '#suffix' => '</div>',
                '#validated' => TRUE,
                '#ajax' => array(
                  'callback' => [$this, 'update_grand_parent_partner_options'],
                  'wrapper' => 'grand-parent-wrapper',
                  // The wrapper that will be updated jugad
                ),
                '#states' => [
                    'visible' => [
                      ':input[name="parent_partner_id"]' => ['!value' => 'NULL'],
                    ],
                  ],
                '#cache' => array('max-age' => 0)
              );
            } elseif ($column == 'static') {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => ['0'=>'FALSE','1'=> 'TRUE'],
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'partner_id') && ($r == 'imsi_range')) {
              //For admin
              if (in_array('administrator', $roles)) {
                $form[$column] = array(
                  '#type' => 'select',
                  '#title' => t('Partner'),
                  '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                  '#options' => $partner_id,
                  '#cache' => array('max-age' => 0),
                  '#required' => true
                );
              } else { //For user who has parent as well as child partner
                $user_display_name = $user->getEmail();
                $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
                $resultuser = $queryuser->fetchAll();

                //get Assigned partner id from current user
                $querypartner = $this->externalDatabase->query("select partner_id, partner_name from partner where partner_id={$resultuser[0]->sim_partner_id}");
                $resultpartner = $querypartner->fetchAll();

                //if user has child partner then show count
                $queryc = $this->externalDatabase->query("select count(*) from partner where parent_partner_id = {$resultuser[0]->sim_partner_id}");
                $resultc = $queryc->fetchAll();

                if ($resultc[0]->count) { // Child partner yes
                  $querypid = $this->externalDatabase->query("select partner_id, partner_name from partner where parent_partner_id={$resultuser[0]->sim_partner_id}");
                  $resultpid = $querypid->fetchAll();

                  $partner_pid = [];
                  foreach ($resultpid as $keypid => $datapid) {
                    //Add orignal partner as well
                    $partner_pid[$resultpartner[0]->partner_id] = $resultpartner[0]->partner_name;
                    //child partner
                    $partner_pid[$datapid->partner_id] = $datapid->partner_name;
                  }
                    //child partner
                    $form[$column] = array(
                        '#type' => 'select',
                        '#title' => t('Partner'),
                        '#default_value' => $resultuser[0]->sim_partner_id,
                        '#options' => $partner_pid,
                        '#cache' => array('max-age' => 0),
                        '#required' => true
                    );
                } else {
                    // No child partner
                    $form[$column] = array(
                      '#type' => 'select',
                      '#title' => t('Partner'),
                      '#default_value' => $resultuser[0]->sim_partner_id,
                      '#options' => $partner_id,
                      '#cache' => array('max-age' => 0),
                      '#required' => true,
                      '#disabled' => true
                    );
                }              
              } //else            
            } elseif (($column == 'partner_id') && ($r == 'esim_profile_type')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t('Partner'),
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#options' => $partner_id,
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } elseif (($column == 'esim_profile_type') && ($r == 'esim_profile_type')) {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } elseif(($column == 'imsi_range_name') && ($r == 'imsi_range')) {  
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#required' => true,
              );
            } elseif (($column == 'imsi_start') && ($r == 'imsi_range')) {       
              $form['imsi_start1'] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#cache' => array('max-age' => 0),
                '#required' => true,
                //'#disabled' => true,
                '#description' => 'The entered value must be numeric and length 5 or 6',
                '#wrapper_attributes' => array('style' => ["display:inline-block"]),
                '#attributes' => array('readonly' => 'readonly')
              );
              $form['imsi_start2'] = array(
                '#type' => 'number',
                '#description' => $this->t('Enter a numeric value with a length of 9 or 10.'),
                '#default_value' => isset($_SESSION['imsi_start2']) ? $_SESSION['imsi_start2'] : '',
                '#required' => TRUE,
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => '[0-9]{9,10}','title'=>'Enter a numeric value with a length of 9 or 10.','maxlength'=>"10"),
                '#wrapper_attributes' => array('style' => ["display:inline-block"]),
                '#element_validate' => array(array($this, 'validateImsiStart2'))
              );
            } elseif (($column == 'imsi_end') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#required' => true,
                //'#attributes' => array('pattern' => array('[0-9]{15}')),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{15}'),'maxlength'=>"15"),
                '#description' => 'The entered value must be numeric and length 15',
              );
            } elseif (($column == 'imsi_count') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#required' => true,
                //'#attributes' => array('pattern' => array('[0-9]{15}')),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{6}'),'maxlength'=>"100000")
              );
            } elseif (($column == 'date_created') && ($r == 'imsi_range')) {
            } elseif (($column == 'status') && ($r == 'imsi_range')) {
            } elseif (($column == 'esim_usim') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                //'#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#options' => ['ESIM'=> 'ESIM','USIM'=> 'USIM'],
                '#cache' => array('max-age' => 0),
                '#required'=> true
              );
            } elseif (($column == 'mcc') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                //'#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#description' => 'The entered value must be numeric and length 3',
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3"),
                '#required'=> true,
                '#element_validate' => array(array($this, 'validateMCC'))
              );
            } elseif (($column == 'mnc') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                //'#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#description' => 'The entered value must be numeric and length 2 or 3',
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3"),
                '#required'=> true,
                '#element_validate' => array(array($this, 'validateMNC'))
              );
            } elseif (($column == 'notes') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'ibn') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                //'#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#default_value' => isset($_SESSION[$column]) ? $_SESSION[$column] : '',
                '#cache' => array('max-age' => 0),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{4}'),'maxlength'=>"4"),
                '#description' => 'The entered value must be numeric and length 4',
                '#element_validate' => array(array($this, 'validateIBN'))
              );
            } else {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                '#cache' => array('max-age' => 0)
              );
            }
          }
          //////////////////////// UPDATE /////////////////////////////////
          //////////////////////// UPDATE /////////////////////////////////
          //////////////////////// UPDATE /////////////////////////////////
          if ($record) { //if record avaialble then
              $form['title'] = [
                '#type' => 'item',
                '#prefix' => "<h1 class='uppar-case'>Edit ".$title."</h1>"
              ];

            if ($column == $primary_key) { //primary key should not be editable
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                '#cache' => array('max-age' => 0),
                '#attributes' => array('readonly' => 'readonly')
              );
            } elseif (($column == 'partner_id') && ($r == 'partner')) {       
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $partner_id_last,
                '#cache' => array('max-age' => 0),
                '#required' => true,
                '#disabled' => true
              );
            } elseif (($column == 'partner_id') && ($r == 'input_file')) {       
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $partner_id,
                '#options' => $partner_id,
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } elseif (($column == 'date_created') && ($r != 'imsi_range')) {      
              $form[$column] = array(
                '#type' => 'date',
                '#date_date_format' => 'YYYY-MM-DD',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } elseif (($column == 'date_created') && ($r == 'imsi_range')) {      
              $form[$column] = array(
                '#type' => 'date',
                '#date_date_format' => 'yy-mm-dd',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#disabled' => true
              );
            } elseif(($column == 'partner_id') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t('Partner'),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $partner_id,
                '#options' => $partner_id,
                '#cache' => array('max-age' => 0),
                //'#required' => true,
                //'#disabled' => true
              );
            } elseif (($column == 'partner_id') && ($r == 'esim_profile_type')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t('Partner'),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $partner_id,
                '#options' => $partner_id,
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } elseif (($column == 'esim_profile_type') && ($r == 'esim_profile_type')) {
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } elseif (($column == 'imsi_start') && ($r == 'imsi_range')) { 
              if ($record[$column]) {
                $mcc_mnc = $record['mcc'].$record['mnc'];
                $imsi_start1 = substr($record[$column], 0, strlen($mcc_mnc));
                $imsi_start2 = substr($record[$column], strlen($mcc_mnc));
              }

              $form['imsi_start1'] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#cache' => array('max-age' => 0),
                '#required' => true,
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $imsi_start1 : $imsi_start1,
                '#description' => 'The entered value must be numeric and length 5 or 6',
                '#wrapper_attributes' => array('style' => ["display:inline-block"]),
                '#attributes' => array('readonly' => 'readonly'),
                '#disabled' => true
              );
              $form['imsi_start2'] = array(
                '#type' => 'number',
                '#description' => $this->t('Enter a numeric value with a length of 9 or 10.'),
                '#required' => TRUE,
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $imsi_start2 : $imsi_start2,
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => '[0-9]{9,10}','title'=>'Enter a numeric value with a length of 9 or 10.','maxlength'=>"10",'readonly' => 'readonly'),
                '#wrapper_attributes' => array('style' => ["display:inline-block"]),
                '#element_validate' => array(array($this, 'validateImsiStart2')),
                '#disabled' => true
              );
              /*     
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#required' => true,
                //'#attributes' => array('pattern' => array('[0-9]{15}')),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{15}'),'maxlength'=>"15"),
                '#description' => 'The entered value must be numeric and length 15',
              );*/
            } elseif (($column == 'imsi_end') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#required' => true,
                //'#attributes' => array('pattern' => array('[0-9]{15}')),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{15}'),'maxlength'=>"15"),
                '#description' => 'The entered value must be numeric and length 15',
              );
            } elseif (($column == 'imsi_count') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#required' => true,
                //'#attributes' => array('pattern' => array('[0-9]{15}')),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{6}'),'maxlength'=>"100000",'readonly' => 'readonly'),
                '#disabled' => true
              );
            } elseif (($column == 'esim_usim') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#options' => ['--SELECT--'=>'--SELECT--','ESIM'=> 'ESIM','USIM'=> 'USIM'],
                '#cache' => array('max-age' => 0),
                '#disabled' => true,
                '#attributes' => array('readonly' => 'readonly')
              );
            } elseif(($column == 'mcc') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#description' => 'The entered value must be numeric and length 3',
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3",'readonly' => 'readonly'),
                '#required'=> true,
                '#element_validate' => array(array($this, 'validateMCC')),
                '#disabled' => true
              );
            } elseif (($column == 'mnc') && ($r == 'imsi_range')) {
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#description' => 'The entered value must be numeric and length 2 or 3',
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{3}'),'maxlength'=>"3",'readonly' => 'readonly'),
                '#required'=> true,
                '#element_validate' => array(array($this, 'validateMNC')),
                '#disabled' => true
              );
            } elseif(($column == 'notes') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0)
              );
            } elseif(($column == 'ibn') && ($r == 'imsi_range')) {       
              $form[$column] = array(
                '#type' => 'number',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0),
                '#disabled' => true,
                //'#attributes' => array('pattern' => array('[0-9]{4}')),
                '#attributes' => array('oninput'=>'javascript: if (this.value.length > this.maxLength) this.value = this.value.slice(0, this.maxLength)','pattern' => array('[0-9]{4}'),'maxlength'=>"4",'readonly' => 'readonly'),
                '#description' => 'The entered value must be numeric and length 4',
                '#element_validate' => array(array($this, 'validateIBN'))
              );
            } elseif(($column == 'rpad_offset') && ($r == 'input_file')) {
              $form[$column] = array(
                  '#type' => 'number',
                  '#title' => t(str_replace("_", ' ', $column)),
                  '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                  '#cache' => array('max-age' => 0),
                  '#attributes' => array('pattern' => array('[0-9]+'),'min' => '0','max' => '49'),
                  '#description' => 'Default for ESIM is 21, USIM 13.'
              );
            } elseif (($column == 'input_file_type') && ($r == 'input_file')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#options' => ['--SELECT--'=>'--SELECT--','ESIM'=> 'ESIM','USIM'=> 'USIM'],
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'environment') && ($r == 'input_file')) {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#options' => ['--SELECT--'=>'--SELECT--','PROD'=> 'PROD','PREPROD'=> 'PREPROD'],
                '#cache' => array('max-age' => 0)
              );
            } elseif($column == 'op') {
            // this will generate 6 charactor, you can create as many just change the 6 from code
            //$pass = substr(str_shuffle("0123456789ABCDEDF"), 0, 32);
            $characters = '0123456789ABCDEF';
            $charactersLength = strlen($characters);
            $randomString = '';
            for ($i = 0; $i < 32; $i++) {
                $randomString .= $characters[rand(0, $charactersLength - 1)];
            }
            //return $randomString;
            $op = "****************";     
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $randomString,
                //'#cache' => array('max-age' => 0),
                '#required' => true,
                '#attributes' => array('pattern' => array('[0-9A-F]{32}')),
                '#description' => '32 digit hex value'
              );
            } elseif($column == 'k4') {
            // this will generate 6 charactor, you can create as many just change the 6 from code
            //$pass = substr(str_shuffle("0123456789ABCDEDF"), 0, 32);
            $characters2 = '0123456789ABCDEF';
            $charactersLength2 = strlen($characters2);
            $randomString2 = '';
            for ($i = 0; $i < 32; $i++) {
                $randomString2 .= $characters2[rand(0, $charactersLength2 - 1)];
            }
            //return $randomString;
            $k4 = "****************";     
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $randomString2,
                //'#cache' => array('max-age' => 0),
                '#required' => true,
                '#attributes' => array('pattern' => array('[0-9A-F]{32}')),
                '#description' => '32 digit hex value'
              );
            } elseif (($column == 'json_secrets') && ($r == 'partner')) {       
              $form[$column] = array(
                '#type' => 'checkbox',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0)
              );
            } elseif (($column == 'parent_partner_id') && ($r == 'partner')) {
              $user_display_name = $user->getEmail();
              $roles = $user->getRoles();
              if (in_array('administrator', $roles)) {
                $query = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
                $result = $query->fetchAll();
                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
              } else {
                $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
                $resultuser = $queryuser->fetchAll();

                //child partner start
                $all_related_partners = [];
                $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
                $all_related_partners[] = $resultuser[0]->sim_partner_id;
                $partners_list = implode(',', array_map('intval', $all_related_partners));

                // Build the query dynamically
                $query = $this->externalDatabase->query("
                    SELECT partner_id, partner_name 
                    FROM partner 
                    WHERE partner_id in  ($partners_list)"
                );

                $result= $query->fetchAll();
                //child partner end

                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
              }
              /*
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner,
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0)
              );
              */

              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner,
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#ajax' => array(
                  'callback' => [$this, 'update_grand_parent_partner_options'],
                  'wrapper' => 'grand-parent-wrapper',
                  // The wrapper that will be updated
                ),
                '#cache' => array('max-age' => 0),
              );
            } elseif (($column == 'grand_parent_partner_id') && ($r == 'partner')) {
              $user_display_name = $user->getEmail();
              $roles = $user->getRoles();

              if (in_array('administrator', $roles)) {
                /*
                $query = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
                $result = $query->fetchAll();
                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
                */
                $queryuser = $this->externalDatabase->query("select parent_partner_id from partner where partner_id = '".$_GET['num']."'");
                $resultuser = $queryuser->fetchAll();

                //child partner start
                $all_related_partners = [];
                $this->grandPartners($resultuser[0]->parent_partner_id, $all_related_partners);
                //$all_related_partners[] = $resultuser[0]->parent_partner_id;
                $partners_list = implode(',', array_map('intval', $all_related_partners));

                // Build the query dynamically
                $query = $this->externalDatabase->query("
                    SELECT partner_id, partner_name 
                    FROM partner 
                    WHERE partner_id in  ($partners_list)"
                );

                $result= $query->fetchAll();
                //child partner end

                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
                if (!$partner) {
                  $partner['NULL'] = '--None--';
                }
              } else {
                $queryuser = $this->externalDatabase->query("select parent_partner_id from partner where partner_id = '".$_GET['num']."'");
                $resultuser = $queryuser->fetchAll();

                //child partner start
                $all_related_partners = [];
                $this->grandPartners($resultuser[0]->parent_partner_id, $all_related_partners);
                //$all_related_partners[] = $resultuser[0]->parent_partner_id;
                $partners_list = implode(',', array_map('intval', $all_related_partners));

                // Build the query dynamically
                $query = $this->externalDatabase->query("
                    SELECT partner_id, partner_name 
                    FROM partner 
                    WHERE partner_id in  ($partners_list)"
                );

                $result= $query->fetchAll();
                //child partner end

                $partner = [];
                foreach ($result as $key => $data) {
                  $partner['NULL'] = '--Select--';
                  $partner[$data->partner_id] = $data->partner_name;
                }
                if (!$partner) {
                  $partner['NULL'] = '--None--';
                }
              }
              /*
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner,
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#cache' => array('max-age' => 0)
              );
              */
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#options' => $partner,
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : $record[$column],
                '#prefix' => '<div id="grand-parent-wrapper">',  // Define the wrapper
                '#suffix' => '</div>',
                '#validated' => TRUE,
                //'#ajax' => array(
                 // 'callback' => [$this, 'update_grand_parent_partner_options'],
                 // 'wrapper' => 'grand-parent-wrapper',
                  // The wrapper that will be updated jugad
                //),
                '#states' => [
                    'visible' => [
                      ':input[name="parent_partner_id"]' => ['!value' => 'NULL'],
                    ],
                  ],
                '#cache' => array('max-age' => 0)
              );
            } elseif ($column == 'static') {
              $form[$column] = array(
                '#type' => 'select',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                '#options' => ['0'=>'FALSE','1'=> 'TRUE'],
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            } else {        
              $form[$column] = array(
                '#type' => 'textfield',
                '#title' => t(str_replace("_", ' ', $column)),
                '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
                '#cache' => array('max-age' => 0),
                '#required' => true
              );
            }
          }
        } //if
      } //for
    } else {
      foreach ($header_table as $column) {
        if (($column != 'Edit') && ($column != 'Delete')) {
          $form[$column] = array(
            '#type' => 'textfield',
            '#title' => t(str_replace("_", ' ', $column)),
            '#default_value' => (isset($record[$column]) && $_GET['num']) ? $record[$column] : '',
            '#cache' => array('max-age' => 0)
          );
        } //if
      } //for
    } //else

    $form['edit_submit'] = [
      '#type' => 'button',
      '#value' => t('Save'),
      '#name' => 'save',
      '#attributes' => [
          'class' => [
          'use-ajax',
          ],
        ],
        '#ajax' => [
          'callback' => [$this, 'submitModalFormAjax'],
          'event' => 'click',
        ],
    ];

    $form['actions']['previous'] = [
      '#type' => 'submit',
      '#submit' => [[$this, 'previousForm']],
      '#value' => t('Previous'),
      '#limit_validation_errors' => [], //no validation for back button
    ];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
    return $form;
  }

  public function validateImsiStart2(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $numeric_value = $element['#value'];
    $field = $form_state->getValues();
    $imsi_start_count = strlen($field['imsi_start1']) + strlen($field['imsi_start2']) ;
    if ($imsi_start_count < 15) {
        $form_state->setError($element, $this->t('The value must be numeric and have a length of 10.'));
    }
    if ($imsi_start_count > 15) {
      $form_state->setError($element, $this->t('The value must be numeric and have a length of 9.'));
    }
  }

  public function validateMCC(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $field = $form_state->getValues();
    if (strlen($field['mcc']) !== 3) {
      $form_state->setError($element, $this->t('The value must be numeric and have a length of 3.'));
    }
  }

  public function validateMNC(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $field = $form_state->getValues();
    if (strlen($field['mnc']) < 2 || strlen($field['mnc']) > 3) {
      $form_state->setError($element, $this->t('The value must be numeric and have a length of 2 or 3.'));
    }
  }

  public function validateIBN(array &$element, FormStateInterface $form_state, array &$complete_form) {
    $field = $form_state->getValues();
    if ($field['ibn']) {
      if ($field['ibn'] < 5) {
        $form_state->setError($element, $this->t('The value must be numeric and have a length of 4.'));
      }
    }    
  }

  public function previousForm(array &$form, FormStateInterface $form_state) {
    $decrypted = '';
    if (strlen($_GET['table']) > 50 ) {
      $encryption_profile = EncryptionProfile::load('url');
      $decrypted = \Drupal::service('encryption')->decrypt($_GET['table'], $encryption_profile);
      $r = $decrypted;
    }

    if ($decrypted == '') {
      $r = $_GET['table'];
    }

    if ($r == 'imsi_range') {
      unset($_SESSION['mcc']);
      unset($_SESSION['mnc']);
      unset($_SESSION['imsi_count']);
      unset($_SESSION['ibn']);
      unset($_SESSION['imsi_start1']);
      unset($_SESSION['imsi_start2']);
      unset($_SESSION['imsi_end']);
      unset($_SESSION['partner_id']);
      unset($_SESSION['imsi_range_name']);
      unset($_SESSION['notes']);
    }     

    if ($r == 'input_file') {
      $response = new RedirectResponse("/input-file");
    } else {
      $response = new RedirectResponse("/mydata/oss/table2?table=" . $_GET['table']);
    }
    $response->send();
  }

  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitModalFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $field = $form_state->getValues();
      $decrypted = '';
      if (strlen($_GET['table']) > 50 ) {
        $encryption_profile = EncryptionProfile::load('url');
        $decrypted = \Drupal::service('encryption')->decrypt($_GET['table'], $encryption_profile);
        $r = $decrypted;
      }

      if ($decrypted == '') {
        $r = $_GET['table'];
      }

      //on submit validation added
      $validation =1;
      if (($field['partner_id']==0) && ($r == 'imsi_range')) {
        $title = "Validatation";
        $content = 'Please select partner_id';
        $options = [
            'buttons' => [
              'button1' => [
                'text' => $this->t('Ok'),
                'id' => 'ok',
                'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
              ],
            ],
            'width' => 400
        ];
        $validation=2;
      }

      //special case
      if ($field['imsi_start2']) {
        $_SESSION['imsi_start2'] = $field['imsi_start2'];
      }
      
      //partner table HEX value genrated
      if (($field['op'] =='') && ($r == 'partner')) {
        //if data entered manually keep as it is else
        $characters = '0123456789ABCDEF';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < 32; $i++) {
          $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        $field['op'] = $randomString;
      }
      //end partner table HEX logic

      //k4 table HEX value genrated
      if (($field['k4'] =='') && ($r == 'partner')) {
        //if data entered manually keep as it is else
        $characters2 = '0123456789ABCDEF';
        $charactersLength2 = strlen($characters2);
        $randomString2 = '';
        for ($i = 0; $i < 32; $i++) {
          $randomString2 .= $characters2[rand(0, $charactersLength2 - 1)];
        }
        $field['k4'] = $randomString2;
      }
      //end k4 table HEX logic

      //$r = $_GET['table'];
      $config = \Drupal::config('xai.settings');
      $str = json_decode($config->get('bio'));
      $header_table = $str->$r->table_listing_fields;
      $primary_key = $str->$r->primary_key;
      $composite_key = $str->$r->composite_key;
      
      //Removed column which should not be inserted
      if ($r == 'input_file') {
        $removevalue = ['partner_name','date_created','Input_Elements','ESIM_File_Detail'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }

      //Removed column which should not be inserted
      if ($r == 'partner') {
        $removevalue = ['parent_partner_name','grand_parent_partner_name'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }

      //Removed column which should not be inserted
      if ($r == 'imsi_range') {
        $removevalue = ['imsi_range_id','partner name','date_created','status','Stats'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }

      //esim_profile_type
      if ($r == 'esim_profile_type') {
        $removevalue = ['created_at','partner name'];
        foreach ($removevalue as $keyr => $valuer) {
          if (($key = array_search($valuer, $header_table)) !== false) {
            unset($header_table[$key]);
          }
        }
      }

      $results= [];
      foreach ($header_table as $key => $column) {
        if (($column != 'Edit') && ($column != 'Delete') && ($column != $primary_key)) {
          $results[$column] = $field[$column];
          if ($r=='imsi_range') {
             //keep value in session after page refresh only for insert
             $_SESSION[$column] = $field[$column];           
          }      
        } //if
      } //for

      //Validation check(success loop)
      if ($validation != 2) {
        if (isset($_GET['num'])) { //Update query
          $flattened = $results;
          array_walk($flattened, function (&$value, $key) {
            //$value = "{$key}= '{$value}'";
            if ($value=='NULL') {
              $value = "{$key}= {$value}"; //remove inverted comma if value is null passing plane NULL value e.g parent_partner_id = NULL
            } else {
              $value = "{$key}= '{$value}'";
            }
          });
         $fields =  implode(', ', $flattened);
          try {
            if ($r == 'input_element') {
              $query = $this->externalDatabase->query("update {$r} set  $fields where $primary_key = '".$_GET['num']."' and $composite_key='".$_GET['num1']."'");
            } elseif ($r == 'imsi_range') {
              $notes = $field['notes'] ? "'".$field['notes']."'" : 'NULL';
              $ibn = $field['ibn'] ? "'".$field['ibn']."'" : 'NULL';
              $imsi_start = $field['imsi_start1'].$field['imsi_start2'];

              $query = $this->externalDatabase->query("SELECT * FROM insert_update_imsi_range(".$_GET['num'].", 'update', ".$field['partner_id'].", '".$field['esim_usim']."','".$field['imsi_range_name']."','".$field['mcc']."','".$field['mnc']."', '".$imsi_start."', '".$field['imsi_count']."',".$ibn.", ".$notes.")");
            } elseif ($r == 'partner') {
              $query = $this->externalDatabase->query("update {$r} set  $fields where $primary_key = '".$_GET['num']."'");
              if ($query) {
                $queryselect = $this->externalDatabase->select('partner', 'p');
                $queryselect->fields('p', ['partner_name']);
                $queryselect->condition('p.partner_id', $field['partner_id'], '=');
                $resultsgroupname = $queryselect->execute()->fetchAll();
                $groups = Group::loadMultiple();

                //maping in group list in drupal application
                foreach ($groups as $groupv) {
                  $label = $groupv->label();

                  // Check if the label exists as a key in the partner_name array
                  if (trim($label) == trim($resultsgroupname[0]->partner_name)) {
                     $group_id = $groupv->id(); 
                  }
                }

                $group = Group::load($group_id); // Load the existing group entity by its ID.

                if ($group) {
                  // Update the label of the group.
                  $group->set('label', $field['partner_name']); // Replace 'Updated Label' with your desired label.
                  
                  // Save the updated group entity.
                  $group->save();
                  
                  // Log the update operation.
                  \Drupal::logger('mydata')->info('Group with ID ' . $group_id . ' has been updated with a new label : '.$field['partner_name']);
                }
              }
            } else {
              $query = $this->externalDatabase->query("update {$r} set  $fields where $primary_key = '".$_GET['num']."'");
            }

            if ($r == 'imsi_range') {
              $result = $query->fetchAll();
              if ($result[0]->insert_update_imsi_range == 'SUCCESS') {
                $title = "Success";
                $content = 'Successfully Updated';
                $options = [
                    'buttons' => [
                      'button1' => [
                        'text' => $this->t('Ok'),
                        'id' => 'ok',
                        'onclick' => 'window.open("/mydata/oss/table2?table='.$r.'","_self")'
                      ],
                    ],
                    'width' => 400
                ];
              } else {
                $title = "Success";
                $content = 'imsi_start "'.$field['imsi_start'].'" is between existing imsi_range_name';
                $options = [
                    'buttons' => [
                      'button1' => [
                        'text' => $this->t('Ok'),
                        'id' => 'ok',
                        'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
                      ],
                    ],
                    'width' => 400
                ];
              }
            } else {
                $query->execute();
                $title = "Success";
                $content = 'Successfully Updated';
                $options = [
                    'buttons' => [
                      'button1' => [
                        'text' => $this->t('Ok'),
                        'id' => 'ok',
                        'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
                      ],
                    ],
                    'width' => 400
                ];
            }
                      
            // Clear the session variable when the form is submitted successfully.
            if($r == 'imsi_range') {
              unset($_SESSION['mcc']);
              unset($_SESSION['mnc']);
              unset($_SESSION['imsi_count']);
              unset($_SESSION['ibn']);
              unset($_SESSION['imsi_start1']);
              unset($_SESSION['imsi_start2']);
              unset($_SESSION['imsi_end']);
              unset($_SESSION['partner_id']);
              unset($_SESSION['imsi_range_name']);
              unset($_SESSION['notes']);
            }
            if ($r == 'input_file') { 
              $title = "Success";
              $content = 'Successfully Updated';
              $options = [
                  'buttons' => [
                    'button1' => [
                      'text' => $this->t('Ok'),
                      'id' => 'ok',
                      'onclick' => 'window.open("/input-file","_self")'
                    ],
                  ],
                  'width' => 400
              ];
            } else {
              $title = "Success";
              $content = 'Successfully Updated';
              $options = [
                  'buttons' => [
                    'button1' => [
                      'text' => $this->t('Ok'),
                      'id' => 'ok',
                      'onclick' => 'window.open("/mydata/oss/table2?table='.$r.'","_self")'
                    ],
                  ],
                  'width' => 400
              ];
            }
          } catch (\Exception $e) {
            $error_message = $e->getMessage();
            $title = "Error";
            $content = $error_message;
            $options = [
                'buttons' => [
                  'button1' => [
                    'text' => $this->t('Ok'),
                    'id' => 'ok',
                    'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
                  ],
                ],
                'width' => 700
            ];
          }
        } else { //Insert query
          $columnname = $results;
          array_walk($columnname, function (&$value, $key) {
            $value ="{$key}";
          });
          $col =  implode(', ', $columnname);
          $columnvalue = $results;
          array_walk($columnvalue, function (&$value, $key) {
            if ($value=='NULL') {
              $value = "{$value}"; //remove single inverted comma from parent_partner_id if null 
            } else {
              $value = "'{$value}'";
            }        
          });
          $colvalue =  implode(', ', $columnvalue);

          try {
            if ($r == 'imsi_range') {
              $notes = $field['notes'] ? "'".$field['notes']."'" : 'NULL';
              $ibn = $field['ibn'] ? "'".$field['ibn']."'" : 'NULL';
              $imsi_start = $field['imsi_start1'].$field['imsi_start2'];
              $query = $this->externalDatabase->query("SELECT * FROM insert_update_imsi_range(null, 'insert', ".$field['partner_id'].", '".$field['esim_usim']."','".$field['imsi_range_name']."','".$field['mcc']."', '".$field['mnc']."','".$imsi_start."', '".$field['imsi_count']."',".$ibn.", ".$notes.")");
            } elseif($r == 'partner') {
              $query = $this->externalDatabase->query("insert into $r($col) values ($colvalue)");
              //add data in group type
              // Load the group type. Replace 'your_group_type' with the actual group type ID.
              $group_type = GroupType::load('tamu'); //group type machine name

              if ($group_type) {
                // Create a new group entity.
                $group = Group::create([
                  'type' => $group_type->id(),  // Set the group type.
                  'label' => $field['partner_name'], // Set the group title.
                ]);

                // Save the group entity.
                $group->save();

                $current_user = \Drupal::currentUser();
                $uid = $current_user->id();

                // Optionally, add a user to the group. Replace with the actual user ID.
                $user = User::load($uid);
                if ($user) {
                  // Add the user as a member to the newly created group.
                  $group->addMember($user);
                }
                \Drupal::logger('mydata')->info('New group has been created with ID: ' . $group->id());
              }
            } else {
              $query = $this->externalDatabase->query("insert into $r($col) values ($colvalue)");
            }

            if($r == 'imsi_range') {
              unset($_SESSION['mcc']);
              unset($_SESSION['mnc']);
              unset($_SESSION['imsi_count']);
              unset($_SESSION['ibn']);
              unset($_SESSION['imsi_start1']);
              unset($_SESSION['imsi_start2']);
              unset($_SESSION['imsi_end']);
              unset($_SESSION['partner_id']);
              unset($_SESSION['imsi_range_name']);
              unset($_SESSION['notes']);
            }
            
            if ($r == 'input_file') {
              $title = "Success";
              $content = 'Successfully Inserted';
              $options = [
                  'buttons' => [
                    'button1' => [
                      'text' => $this->t('Ok'),
                      'id' => 'ok',
                      'onclick' => 'window.open("/input-file","_self")'
                    ],
                  ],
                  'width' => 400
              ];
            } else {
              $title = "Success";
              $content = 'Successfully Inserted';
              $options = [
                  'buttons' => [
                    'button1' => [
                      'text' => $this->t('Ok'),
                      'id' => 'ok',
                      'onclick' => 'window.open("/mydata/oss/table2?table='.$r.'","_self")'
                    ],
                  ],
                  'width' => 400
              ];
            }
          } catch (\Exception $e) {
            //$error_message = $e->getMessage();
            $prev = $e->getPrevious();
            //$errorMsg = $prev && isset($prev->errorInfo[2]) ? $prev->errorInfo[2] : $e->getMessage();
            $title = "Error";
            if($r == 'partner') {
              $content = "A partner with the name ".$field['partner_name']." already exists in the system. Please enter a unique partner name.";
              \Drupal::logger('mydata')->info($content);
            } else {
              $content = $prev && isset($prev->errorInfo[2]) ? $prev->errorInfo[2] : $e->getMessage();
            }
            $options = [
                'buttons' => [
                  'button1' => [
                    'text' => $this->t('Ok'),
                    'id' => 'ok',
                    'onclick' => "jQuery('.ui-dialog-titlebar-close').click()",
                  ],
                ],
                'width' => 700
            ];
          }
        } //else
      } //validation
      $response->addCommand(new OpenModalDialogCommand($title, $content, $options));
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    }
    return $response;
  }
}