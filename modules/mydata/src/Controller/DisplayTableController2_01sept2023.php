<?php
//eSIM third database settings used
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Drupal\Core\Render\Markup;
use Symfony\Component\HttpFoundation\Response;
use Drupal\Core\Access\AccessResult;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Class DisplayTableController.
 *
 * @package Drupal\mydata\Controller
 */
class DisplayTableController2 extends ControllerBase {
  protected $currentUser;
  protected $languagemanager;
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
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $defaultconnection
   *   The default database connection.
   * @param \Drupal\Core\Database\Connection $externalconnection
   *   The external database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(AccountProxyInterface $current_user, Connection $defaultconnection, Connection $externalconnection, LanguageManagerInterface $languagemanager, SessionInterface $session, RequestStack $request_stack) {
    $this->currentUser = $current_user;
    $this->defaultDatabase = $defaultconnection;
    $this->externalDatabase = $externalconnection;
    $this->languagemanager = $languagemanager;
    $this->session = $session;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    // Create instances of the database connections.
    $current_user = $container->get('current_user');
    $defaultconnection = $container->get('database');
    $externalconnection = $container->get('mydata.external_database');
    $languagemanager = $container->get('language_manager');
    $session = $container->get('session');
    $request = $container->get('request_stack');

    // Return the controller object with the injected database connections.
    return new static($current_user, $defaultconnection, $externalconnection, $languagemanager, $session, $request);
  }

  public function getContent() {
    $build = [
      'description' => [
        '#theme' => 'mydata_description',
        '#description' => 'foo',
        '#attributes' => [],
      ],
    ];
    return $build;
  }

  /**
   * Display.
   *
   * @return string
   *   Return Hello string.
   */
  public function display() {
    //Encryption technique
    $decrypted = '';
    $table = $this->request->query->get('table');
    if (strlen($table) > 50) {
      $encryption_profile = EncryptionProfile::load('url');
      $decrypted = \Drupal::service('encryption')->decrypt($table, $encryption_profile);
      $r = $decrypted;
    }
    if ($decrypted == '') {
     $r = $table;
    }
    $field = $this->request->query->get('field');
    $column = $this->request->query->get('column');
    $quicksearchfield = $this->request->query->get('q');
    $exportdata = $this->request->query->get('export');
    $exportalldata = $this->request->query->get('exportall');
    $type = $this->request->query->get('type');
    $config = \Drupal::config('xai.settings');
    $str = json_decode($config->get('bio'));
    $base_path = $config->get('domain');
    $data = [];
    $header_table1 = $str->$r->table_listing_fields;
    $header_table2 = $str->$r->table_listing_fields;
    //current user load
    //$user = \Drupal::currentUser();
    $user = $this->currentUser->getAccount();
    $roles = $user->getRoles();
    $tablerole = $str->$r->access_role;
    $primary_key = $str->$r->primary_key;
    $composite_key = $str->$r->composite_key;
    $info_param = $str->$r->info_param;
    $results = [];
    $export = [];
    $current_language_id = $this->languagemanager->getCurrentLanguage()->getId();
    if ($current_language_id=='en') {
      $header_alias = $str->$r->alias_en;
      $title = $str->$r->title_en;
    }
    else {
      $header_alias = $str->$r->alias_ja;
      $title = $str->$r->title_ja;
    }

    //info access only for esim_cs and esim_admin role
    if ((in_array('esim_cs', $roles)) || (in_array('esim_admin', $roles)) || (in_array('administrator', $roles))) {
      $info = $str->$r->info_tag;
    }
    else {
      $info = false;
    }

    //Reaonly for esim_cs and usim_cs,customer_integration,tenant_management,sim_tech
    if ($r == 'imsi_range' &&
               (
                    in_array("esim_cs", $roles) ||
                    in_array("usim_cs", $roles) ||
                    in_array("customer_integration", $roles) ||
                    in_array("tenant_management", $roles) ||
                    in_array("esim_readonly", $roles) ||
                    in_array("sim_tech", $roles)
                )
    ) {
        $exclude = ['Edit', 'imsi_range_id', 'partner_id'];
        $header_table1 = array_diff($header_table1, $exclude);
        $header_alias = array_diff($header_alias, $exclude);
    }

    if ($r == 'imsi_range' &&
               (                    
                    in_array("esim_admin", $roles) ||
                    in_array("usim_admin", $roles)
                )
    ) {
        $exclude = ['imsi_range_id', 'partner_id'];
        $header_table1 = array_diff($header_table1, $exclude);
        $header_alias = array_diff($header_alias, $exclude);
    }
    
    if (array_intersect($roles, $tablerole)) {
      //if user has specified role then display
      foreach ($header_table1 as $htable) {
        //ignored the Edit, Delete,etc because this is not part of actual table
        if (strtolower('Edit') == strtolower($htable) || strtolower('Delete') == strtolower($htable) || strtolower('partner name') == strtolower($htable) || strtolower('parent_partner_name') == strtolower($htable) ) {
          $header_table[] = $htable;
        } else {
          $datatmp = array(
            'data' => $htable,
            'field' => $htable,
            'sort' => 'asc'
          );
          $header_table[] = $datatmp;
        } //end else
      } //end for

      $query = $this->externalDatabase->select($r, 'm');

      //Default sort order for the Input Element form first by input_file_id then by position
      if ($r =='input_element') {
        $query->orderBy('input_file_id');
        $query->orderBy('position');
      }

      //Default sor order for the partner table
      if ($r =='partner') {
        $query->orderBy('partner_name');
      }

      if ($r =='imsi_range') {
        $query->orderBy('imsi_range_name');
      }

      if ($r =='esim_usim_partner_counts') {
        $query->orderBy('partner_name');
      }

      if ($r =='esim_profile_type') {
        $query->orderBy('esim_profile_type');
      }

      //Quicksearch for usim_provision_vw
      if (($quicksearchfield) && ($r=='usim_provision_vw')) {
        $orgroup = $query->orConditionGroup()
                          ->condition('m.partner_name', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.master_id', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.imsi', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.msisdn', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.iccid', "%".$quicksearchfield."%", 'LIKE');
          
        // Add the group to the query.
        $query->condition($orgroup);
        $query->orderBy('provision_date', 'DESC');
        $query->orderBy('master_id', 'ASC');
      }
      //Quicksearch for usim_provision_vw end

      //Quicksearch for esim_provision
      if (($quicksearchfield) && ($r=='esim_provision')) {
        $orgroup = $query->orConditionGroup()
                          ->condition('m.esim_file_name', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.iccid', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.imsi', "%".$quicksearchfield."%", 'LIKE');
          
        // Add the group to the query.
        $query->condition($orgroup);
      }
      //Quicksearch for esim_provision end

      //Quicksearch for provision_history
      if (($quicksearchfield) && ($r=='provision_history')) {
        $orgroup = $query->orConditionGroup()
                          ->condition('m.master_id', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.imsi', "%".$quicksearchfield."%" , 'LIKE')
                          ->condition('m.msisdn', "%".$quicksearchfield."%" , 'LIKE')
                          ->condition('m.iccid', "%".$quicksearchfield."%" , 'LIKE');
          
        // Add the group to the query.
        $query->condition($orgroup);
      }
      //Quicksearch for provision_history end

      //Query string as per column and field
      if (($field) || ($column)) {
        if ($column=='partner name') { //in this table partner_name column not available
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        } else {
          $query->condition('m.' . $column . '',
            $field,
            'LIKE'
          );
        }
      }
      //end Query string as per column and field

      //Only applicable for esim_10t_status_view table
      if ($r == 'esim_10t_status_vw') {
        //$user_display_name = $user->getDisplayName();
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
      }

      //Only applicable for imsi_range table and role esim_admin,esim_cs
      if (($r == 'imsi_range') && (in_array("esim_admin", $roles) || in_array("esim_cs", $roles))) {
        //$user_display_name = $user->getDisplayName();
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
        $query->condition('m.esim_usim', 'ESIM', '=');
      }

      //Only applicable for imsi_range table and role usim_admin,usim_cs
      if (($r == 'imsi_range') && (in_array("usim_admin", $roles) || in_array("usim_cs", $roles))) {
        //$user_display_name = $user->getDisplayName();
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
        $query->condition('m.esim_usim', 'USIM', '=');
      }

      if (($r == 'imsi_range') && (in_array("customer_integration", $roles) || in_array("tenant_management", $roles) || in_array("esim_readonly", $roles) || in_array("sim_tech", $roles))) {
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
      }

      //Remove partner name because this is not part of table
      if (($r == 'imsi_range') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove partner name because this is not part of table
      if (($r == 'esim_profile_type') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }
    

      //Only applicable for  usim_provision_vw table
      if ($r == 'usim_provision_vw') {
        //$user_display_name = $user->getDisplayName();
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
      }

      //Remove partner name because this is not part of table
      if (($r == 'esim_provision') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove partner name because this is not part of table
      if (($r == 'provision_history') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove parent_partner_name because this is not part of table
      if (($r == 'partner') && (($key = array_search('parent_partner_name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove Edit, Delete option while fetching data from database
      if ((in_array("Edit", $header_table2)) && (in_array("Delete", $header_table2))) {
        $values = array_splice($header_table2,
          0,
          -2
        );
        $query->fields('m', $values);
      } elseif (in_array("Edit", $header_table2)) {
        $values = array_splice($header_table2,
          0,
          -1
        );
        $query->fields('m', $values);
      } elseif (in_array("Delete", $header_table2)) {
        $values = array_splice($header_table2,
          0,
          -1
        );
        $query->fields('m', $values);
      } else {
        $query->fields('m', $header_table2);
      }

      if ($header_table) {
        $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')
        ->orderByHeader($header_table);
        $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      } else {
        $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      }

      $result = $pager->execute()->fetchAll();

      //Export all data in CSV
      $exportallresults = [];
      if ($exportalldata == 1) { //querystring 1 then execute
          if ($header_table) {
          $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')
          ->orderByHeader($header_table);
          $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(5000);
        } else {
          $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(5000);
        }

        $exportallresult = $pager->execute()->fetchAll();

        foreach ($exportallresult as $key => $data) {
          if (!in_array("Edit", $header_table)) {
            $exportallresults[] = (array) $data;
          }
          else {
            $exportallresults[] = (array) $data;
          }
        }
      }
      //Export all data in CSV end

      $rows = array();
      foreach ($result as $key => $data) {
        if ((!in_array("Edit", $header_table)) && (!in_array("Delete", $header_table))) {
          //for view no crud(no edit and delete)
          $export[] = (array) $data;

          if ($r != 'esim_provision' && $r != 'provision_history' && $r != 'partner' && $r != 'imsi_range') {
            $results[] = (array) $data;
          }

          if ($r== 'imsi_range') {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  //'imsi_range_id'=>$data->imsi_range_id,
                  //'partner_id'=>$data->partner_id,
                  'partner name'=>$output[0]->partner_name,
                  'esim_usim'=>$data->esim_usim,
                  'imsi_range_name'=>$data->imsi_range_name,
                  'mcc'=>$data->mcc,
                  'mnc'=>$data->mnc,
                  //'mnc_length'=>$data->mnc_length,
                  'imsi_start'=>$data->imsi_start,
                  'imsi_count'=>$data->imsi_count,
                  'status'=>$data->status,
                  'ibn'=>$data->ibn,
                  'date_created'=>$data->date_created,
                  'notes'=>$data->notes
                );  
          }

          //partner_name not available in esim_provision
          if ($r== 'esim_provision') {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  'partner_id'=>$data->partner_id,
                  'partner name'=>$output[0]->partner_name,
                  'esim_provision_id'=>$data->esim_provision_id,
                  'esim_file_name'=>$data->esim_file_name,
                  'iccid'=>$data->iccid,
                  'imsi'=>$data->imsi,
                  'pin1'=>$data->pin1,
                  'puk1'=>$data->puk1,
                  'pin2'=>$data->pin2,
                  'puk2'=>$data->puk2,
                  'adm'=>$data->adm,
                  'keyset01kic'=>$data->keyset01kic,
                  'keyset01kid'=>$data->keyset01kid,
                  'keyset01kik'=>$data->keyset01kik,
                  'keyset01tslpsk'=>$data->keyset01tslpsk,
                  'keyset01tlsdek'=>$data->keyset01tlsdek,
                  'msisdn'=>$data->msisdn
                );
          }
          //partner_name not available in provision_history
          if ($r== 'provision_history') {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  'partner_id'=>$data->partner_id,
                  'partner_name'=>$output[0]->partner_name,
                  'master_id'=>$data->master_id,
                  'imsi'=>$data->imsi,
                  'iccid'=>$data->iccid,
                  'msisdn'=>$data->msisdn,
                  'created_at'=>$data->created_at
                );
          }
          //ki asterisk or eye
          if ($data->ki) {
          $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?ki=1&table={$r}&num={$data->$primary_key}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['ki'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }
          //opc asterisk or eye
          if ($data->opc) {
          $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?opc=1&table={$r}&num={$data->$primary_key}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['opc'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }
          //static value 1 become true and false
          if (($data->static == 1) && ($r== 'input_element')) {
          $results[$key]['static'] = 'TRUE';
          }
          if (($data->static == 0) && ($r== 'input_element')) {
          $results[$key]['static'] = 'FALSE';
          }
          //date format change
          if (($data->created_at) && ($r == 'esim_10t_status_vw')) {
            $newDate = date("Y-m-d", strtotime($data->created_at));
            $results[$key]['created_at'] = $newDate;
          }
          //partner op asterisk
          if ($data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?op=1&table={$r}&num={$data->$primary_key}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['op'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }
          //json_secrets checkbox true
          if ($data->json_secrets == true) {
              $url = Url::fromUri("{$base_path}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-check-square-o',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['json_secrets'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }
          if ($info) {
            //Info
            $url = Url::fromUri("{$base_path}admin/config/modal_form2?table={$r}&iccid={$data->$info_param}");
            $link_options = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
            $url->setOptions($link_options);
            $results[$key][] = Link::fromTextAndUrl('Info', $url->setOptions($link_options))->toString();
            //end Info
            //QR
            if ($data->state == 'RELEASED') {
              $url2 = Url::fromUri("{$base_path}admin/config/qr_form?table={$r}&iccid={$data->$info_param}");
              $link_options2 = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options2);
              $results[$key][] = Link::fromTextAndUrl('QR', $url2->setOptions($link_options2))->toString();
            }//end QR
            //RELEASED
            if ($data->state == 'DELETED') {
              $url2 = Url::fromUri("{$base_path}admin/config/release_form?table={$r}&iccid={$data->$info_param}");
              $link_options2 = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options2);
              $results[$key][] = Link::fromTextAndUrl('Release', $url2->setOptions($link_options2))->toString();
            } //end RELEASED
          }
          //
        } else {
          //crud(Edit, Delete)
          $export[] = (array) $data;

          //only partner id=0
          if (($r == 'partner') && ($data->$primary_key == 0)) {        
            $edit   = Url::fromUserInput('/mydata/form/mydata2?num=00&table=' . $r);
            $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key . '?table=' . $r);
          } elseif($r == 'input_element') {
            //added one more parameter if input_element table
            $edit   = Url::fromUserInput('/mydata/form/mydata2?num='.$data->$primary_key.'&num1='.$data->$composite_key.'&table=' . $r);
            $delete = Url::fromUserInput('/mydata/form/delete2/'.$data->$primary_key.'?&num1='.$data->$composite_key.'&input_element='.$data->input_element.'&table=' . $r);
          } elseif($r == 'input_file_detail') {
            //added one more parameter if input_file_detail table 
            $edit   = Url::fromUserInput('/mydata/form/mydata2?num='.$data->$primary_key.'&num1='.$data->$composite_key.'&table=' . $r);
            $delete = Url::fromUserInput('/mydata/form/delete2/'.$data->$primary_key.'?&mnoid='.$data->mnoid.'&profile_type='.$data->profile_type.'&table=' . $r);
          } else {
          $edit   = Url::fromUserInput('/mydata/form/mydata2?num=' . $data->$primary_key . '&table=' . $r);
          $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key . '?table=' . $r);
          }
          //Added because parent_partner_name need to add which is not in partner table
          if ($r== 'partner') {
            $results[] = array(
              'partner_id'=>$data->partner_id,
              'partner_name'=>$data->partner_name,
              'op'=>$data->op,
              'json_secrets'=>$data->json_secrets,
              'parent_partner_id'=>$data->parent_partner_id,
              'parent_partner_name'=>''
            );       
          } elseif ($r == 'imsi_range' &&
               (                    
                    in_array("esim_admin", $roles) ||
                    in_array("usim_admin", $roles)
                )
            ) {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  //'imsi_range_id'=>$data->imsi_range_id,
                  //'partner_id'=>$data->partner_id,
                  'partner name'=>$output[0]->partner_name,
                  'esim_usim'=>$data->esim_usim,
                  'imsi_range_name'=>$data->imsi_range_name,
                  'mcc'=>$data->mcc,
                  'mnc'=>$data->mnc,
                  //'mnc_length'=>$data->mnc_length,
                  'imsi_start'=>$data->imsi_start,
                  'imsi_count'=>$data->imsi_count,
                  'status'=>$data->status,
                  'ibn'=>$data->ibn,
                  'date_created'=>$data->date_created,
                  'notes'=>$data->notes
                );
          }elseif ($r== 'imsi_range') {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  'imsi_range_id'=>$data->imsi_range_id,
                  'partner_id'=>$data->partner_id,
                  'partner name'=>$output[0]->partner_name,
                  'esim_usim'=>$data->esim_usim,
                  'imsi_range_name'=>$data->imsi_range_name,
                  'mcc'=>$data->mcc,
                  'mnc'=>$data->mnc,
                  //'mnc_length'=>$data->mnc_length,
                  'imsi_start'=>$data->imsi_start,
                  'imsi_count'=>$data->imsi_count,
                  'status'=>$data->status,
                  'ibn'=>$data->ibn,
                  'date_created'=>$data->date_created,
                  'notes'=>$data->notes
                );  
          } elseif ($r== 'esim_profile_type') {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  'esim_profile_type_id'=>$data->esim_profile_type_id,
                  'esim_profile_type'=>$data->esim_profile_type,
                  'partner_id'=>$data->partner_id,
                  'partner name'=>$output[0]->partner_name,
                  'created_at'=>$data->created_at
                );       
          } else {
            $results[] = (array) $data;
          }          
          //if in json array Edit then show this
          if (in_array("Edit", $header_table2)) {
            $results[$key][] = Link::fromTextAndUrl(t('Edit'), $edit)->toString();
          }
          //if in json array Delete then show this
          if (in_array("Delete", $header_table2)) {
            $results[$key][] = Link::fromTextAndUrl('Delete', $delete)->toString();
          }
          if (($r == 'partner') && ($data->parent_partner_id)) {
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id=".$data->parent_partner_id."");
            $output = $query->fetchAll();
            $results[$key]['parent_partner_name'] = $output[0]->partner_name;
          }
          //partner op asterisk or eye view
          if ($data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?op=1&table={$r}&partner_name={$data->partner_name}&num={$data->$primary_key}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['op'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }
          //json_secret value 1 show checked checbox
          if (($data->json_secrets == 1) && ($r== 'partner')) {
              $url = Url::fromUri("{$base_path}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-check-square',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['json_secrets'] = Link::fromTextAndUrl('', $url->setOptions($link_options))->toString();
          }
          //json_secret value 0 show checbox
          if (($data->json_secrets == 0)&& ($r== 'partner')) {
              $url = Url::fromUri("{$base_path}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-square',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['json_secrets'] = Link::fromTextAndUrl('', $url->setOptions($link_options))->toString();
          }
          //static value 1 become true and false
          if (($data->static == 1) && ($r== 'input_element')) {
          $results[$key]['static'] = 'TRUE';
          }
          if (($data->static == 0) && ($r== 'input_element')) {
          $results[$key]['static'] = 'FALSE';
          }
          if ($info) {
            //Info
            $url = Url::fromUri("{$base_path}admin/config/modal_form2?table={$r}&iccid={$data->$info_param}");
            $link_options = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
            $url->setOptions($link_options);
            $results[$key][] = Link::fromTextAndUrl('Info', $url->setOptions($link_options))->toString();
            //end Info
            //QR
            if ($data->state == 'RELEASED') {
              $url2 = Url::fromUri("{$base_path}admin/config/qr_form?table={$r}&iccid={$data->$info_param}");
              $link_options2 = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options2);
              $results[$key][] = Link::fromTextAndUrl('QR', $url2->setOptions($link_options2))->toString();
            }//end QR
            //RELEASED
            if ($data->state == 'DELETED') {
              $url2 = Url::fromUri("{$base_path}admin/config/release_form?table={$r}&iccid={$data->$info_param}");
              $link_options2 = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options2);
              $results[$key][] = Link::fromTextAndUrl('Release', $url2->setOptions($link_options2))->toString();
            }//end RELEASED
          }// end Info
        } //else crud
      }//end foreach
      //csv export
      if (($exportdata == '1') && ($r !='partner')) {
        $handle = fopen('php://temp', 'w+');
        if ((!in_array("Edit", $header_table1)) && (!in_array("Delete", $header_table1))) {
          fputcsv($handle, $header_table1);
          foreach ($results as $value) {
            fputcsv($handle, array_values($value));
          }
        } elseif (!in_array("Edit", $header_table1)) {
          fputcsv($handle, array_splice($header_table1, 0, -1));
          foreach ($results as $value) {
            $values = array_splice($value, 0, -1);
            fputcsv($handle, array_values($values));
          }
        } elseif (!in_array("Delete", $header_table1)) {
          fputcsv($handle, array_splice($header_table1, 0, -1));
          foreach ($results as $value) {
            $values = array_splice($value, 0, -1);
            fputcsv($handle, array_values($values));
          }
        } else {
          fputcsv($handle, array_splice($header_table1, 0, -2));
          foreach ($results as $value) {
            $values = array_splice($value, 0, -2);
            fputcsv($handle, array_values($values));
          }
        }
        rewind($handle);
        $csv_data = stream_get_contents($handle);
        fclose($handle);
        $response = new Response();
        if ($type == 'xls') {
          $response->headers->set('Content-Type', 'application/vnd.ms-excel');
          //set filename session in jcius_master.theme
          if ($this->session->get('current_partner_name')) {
            if ($r =='esim_usim_partner_counts') {
              $filename = "esim_usim_partner_counts";
            }
            elseif ($r =='esim_profile_type') {
              $filename = "esim_profile_type_".date("Ymd");
            } else {
              $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            }
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report.'.$type.'"');
          } else {
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report.'.$type.'"');
          }
        } else {
          $response->headers->set('Content-Type', 'text/csv');
          //set filename session in jcius_master.theme        
          if ($this->session->get('current_partner_name')) {
            if ($r =='esim_usim_partner_counts') {
              $filename = "esim_usim_partner_counts";
            }
            elseif ($r =='esim_profile_type') {
              $filename = "esim_profile_type_".date("Ymd");
            } else {
              $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            }
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report.csv"');
          } else {
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report.csv"');
          }
        }      
        $response->setContent($csv_data);
        return $response;
      } //csv export end
      //csv export all
      if (($exportalldata == '1') && ($r !='partner')) {
        $handle = fopen('php://temp', 'w+');
        $remove=array("Edit", "Delete","partner name");
        $header_table1=array_diff($header_table1, $remove);
        fputcsv($handle, $header_table1);
        foreach ($exportallresults as $value) {
          fputcsv($handle, array_values($value));
        }
        rewind($handle);
        $csv_data = stream_get_contents($handle);
        fclose($handle);
        $response = new Response();
        if ($type == 'xls') {
          
          $response->headers->set('Content-Type', 'application/vnd.ms-excel');
          //set filename session in jcius_master.theme
          if ($this->session->get('current_partner_name')) {  
            if ($r =='esim_usim_partner_counts') {
              $filename = "esim_usim_partner_counts";
            }
            elseif ($r =='esim_profile_type') {
              $filename = "esim_profile_type_".date("Ymd");
            } else {
              $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            }
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report_all.'.$type.'"');
          } else {
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report-all.'.$type.'"');
          }
        } else {
          $response->headers->set('Content-Type', 'text/csv');
          //set filename session in jcius_master.theme 
          if ($this->session->get('current_partner_name')) {
            if ($r =='esim_usim_partner_counts') {
              $filename = "esim_usim_partner_counts";
            }
            elseif ($r =='esim_profile_type') {
              $filename = "esim_profile_type_".date("Ymd");
            } else {
              $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            }
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report_all.csv"');
          } else {
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report-all.csv"');
          }
        }
        $response->setContent($csv_data);
        return $response;
      } //csv export all end
      $form = [];
      $form['form'] = [
        '#type'  => 'form',
      ];
      $form['form']['filters'] = [
        '#type'  => 'details',
        '#title' => $this->t('Search / Options'),
        '#open'  => false,
        '#attributes' => array('class' => array('fa'),'title' => t("Search / Options"))
      ];
      //fuzzy search only for usim_provision_vw
      if (($r =='usim_provision_vw') || ($r =='esim_provision') || ($r =='provision_history')) {
        $form['form']['filters']['quick_search'] = [
            '#title'         => $this->t('Search Value'),
            '#type'          => 'textfield',
            '#value'         => $quicksearchfield,
            '#attributes' => array('class' => array('custom-quicksearch')),
            '#wrapper_attributes' => ['class' => ['custom-search-quicksearch-wrapper'],'title' => t("Search Value")],
          ];
        $form['form']['filters']['quick_submit'] = [
          '#type'  => 'button',
          '#value' => $this->t('Quick Search'),
          '#button_type' => 'primary',
          '#default_value' => '',
          '#attributes' => array('class' => array('custom-filter-quicksubmit'),'title' => t("Quick Search"))
        ];
      } //end fuzzy search only for usim_provision_vw

      if ($r !== 'usim_provision_vw') {
        $form['form']['filters']['text'] = [
            '#title'         => $this->t('Search Value'),
            '#type'          => 'search',
            '#required' => TRUE,
            '#value' => $field,
            '#attributes' => array('class' => array('custom-search'),'title' => t("Enter Search value, select the Column to search and click Search button.")),
            '#wrapper_attributes' => ['class' => ['custom-search-wrapper'],'title' => t("Search Value")],
          ];     
        //remove edit option from header_table1
        $headernew = $header_table1;
        if ((in_array("Edit", $headernew)) && (in_array("Delete", $headernew))) {
          $headervalues = array_splice($headernew, 0, -2);
        } elseif (in_array("Edit", $headernew)) {
          $headervalues = array_splice($headernew, 0, -1);
        } elseif (in_array("Delete", $headernew)) {
          $headervalues = array_splice($headernew, 0, -1);
        } else {
          $headervalues =  $headernew;
        }
        //query column key value
        foreach ($headervalues as $key => $value) {
          if ($value == $column) {
            $default_key =$key;
          }
        }
        $form['form']['filters']['column'] = [
          '#title'         => $this->t('Search Column'),
          '#type'          => 'select',
          '#empty_value'   => 'none',
          '#empty_option'  => '- None -',
          '#size'          => 0,
          '#options'       => $headervalues,
          '#default_value' => 'none',
          '#value' => (isset($default_key)) ? $default_key : '',
          '#attributes' => array('id' => array('custom-header-colmn'),'title' => t("Search Column"))
        ];
        $form['form']['filters']['actions'] = [
          '#type'       => 'actions'
        ];
        $form['form']['filters']['actions']['submit'] = [
          '#type'  => 'submit',
          '#value' => $this->t('Search'),
          '#button_type' => 'primary',
          '#default_value' => '',
          '#attributes' => array('class' => array('custom-filter-btn'),'title' => t("Search"))
        ];
      } //if
      if ($r != 'partner') {
        $form['form']['filters']['actions']['exportselect'] = [
          '#type'          => 'select',
          "#weight" => 2,
          '#empty_value'   => 'none',
          '#empty_option'  => '- None -',
          '#size'          => 0,
          '#options'       => ['export_csv'=>'Export CSV','export_all_csv'=>'Export All CSV','export_xls'=>'Export Excel','export_all_xls'=>'Export All Excel'],
          '#value'         => 'export_csv',
          '#attributes' => array('id' => array('export-csv'),'title' => t("Export Line Report")),
          '#wrapper_attributes' => ['class' => ['export_csv_wrapper'],'title' => t("Enter the search value. Select a column to search. Press the Search Button")],
        ];
        $form['form']['filters']['actions']['exportbutton'] = [
          '#type'  => 'submit',
          "#weight" => 3,
          '#value' => $this->t('Export'),
          '#button_type' => 'primary',
          '#default_value' => '',
          '#attributes' => array('class' => array('export-btn'),'title' => t("Export currently filtered or all Lines to CSV or Excel"))
        ];
      } //if
      $form['form']['filters']['actions']['button2'] = [
        '#type'  => 'button',
        '#value' => $this->t('Reset'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#attributes' => array('class' => array('custom-reset-btn'),'title' => t("Reset search values and display all records."))
      ];
      if ($r == 'esim_10t_status_vw') {
        $preprod   = Url::fromUserInput('/mydata/oss/tables2?table=esim_10t_status_vw');
        $form['form']['filters']['actions']['preprod'] = [
          '#type' => 'link',
          "#weight" => 1,
          '#url' => $preprod,
          '#attributes' => ['target' => '_self', 'class' => 'prod fa fa-toggle-on','title' => t("Production view")],
        ];
        $form['form']['filters']['actions']['preprodbutton'] = [
          '#type' => 'link',
          '#title' => $this->t(' Prod'),
          "#weight" => 1,
          '#url' => $preprod,
          '#attributes' => ['target' => '_self', 'class' => ''],
        ];
      } //if
      if (($r == 'partner') || ($r == 'imsi_range') || ($r == 'esim_profile_type')) {
        $addnewurl   = Url::fromUserInput('/mydata/form/mydata2?table=' . $r);

        $form['form']['filters']['actions']['new'] = [
          '#type' => 'link',
          '#title' => $this->t('Add New'),
          "#weight" => 1,
          '#url' => $addnewurl,
          '#attributes' => ['target' => '_self', 'class' => 'add-new button button--primary js-form-submit form-submit','title' => t("Add New")],
        ];
      } //if    
      //if header alias else keep orignal
      if ($header_alias) {
        $header_column = $header_alias;
      } else {
        $header_column = $header_table;
      } //else
      //esim_10t_status_vw addtional header column
      if ($r=='esim_10t_status_vw') {
        $user = \Drupal::currentUser();
        $roles = $user->getRoles();
        $tablerole = $str->$r->access_role;
        $value = ['INFO','ACTION'];
        if ((in_array('esim_cs', $roles)) || (in_array('esim_admin', $roles)) || (in_array('administrator', $roles))) {
          array_push($header_column,"INFO","ACTION");
        }
      } //end esim_10t_status_vw addtional header column
      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $results,
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table')),
        '#prefix' => "<h1 class='uppar-case'>".$title."</h1>"
      ];
      // Finally add the pager.
      $form['pager'] = array(
        '#type' => 'pager'
      );
      $form['#cache'] = ['max-age' => 0];
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
    } else { //end if user has role then display else access denied
      $form['form'] = [
        '#prefix'  => '<h1>Access Denied</h1>',
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
      ];
      // Attach the library for pop-up dialogs/modals.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
    } //else access denied
  }//display function
} //DisplayTableController
