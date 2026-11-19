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
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

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
  public function __construct(
    AccountProxyInterface $current_user,
    Connection $defaultconnection,
    Connection $externalconnection,
    LanguageManagerInterface $languagemanager,
    SessionInterface $session,
    RequestStack $request_stack
  ) {
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

  // Function to fetch related partners recursively
  function fetchRelatedPartners($parentId, &$all_related_partners) {
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
    $tablerole = isset($str->$r->access_role) ? $str->$r->access_role : null;
    $primary_key = isset($str->$r->primary_key) ? $str->$r->primary_key : null;
    $composite_key = isset($str->$r->composite_key) ? $str->$r->composite_key : null;
    $info_param = isset($str->$r->info_param) ? $str->$r->info_param : null;

    $results = [];
    $export = [];
    $current_language_id = $this->languagemanager->getCurrentLanguage()->getId();
    if ($current_language_id == 'en') {
      $header_alias = $str->$r->alias_en;
      $title = isset($str->$r->title_en) ? $str->$r->title_en : NULL;
    } else {
      $header_alias = $str->$r->alias_ja;
      $title = isset($str->$r->title_ja) ? $str->$r->title_ja : NULL;
    }

    //info access only for esim_cs and esim_admin role
    if ((in_array('esim_admin', $roles)) || (in_array('administrator', $roles))) {
      $info = isset($str->$r->info_tag) ? $str->$r->info_tag : false;
    } else {
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

    if (isset($r) && $r == 'imsi_range' &&
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
        if (
            strtolower('Edit') == strtolower($htable) ||
            strtolower('Stats') == strtolower($htable) ||
            strtolower('Delete') == strtolower($htable) ||
            strtolower('partner name') == strtolower($htable) ||
            strtolower('parent_partner_name') == strtolower($htable) ||
            strtolower('grand_parent_partner_name') == strtolower($htable)
        ) {
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
      if (isset($r) && $r =='input_element') {
        $query->orderBy('input_file_id');
        $query->orderBy('position');
      }

      //Default sor order for the partner table
      if (isset($r) && $r =='partner') {
        $query->orderBy('partner_name');
      }

      if (isset($r) && $r =='imsi_range') {
        $query->orderBy('imsi_range_name');
      }

      if (isset($r) && $r =='esim_usim_partner_counts') {
        $query->orderBy('partner_name');
      }

      if (isset($r) && $r =='esim_profile_type') {
        $query->orderBy('esim_profile_type');
      }

      //Quicksearch for usim_provision_vw
      if ((isset($quicksearchfield) && $quicksearchfield) && ($r == 'usim_provision_vw')) {
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
      if ((isset($quicksearchfield) && $quicksearchfield) && (isset($r) && $r =='esim_provision')) {
        $orgroup = $query->orConditionGroup()
                          ->condition('m.esim_file_name', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.iccid', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.imsi', "%".$quicksearchfield."%", 'LIKE');

        // Add the group to the query.
        $query->condition($orgroup);
      }
      //Quicksearch for esim_provision end

      //Quicksearch for provision_history
      if ((isset($quicksearchfield) && $quicksearchfield) && (isset($r) && $r =='provision_history')) {
        $orgroup = $query->orConditionGroup()
                          ->condition('m.master_id', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.imsi', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.msisdn', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.iccid', "%".$quicksearchfield."%", 'LIKE');

        // Add the group to the query.
        $query->condition($orgroup);
      }
      //Quicksearch for provision_history end

      //Query string as per column and field
      if ((isset($field) && $field) || (isset($column) && $column)) {
        if (isset($column) && $column == 'partner name') { //in this table partner_name column not available
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        } elseif (isset($column) && $column == 'parent_partner_name') {
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.parent_partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        } elseif (isset($column) && $column == 'grand_parent_partner_name') {
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.grand_parent_partner_id',
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

      if ($user) {
        $user_display_name = $user->getEmail();        
        // Get the user's partner ID
        $queryuser = $this->externalDatabase->query("SELECT sim_partner_id FROM drupal_user WHERE drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        
        // Initialize an array to hold all partner IDs related to partner_id 1
        $all_related_partners = [];
        // Start the recursion with current partner_id
        $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);

        // At this point, $all_related_partners will contain all partner IDs related to current partner_id pass
        $all_related_partners[] = $resultuser[0]->sim_partner_id; // Also include the initial current partner_id        
      }


      //Only applicable for esim_10t_status_view table
      if (isset($r) && $r == 'esim_10t_status_vw') {
        if ((!in_array('administrator', $roles)) || ($resultc[0]->count)) {
          $query->condition('m.partner_id', $all_related_partners, 'IN');
        } else {
          $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
        }
        //dashboard table partner_name
        if($_GET['partner_name']){
          $query->condition('m.partner_name', $_GET['partner_name'], '=');
        }
      }

      //Only applicable for partner table
      if (isset($r) && $r == 'partner') {
        if (in_array('administrator', $roles)) {
          //No filter
        } else {         
          $query->condition('m.partner_id', $all_related_partners, 'IN'); 
        }
      }
      /*
      //Only applicable for imsi_range table and role esim_admin,esim_cs
      if (($r == 'imsi_range') && (in_array("esim_admin", $roles) || in_array("esim_cs", $roles))) {
        if (!in_array('administrator', $roles)) { //adminstrator will see all
          $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
          $query->condition('m.esim_usim', 'ESIM', '=');
        }
      }

      //Only applicable for imsi_range table and role usim_admin,usim_cs
      if (($r == 'imsi_range') && (in_array("usim_admin", $roles) || in_array("usim_cs", $roles))) {
        if (!in_array('administrator', $roles)) { //adminstrator will see all
          $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
          $query->condition('m.esim_usim', 'USIM', '=');
        }
      }
      */
      /*
      if ($r == 'imsi_range' && (in_array("esim_admin", $roles) || in_array("esim_cs", $roles) || in_array("usim_admin", $roles) || in_array("usim_cs", $roles))) {
        if (!in_array('administrator', $roles)) { // adminstrator will see all
            $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
            if ((in_array("esim_admin", $roles) || in_array("esim_cs", $roles)) && (in_array("usim_admin", $roles) || in_array("usim_cs", $roles))) {
              //ESIM & USIM
            } elseif (in_array("esim_admin", $roles) || in_array("esim_cs", $roles)) {
                $query->condition('m.esim_usim', 'ESIM', '=');
            } elseif (in_array("usim_admin", $roles) || in_array("usim_cs", $roles)) {
                $query->condition('m.esim_usim', 'USIM', '=');
            }
        }
      }

      if (
          ($r == 'imsi_range') &&
          (
              in_array("customer_integration", $roles) ||
              in_array("tenant_management", $roles) ||
              in_array("esim_readonly", $roles) ||
              in_array("sim_tech", $roles)
          )
      ) {
        if (!in_array('administrator', $roles)) { //adminstrator will see all
          $query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
        }
      }
      */
      if (isset($r) && $r == 'imsi_range') {
          $esimOrUsimRole = in_array("esim_admin", $roles) || in_array("esim_cs", $roles);
          $usimRole = in_array("usim_admin", $roles) || in_array("usim_cs", $roles);
          $authorizedRole = in_array("customer_integration", $roles) ||
                            in_array("tenant_management", $roles) ||
                            in_array("esim_readonly", $roles) ||
                            in_array("sim_tech", $roles);

          if (!in_array('administrator', $roles)) { // Admin will see all
            $query->condition('m.partner_id', $all_related_partners, 'IN');
          }

          if ($esimOrUsimRole && $usimRole) {
              // ESIM & USIM
          } elseif ($esimOrUsimRole) {
              $query->condition('m.esim_usim', 'ESIM', '=');
          } elseif ($usimRole) {
              $query->condition('m.esim_usim', 'USIM', '=');
          } elseif ($authorizedRole) {
              // Handle additional conditions for authorized roles
          }
      }

      //Remove partner name because this is not part of table
      if ((isset($r) && $r == 'imsi_range') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove partner name because this is not part of table
      if ((isset($r) && $r == 'esim_profile_type') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }


      //Only applicable for  usim_provision_vw table
      if (isset($r) && $r == 'usim_provision_vw') {
        if (!in_array('administrator', $roles)) { //adminstrator will see all
          $query->condition('m.partner_id', $all_related_partners, 'IN');
        }
      }

      //Remove partner name because this is not part of table
      if ((isset($r) && $r == 'esim_provision') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove partner name because this is not part of table
      if ((isset($r) && $r == 'provision_history') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove parent_partner_name because this is not part of table
      if ((isset($r) && $r == 'partner') && (($key = array_search('parent_partner_name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      /*
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

      */

      if (in_array("Edit", $header_table2) || in_array("Delete", $header_table2) || in_array("Stats", $header_table2)) {
        $removeOptions = ["Edit", "Delete","Stats", "grand_parent_partner_name"];
        $header_table_removedOptions = array_diff($header_table2, $removeOptions);
        $query->fields('m', $header_table_removedOptions);
      } else {
        $query->fields('m', $header_table2);
      }
      
      // Get the total count of records without the ->range() limit.
      $count_query = $query->countQuery();

      // Execute the count query and fetch the result.
      $total_records = $count_query->execute()->fetchField();

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

          if (isset($r) && $r == 'imsi_range') {
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
          if (isset($r) && $r == 'esim_provision') {
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
          if (isset($r) && $r == 'provision_history') {
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
          //stats condition for normal user
          if (isset($r) && $r == 'imsi_range') {
            $url = Url::fromUri("{$base_path}admin/config/stats?imsi_range_id={$data->$primary_key}&imsi_range_name={$data->imsi_range_name}");
            $link_options_info_edit = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
            $url->setOptions($link_options_info_edit);
            $results[$key][] = Link::fromTextAndUrl('Stats', $url->setOptions($link_options_info_edit))->toString();
          }
          //ki asterisk or eye
          if (isset($data->ki) && $data->ki) {
          $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?ki=1&table={$r}&num={$data->$primary_key}");
              $link_options_ki = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options_ki);
              $results[$key]['ki'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_ki))->toString();
          }
          //opc asterisk or eye
          if (isset($data->opc) && $data->opc) {
          $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?opc=1&table={$r}&num={$data->$primary_key}");
              $link_options_opc = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options_opc);
              $results[$key]['opc'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_opc))->toString();
          }
          //static value 1 become true and false
          if ((isset($data->static) && $data->static == 1) && ($r == 'input_element')) {
          $results[$key]['static'] = 'TRUE';
          }
          if ((isset($data->static) && $data->static == 0) && ($r == 'input_element')) {
          $results[$key]['static'] = 'FALSE';
          }
          //date format change
          if ((isset($data->created_at) && $data->created_at) && ($r == 'esim_10t_status_vw')) {
            $newdate = date("Y-m-d", strtotime($data->created_at));
            $results[$key]['created_at'] = $newdate;
          }
          //partner op asterisk
          if (isset($data->op) && $data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?op=1&table={$r}&num={$data->$primary_key}&partner_name={$data->partner_name}");
              $link_options_op = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options_op);
              $results[$key]['op'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_op))->toString();
          }

          //partner k4 asterisk
          if (isset($data->k4) && $data->k4) {
              $urlk4 = Url::fromUri("{$base_path}admin/config/modal_form_partner?k4=1&table={$r}&num={$data->$primary_key}&partner_name={$data->partner_name}");
              $link_options_k4 = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $urlk4->setOptions($link_options_k4);
              $results[$key]['k4'] = Link::fromTextAndUrl(' ', $urlk4->setOptions($link_options_opk4))->toString();
          }

          //json_secrets checkbox true
          if (isset($data->json_secrets) && $data->json_secrets == true) {
              $url = Url::fromUri("{$base_path}");
              $link_options_jst = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-check-square-o',
                  ),
                ),
              );
              $url->setOptions($link_options_jst);
              $results[$key]['json_secrets'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_jst))->toString();
          }
          if (isset($info) && $info) {
            //Info
            $url = Url::fromUri("{$base_path}admin/config/modal_form2?table={$r}&iccid={$data->$info_param}");
            $link_options_info = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
            $url->setOptions($link_options_info);
            $results[$key][] = Link::fromTextAndUrl('Info', $url->setOptions($link_options_info))->toString();
            //end Info
            //QR
            if (isset($data->state) && $data->state == 'RELEASED') {
              $url2 = Url::fromUri("{$base_path}admin/config/qr_form?table={$r}&iccid={$data->$info_param}");
              $link_options_release = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options_release);
              $results[$key][] = Link::fromTextAndUrl('QR', $url2->setOptions($link_options_release))->toString();
            }//end QR
            //RELEASED
            if (isset($data->state) && $data->state == 'DELETED') {
              $url2 = Url::fromUri("{$base_path}admin/config/release_form?table={$r}&iccid={$data->$info_param}");
              $link_options_deleted = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options_deleted);
              $results[$key][] = Link::fromTextAndUrl('Release', $url2->setOptions($link_options_deleted))->toString();
            } //end RELEASED
          }
          //
        } else {
          //crud(Edit, Delete)
          $export[] = (array) $data;

          //only partner id=0
          if ((isset($r) && $r == 'partner') && ($data->$primary_key == 0)) {
            $edit = Url::fromUserInput('/mydata/form/mydata2', [
                'query' => [
                    'num' => '00',
                    'table' => $r,
                ],
            ]);

            $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key, [
                'query' => [
                    'table' => $r,
                ],
            ]);
          } elseif (isset($r) && $r == 'input_element') {
            //added one more parameter if input_element table
            $edit = Url::fromUserInput('/mydata/form/mydata2', [
                'query' => [
                    'num' => $data->$primary_key,
                    'num1' => $data->$composite_key,
                    'table' => $r,
                ],
            ]);

            $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key, [
                'query' => [
                    'num1' => $data->$composite_key,
                    'input_element' => $data->input_element,
                    'table' => $r,
                ],
            ]);
          } elseif (isset($r) && $r == 'input_file_detail') {
            //added one more parameter if input_file_detail table
            $edit = Url::fromUserInput('/mydata/form/mydata2', [
                'query' => [
                    'num' => $data->$primary_key,
                    'num1' => $data->$composite_key,
                    'table' => $r,
                ],
            ]);

            $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key, [
                'query' => [
                    'mnoid' => $data->mnoid,
                    'profile_type' => $data->profile_type,
                    'table' => $r,
                ],
            ]);
          } else {
            $edit = Url::fromUserInput('/mydata/form/mydata2', [
                'query' => [
                    'num' => $data->$primary_key,
                    'table' => $r,
                ],
            ]);

            $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key, [
                'query' => [
                    'table' => $r,
                ],
            ]);
          }
          //Added because parent_partner_name need to add which is not in partner table
          if (isset($r) && $r == 'partner') {
            $results[] = array(
              'partner_id'=>$data->partner_id,
              'partner_name'=>$data->partner_name,
              'op'=>$data->op,
              'k4'=>$data->k4,
              'json_secrets'=>$data->json_secrets,
              'parent_partner_id'=>$data->parent_partner_id,
              'parent_partner_name'=>'',
              'grand_parent_partner_id'=>$data->grand_parent_partner_id,
              'grand_parent_partner_name'=>''
            );
          } elseif (isset($r) && $r == 'imsi_range' &&
               (
                    in_array("esim_admin", $roles) ||
                    in_array("usim_admin", $roles)
                )
            ) {
            $query = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->partner_id);

            $result = $query->execute();
            $output = $result->fetchAll();

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
          } elseif (isset($r) && $r == 'imsi_range') {
            $query_ir = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->partner_id);

            $result_ir = $query_ir->execute();
            $output_ir = $result_ir->fetchAll();
            $results[] = [
                          'imsi_range_id' => $data->imsi_range_id,
                          'partner_id' => $data->partner_id,
                          'partner name' => $output_ir[0]->partner_name,
                          'esim_usim' => $data->esim_usim,
                          'imsi_range_name' => $data->imsi_range_name,
                          'mcc' => $data->mcc,
                          'mnc' => $data->mnc,
                          'imsi_start' => $data->imsi_start,
                          'imsi_count' => $data->imsi_count,
                          'status' => $data->status,
                          'ibn' => $data->ibn,
                          'date_created' => $data->date_created,
                          'notes' => $data->notes
                        ];
          } elseif (isset($r) && $r == 'esim_profile_type') {
            $query = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->partner_id);
            $result = $query->execute();
            $output = $result->fetchAll();
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

          //if in json array Stats then show this
          if (in_array("Stats", $header_table2)) {
            $url = Url::fromUri("{$base_path}admin/config/stats?imsi_range_id={$data->$primary_key}&imsi_range_name={$data->imsi_range_name}");
            $link_options_info_edit = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
            $url->setOptions($link_options_info_edit);
            $results[$key][] = Link::fromTextAndUrl('Stats', $url->setOptions($link_options_info_edit))->toString();
          }
          //if in json array Edit then show this
          if (in_array("Edit", $header_table2)) {
            $results[$key][] = Link::fromTextAndUrl(t('Edit'), $edit)->toString();
          }
          //if in json array Delete then show this
          if (in_array("Delete", $header_table2)) {
            $results[$key][] = Link::fromTextAndUrl('Delete', $delete)->toString();
          }          

          if ((isset($r) && $r == 'partner') && ($data->parent_partner_id)) {
            $query = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->parent_partner_id);
            $result = $query->execute();
            $output = $result->fetchAll();
            $results[$key]['parent_partner_name'] = $output[0]->partner_name;
          }
          //grand_parent_partner_id
          if ((isset($r) && $r == 'partner') && ($data->grand_parent_partner_id)) {
            $query = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->grand_parent_partner_id);
            $result = $query->execute();
            $output = $result->fetchAll();
            $results[$key]['grand_parent_partner_name'] = $output[0]->partner_name;
          }
          //partner op asterisk or eye view
          if (isset($data->op) && $data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?op=1&table={$r}&num={$data->$primary_key}&partner_name={$data->partner_name}");
              $link_options_op = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options_op);
              $results[$key]['op'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_op))->toString();
          }

          //partner k4 asterisk or eye view
          if (isset($data->k4) && $data->k4) {
              $urlk4 = Url::fromUri("{$base_path}admin/config/modal_form_partner?k4=1&table={$r}&num={$data->$primary_key}&partner_name={$data->partner_name}");
              $link_options_k4 = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $urlk4->setOptions($link_options_k4);
              $results[$key]['k4'] = Link::fromTextAndUrl(' ', $urlk4->setOptions($link_options_k4))->toString();
          }
          //json_secret value 1 show checked checbox
          if ((isset($data->json_secrets) && $data->json_secrets == 1) && ($r== 'partner')) {
              $url = Url::fromUri("{$base_path}");
              $link_options_jst_edit = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-check-square',
                  ),
                ),
              );
              $url->setOptions($link_options_jst_edit);
              $results[$key]['json_secrets'] = Link::fromTextAndUrl('', $url->setOptions($link_options_jst_edit))->toString();
          }
          //json_secret value 0 show checbox
          if ((isset($data->json_secrets) && $data->json_secrets == 0)&& ($r== 'partner')) {
              $url = Url::fromUri("{$base_path}");
              $link_options_jsf_edit = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-square',
                  ),
                ),
              );
              $url->setOptions($link_options_jsf_edit);
              $results[$key]['json_secrets'] = Link::fromTextAndUrl('', $url->setOptions($link_options_jsf_edit))->toString();
          }
          //static value 1 become true and false
          if ((isset($data->static) && $data->static == 1) && ($r == 'input_element')) {
          $results[$key]['static'] = 'TRUE';
          }
          if ((isset($data->static) && $data->static == 0) && ($r == 'input_element')) {
          $results[$key]['static'] = 'FALSE';
          }
          if (isset($info) && $info) {
            //Info
            $url = Url::fromUri("{$base_path}admin/config/modal_form2?table={$r}&iccid={$data->$info_param}");
            $link_options_info_edit = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
            $url->setOptions($link_options_info_edit);
            $results[$key][] = Link::fromTextAndUrl('Info', $url->setOptions($link_options_info_edit))->toString();
            //end Info
            //QR
            if (isset($data->state) && $data->state == 'RELEASED') {
              $url2 = Url::fromUri("{$base_path}admin/config/qr_form?table={$r}&iccid={$data->$info_param}");
              $link_options_released_edit = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options_released_edit);
              $results[$key][] = Link::fromTextAndUrl('QR', $url2->setOptions($link_options_released_edit))->toString();
            }//end QR
            //RELEASED
            if (isset($data->state) && $data->state == 'DELETED') {
              $url2 = Url::fromUri("{$base_path}admin/config/release_form?table={$r}&iccid={$data->$info_param}");
              $link_options_deleted_edit = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $url2->setOptions($link_options_deleted_edit);
              $results[$key][] = Link::fromTextAndUrl('Release', $url2->setOptions($link_options_deleted_edit))->toString();
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
        $handle2 = fopen('php://temp', 'w+');
        $remove2 = ["Edit", "Delete", "partner name"];
        $header_table1=array_diff($header_table1, $remove2);
        fputcsv($handle2, $header_table1);
        foreach ($exportallresults as $value2) {
          fputcsv($handle2, array_values($value2));
        }
        rewind($handle2);
        $csv_data2 = stream_get_contents($handle2);
        fclose($handle2);
        $response2 = new Response();
        if ($type == 'xls') {

          $response2->headers->set('Content-Type', 'application/vnd.ms-excel');
          //set filename session in jcius_master.theme
          if ($this->session->get('current_partner_name')) {
            if ($r =='esim_usim_partner_counts') {
              $filename2 = "esim_usim_partner_counts";
            }
            elseif ($r =='esim_profile_type') {
              $filename2 = "esim_profile_type_".date("Ymd");
            } else {
              $filename2 = str_replace(' ', '_', $this->session->get('current_partner_name'));
            }
            $response2->headers->set('Content-Disposition', 'attachment; filename="'.$filename2.'_report_all.'.$type.'"');
          } else {
            $response2->headers->set('Content-Disposition', 'attachment; filename="download-report-all.'.$type.'"');
          }
        } else {
          $response2->headers->set('Content-Type', 'text/csv');
          //set filename session in jcius_master.theme
          if ($this->session->get('current_partner_name')) {
            if ($r =='esim_usim_partner_counts') {
              $filename2 = "esim_usim_partner_counts";
            }
            elseif ($r =='esim_profile_type') {
              $filename2 = "esim_profile_type_".date("Ymd");
            } else {
              $filename2 = str_replace(' ', '_', $this->session->get('current_partner_name'));
            }
            $response2->headers->set('Content-Disposition', 'attachment; filename="'.$filename2.'_report_all.csv"');
          } else {
            $response2->headers->set('Content-Disposition', 'attachment; filename="download-report-all.csv"');
          }
        }
        $response2->setContent($csv_data2);
        return $response2;
      } //csv export all end

      $form = [];
      $form['form'] = [
        '#type'  => 'form',
      ];
      $form['form']['filters'] = [
        '#type'  => 'details',
        '#title' => $this->t('Search / Options'),
        '#open'  => false,
        '#attributes' => [
            'class' => ['fa'],
            'title' => t("Search / Options"),
        ],
      ];
      //fuzzy search only for usim_provision_vw
      if (($r =='usim_provision_vw') || ($r =='esim_provision') || ($r =='provision_history')) {
        $form['form']['filters']['quick_search'] = [
            '#title'         => $this->t('Search Value'),
            '#type'          => 'textfield',
            '#value'         => $quicksearchfield,
            '#attributes' => [
                'class' => ['custom-quicksearch'],
            ],
            '#wrapper_attributes' => [
                'class' => ['custom-search-quicksearch-wrapper'],
                'title' => t("Search Value"),
            ],
          ];
        $form['form']['filters']['quick_submit'] = [
          '#type'  => 'button',
          '#value' => $this->t('Quick Search'),
          '#button_type' => 'primary',
          '#default_value' => '',
          '#attributes' => [
              'class' => ['custom-filter-quicksubmit'],
              'title' => t("Quick Search"),
          ],
        ];
      } //end fuzzy search only for usim_provision_vw

      if ($r !== 'usim_provision_vw') {
        $form['form']['filters']['text'] = [
            '#title'         => $this->t('Search Value'),
            '#type'          => 'search',
            '#required' => TRUE,
            '#value' => $field,
            '#attributes' => [
                'class' => ['custom-search'],
                'title' => t("Enter Search value, select the Column to search and click Search button."),
            ],
            '#wrapper_attributes' => [
                'class' => ['custom-search-wrapper'],
                'title' => t("Search Value")
            ],
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
          '#attributes' => [
              'id' => ['custom-header-colmn'],
              'title' => t("Search Column"),
          ],
        ];
        $form['form']['filters']['actions'] = [
          '#type'       => 'actions'
        ];
        $form['form']['filters']['actions']['submit'] = [
          '#type'  => 'submit',
          '#value' => $this->t('Search'),
          '#button_type' => 'primary',
          '#default_value' => '',
          '#attributes' => [
              'class' => ['custom-filter-btn'],
              'title' => t("Search"),
          ],
        ];
      } //if
      if ($r != 'partner') {
        $form['form']['filters']['actions']['exportselect'] = [
          '#type'          => 'select',
          "#weight" => 2,
          '#empty_value'   => 'none',
          '#empty_option'  => '- None -',
          '#size'          => 0,
          '#options' => [
                          'export_csv' => 'Export CSV',
                          'export_all_csv' => 'Export All CSV',
                          'export_xls' => 'Export Excel',
                          'export_all_xls' => 'Export All Excel',
                        ],
          '#value' => 'export_csv',
          '#attributes' => [
                            'id' => ['export-csv'],
                            'title' => t("Export Line Report"),
                          ],
          '#wrapper_attributes' => [
                            'class' => ['export_csv_wrapper'],
                            'title' => t("Enter the search value. Select a column to search. Press the Search Button"),
                        ],
        ];

        $form['form']['filters']['actions']['exportbutton'] = [
          '#type'  => 'submit',
          "#weight" => 3,
          '#value' => $this->t('Export'),
          '#button_type' => 'primary',
          '#default_value' => '',
          '#attributes' => [
              'class' => ['export-btn'],
              'title' => t("Export currently filtered or all Lines to CSV or Excel"),
          ],
        ];
      } //if
      $form['form']['filters']['actions']['button2'] = [
        '#type'  => 'button',
        '#value' => $this->t('Reset'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#attributes' => [
            'class' => ['custom-reset-btn'],
            'title' => t("Reset search values and display all records."),
        ],
      ];
      if ($r == 'esim_10t_status_vw') {
        $preprod   = Url::fromUserInput('/mydata/oss/tables2?table=esim_10t_status_vw');
        $form['form']['filters']['actions']['preprod'] = [
          '#type' => 'link',
          "#weight" => 1,
          '#url' => $preprod,
          '#attributes' => [
              'target' => '_self',
              'class' => 'prod fa fa-toggle-on',
              'title' => t("Production view"),
          ],
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
        if ((in_array('administrator', $roles)) || (in_array('esim_admin', $roles)) || (in_array('tenant_management', $roles))) {
          $addnewurl   = Url::fromUserInput('/mydata/form/mydata2?table=' . $r);
          $form['form']['filters']['actions']['new'] = [
            '#type' => 'link',
            '#title' => $this->t('Add New'),
            "#weight" => 1,
            '#url' => $addnewurl,
            '#attributes' => [
                'target' => '_self',
                'class' => 'add-new button button--primary js-form-submit form-submit',
                'title' => t("Add New"),
            ],
          ];
        }
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
        if (
            //in_array('esim_cs', $roles) ||
            in_array('esim_admin', $roles) ||
            in_array('administrator', $roles)
        ) {
            array_push($header_column, "INFO", "ACTION");
        }
      } //end esim_10t_status_vw addtional header column
      
      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $results,
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table')),
        '#prefix' => "<h1 class='uppar-case'>".$title."</h1><p class='total-records'>".t('Total number of records found: @count', ['@count' => $total_records])."</p>"
      ];
      // Finally add the pager.
      $form['pager'] = array(
        '#type' => 'pager'
      );
      $form['#cache'] = ['max-age' => 0];
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
    } else { //end if user has role then display else access denied
      throw new AccessDeniedHttpException();
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
