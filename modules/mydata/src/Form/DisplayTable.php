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
use Drupal\Core\Render\Markup;
use Drupal\Core\Access\AccessResult;
use Drupal\encrypt\Entity\EncryptionProfile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * SendToDestinationsForm class.
 */
class DisplayTable extends FormBase {
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
  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;
  /**
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(AccountProxyInterface $current_user, Connection $defaultConnection, Connection $externalConnection, LanguageManagerInterface $languageManager, SessionInterface $session, PagerManagerInterface $pagerManager, RequestStack $request_stack) {
    $this->currentUser = $current_user;
    $this->defaultDatabase = $defaultConnection;
    $this->externalDatabase = $externalConnection;
    $this->languagemanager = $languageManager;
    $this->session = $session;
    $this->pagerManager = $pagerManager;
    $this->request = $request_stack->getCurrentRequest();
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
    $request = $container->get('request_stack');

    // Return the controller object with the injected database connections.
    return new static($current_user, $defaultConnection, $externalConnection, $languageManager, $session,$pagerManager, $request);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'table_form';
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

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {  
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
    //Dashboard value set
    if($this->request->query->get('field') && $this->request->query->get('column')) {
      $this->session->set('esim_field', $this->request->query->get('field'));
      $this->session->set('esim_column', $this->request->query->get('column'));
    }

    $field = $this->session->get('esim_field');
    $column = $this->session->get('esim_column');
    //$quicksearchfield = $this->request->query->get('q');
    $quicksearchfield = $this->session->get('quicksearchtxt');    
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
    if ($current_language_id=='en') {
      $header_alias = $str->$r->alias_en;
      $title = isset($str->$r->title_en) ? $str->$r->title_en : NULL;
    } else {
      $header_alias = $str->$r->alias_ja;
      $title = isset($str->$r->title_ja) ? $str->$r->title_ja : NULL;
    }

    //info access only for esim_cs and esim_admin role
    if ((in_array('esim_admin', $roles)) || (in_array('administrator', $roles))) {
      $info = $str->$r->info_tag;
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
        if (
            strtolower('Edit') == strtolower($htable) ||
            strtolower('Stats') == strtolower($htable) ||
            strtolower('Delete') == strtolower($htable) ||
            strtolower('partner name') == strtolower($htable) ||
            strtolower('parent_partner_name') == strtolower($htable) ||
            strtolower('grand_parent_partner_name') == strtolower($htable) ||
            strtolower('INFO') == strtolower($htable) ||
            strtolower('ACTION') == strtolower($htable)
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
      if ((isset($quicksearchfield) && $quicksearchfield) && ($r=='usim_provision_vw')) {
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
      if ((isset($quicksearchfield) && $quicksearchfield) && ($r=='esim_provision')) {
        $orgroup = $query->orConditionGroup()
                          ->condition('m.esim_file_name', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.iccid', "%".$quicksearchfield."%", 'LIKE')
                          ->condition('m.imsi', "%".$quicksearchfield."%", 'LIKE');

        // Add the group to the query.
        $query->condition($orgroup);
      }
      //Quicksearch for esim_provision end

      //Quicksearch for provision_history
      if ((isset($quicksearchfield) && $quicksearchfield) && ($r=='provision_history')) {
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
        if (isset($column) && $column =='partner name') { //in this table partner_name column not available
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        } else if (isset($column) && $column =='parent_partner_name') {
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.parent_partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        } else if (isset($column) && $column =='grand_parent_partner_name') {
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.grand_parent_partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        } else {
          $query->condition('m.' . $column . '',
             '%' . $field . '%',
            'LIKE'
          );
        }
      }
      //end Query string as per column and field
      /*
      if ($user) {
        //$user_display_name = $user->getDisplayName();
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        //child partner start
        $queryc = $this->externalDatabase->query("select count(*) from partner where parent_partner_id = {$resultuser[0]->sim_partner_id}");
        $resultc = $queryc->fetchAll();
        if($resultc[0]->count){
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
        if($resultc2[0]->count){
          $querypid2 = $this->externalDatabase->query("select partner_id from partner where grand_parent_partner_id={$resultuser[0]->sim_partner_id}");
          $resultpid2 = $querypid2->fetchAll();
          $partner_pid2 = [];
          foreach ($resultpid2 as $keypid2 => $datapid2) {
            $partner_pid2[] = $datapid2->partner_id;
          }
        }
        //grand parent partner end
      }
      */

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
      if ($r == 'esim_10t_status_vw') {
        //if ((!in_array('administrator', $roles)) || ($resultc[0]->count)) {
        if (!in_array('administrator', $roles)) {
          $query->condition('m.partner_id', $all_related_partners, 'IN');
        } else {
          //Admin user no filter/ show all
          //$query->condition('m.partner_id', $resultuser[0]->sim_partner_id, '=');
        }
        //dashboard table partner_name
        if (isset($_GET['partner_name']) && $_GET['partner_name']) {
          $query->condition('m.partner_name', $_GET['partner_name'], '=');
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
      if ($r == 'imsi_range') {
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
      if (($r == 'imsi_range') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }

      //Remove partner name because this is not part of table
      if (($r == 'esim_profile_type') && (($key = array_search('partner name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }


      //Only applicable for  usim_provision_vw table
      if ($r == 'usim_provision_vw') {
        if (!in_array('administrator', $roles)) { //adminstrator will see all
          $query->condition('m.partner_id', $all_related_partners, 'IN');
        }
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

      if (in_array("Edit", $header_table2) || in_array("Delete", $header_table2) || in_array("Stats", $header_table2) || in_array("INFO", $header_table2)|| in_array("ACTION", $header_table2)) {
        $removeOptions = ["Edit", "Delete","Stats", "grand_parent_partner_name","INFO","ACTION"];
        $header_table_removedOptions = array_diff($header_table2, $removeOptions);
        $query->fields('m', $header_table_removedOptions);
      } else {
        $query->fields('m', $header_table2);
      }

      /**
       * Export ALL data to CSV.
       *
       * Important:
       * - No 5000 record limit.
       * - No fetchAll().
       * - No large PHP array.
       * - No large CSV string in memory.
       * - Records are streamed directly to the browser.
       */
      if (($exportalldata == '1') && ($r != 'partner')) {

        // Prevent PHP from terminating the export because of execution time.
        set_time_limit(0);

        // Allow the request to continue even if the browser disconnects.
        ignore_user_abort(TRUE);

        // Keep the export headers separate from the table headers.
        $export_headers = $header_table1;

        // Remove fields which are not actual database columns.
        $remove_export_columns = [
          'Edit',
          'Delete',
          'Stats',
          'partner name',
          'parent_partner_name',
          'grand_parent_partner_name',
          'INFO',
          'ACTION',
        ];

        $export_headers = array_values(
          array_diff($export_headers, $remove_export_columns)
        );

        /*
         * Clone the query so that the normal table query is not affected.
         *
         * IMPORTANT:
         * Do NOT use PagerSelectExtender here.
         * Do NOT use limit(5000).
         */
        $export_query = clone $query;

        /*
         * Apply the same table sorting used by the normal table.
         */
        if ($header_table) {
          $export_query = $export_query
            ->extend('Drupal\Core\Database\Query\TableSortExtender')
            ->orderByHeader($header_table);
        }

        /*
         * Execute the query WITHOUT fetchAll().
         *
         * fetchAssoc() reads one record at a time.
         */
        $export_result = $export_query->execute();

        /*
         * Create a streamed response.
         */
        $response = new StreamedResponse(
          function () use ($export_result, $export_headers) {

            // Open browser output stream.
            $handle = fopen('php://output', 'w');

            if ($handle === FALSE) {
              return;
            }

            /*
             * Write CSV header.
             */
            fputcsv($handle, $export_headers);

            /*
             * Read and write one database record at a time.
             *
             * This is the important part.
             * We do NOT use fetchAll().
             */
            while ($row = $export_result->fetchAssoc()) {

              /*
               * Keep only the columns that are part of the CSV.
               */
              $csv_row = [];

              foreach ($export_headers as $column) {
                $csv_row[] = $row[$column] ?? '';
              }

              /*
               * Write the row immediately.
               */
              fputcsv($handle, $csv_row);

              /*
               * Flush output periodically.
               *
               * This prevents PHP/output buffers from accumulating
               * a huge amount of data.
               */
              if (function_exists('ob_flush')) {
                @ob_flush();
              }

              flush();
            }

            fclose($handle);
          }
        );

        /*
         * CSV headers.
         */
        if ($type == 'xls') {

          $response->headers->set(
            'Content-Type',
            'application/vnd.ms-excel'
          );

          if ($this->session->get('current_partner_name')) {

            if ($r == 'esim_usim_partner_counts') {
              $filename = 'esim_usim_partner_counts';
            }
            elseif ($r == 'esim_profile_type') {
              $filename = 'esim_profile_type_' . date('Ymd');
            }
            else {
              $filename = str_replace(
                ' ',
                '_',
                $this->session->get('current_partner_name')
              );
            }

            $response->headers->set(
              'Content-Disposition',
              'attachment; filename="' . $filename . '_report_all.' . $type . '"'
            );
          }
          else {
            $response->headers->set(
              'Content-Disposition',
              'attachment; filename="download-report-all.' . $type . '"'
            );
          }

        }
        else {

          $response->headers->set(
            'Content-Type',
            'text/csv; charset=UTF-8'
          );

          if ($this->session->get('current_partner_name')) {

            if ($r == 'esim_usim_partner_counts') {
              $filename = 'esim_usim_partner_counts';
            }
            elseif ($r == 'esim_profile_type') {
              $filename = 'esim_profile_type_' . date('Ymd');
            }
            else {
              $filename = str_replace(
                ' ',
                '_',
                $this->session->get('current_partner_name')
              );
            }

            $response->headers->set(
              'Content-Disposition',
              'attachment; filename="' . $filename . '_report_all.csv"'
            );
          }
          else {
            $response->headers->set(
              'Content-Disposition',
              'attachment; filename="download-report-all.csv"'
            );
          }
        }

        /*
         * Tell the browser that this is a download.
         */
        $response->headers->set(
          'Cache-Control',
          'no-cache, no-store, must-revalidate'
        );

        $response->headers->set(
          'Pragma',
          'no-cache'
        );

        $response->headers->set(
          'Expires',
          '0'
        );

        return $response;
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
      /*
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
      */
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
          if ($r == 'esim_provision') {
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
          if ($r == 'provision_history') {
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
          if ($r == 'imsi_range') {
            $url = Url::fromUri("{$base_path}admin/config/stats?imsi_range_id={$data->$primary_key}&imsi_range_name={$data->imsi_range_name}");
            $link_options_info_edit = array(
              'attributes' => array(
                'class' => array(
                  'use-ajax button',
                  'my-second-class',
                ),
              ),
            );
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
              $results[$key]['opc'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_opc))->toString();
          }
          //static value 1 become true and false
          if ((isset($data->static) && $data->static == 1) && ($r== 'input_element')) {
          $results[$key]['static'] = 'TRUE';
          }
          if ((isset($data->static) && $data->static == 0) && ($r== 'input_element')) {
          $results[$key]['static'] = 'FALSE';
          }
          //date format change
          if ((isset($data->created_at) && $data->created_at) && ($r == 'esim_10t_status_vw')) {
            $newdate = date("Y-m-d", strtotime($data->created_at));
            $results[$key]['created_at'] = $newdate;
          }
          //esim comment
          if ($r == 'esim_10t_status_vw') {
              $url = Url::fromUri(
                  "{$base_path}admin/config/comment_form?table={$r}&iccid={$data->$info_param}&imsi={$data->imsi}"
              );

              $link_options_op = [
                  'attributes' => [
                      'class' => [
                          'use-ajax',
                          'button',
                      ],
                  ],
              ];

              if (!empty($data->comment)) {
                  $comment = trim($data->comment);

                  $link_text = mb_strlen($comment) > 50
                      ? mb_substr($comment, 0, 50) . '...'
                      : $comment;
              }
              else {
                  $link_text = '<span class="fa fa-comment"></span>';
              }

              $results[$key]['comment'] = Link::fromTextAndUrl(
                  Markup::create($link_text),
                  $url->setOptions($link_options_op)
              )->toString();
          }

          //usim comment
          if ($r == 'usim_provision_vw') {
              $url = Url::fromUri(
                  "{$base_path}admin/config/usim_comment_form?table={$r}&master_id={$data->master_id}"
              );

              $link_options_op = [
                  'attributes' => [
                      'class' => [
                          'use-ajax',
                          'button',
                      ],
                  ],
              ];

              if (!empty($data->comment)) {
                  $comment = trim($data->comment);

                  $link_text = mb_strlen($comment) > 50
                      ? mb_substr($comment, 0, 50) . '...'
                      : $comment;
              }
              else {
                  $link_text = '<span class="fa fa-comment"></span>';
              }

              $results[$key]['comment'] = Link::fromTextAndUrl(
                  Markup::create($link_text),
                  $url->setOptions($link_options_op)
              )->toString();
          }
          
          //partner op asterisk
          if (isset($data->op) && $data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?op=1&table={$r}&num={$data->$primary_key}");
              $link_options_op = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $results[$key]['op'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_op))->toString();
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
              $results[$key]['json_secrets'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_jst))->toString();
          }
          if ($info=1) { //intentionally removed
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
              $results[$key][] = Link::fromTextAndUrl('QR', $url2->setOptions($link_options_release))->toString();
            }//end QR

            //RESET
            if (isset($data->state) && $data->state == 'DELETED') {
              $url2 = Url::fromUri("{$base_path}admin/config/reset_form?table={$r}&iccid={$data->$info_param}");
              $link_options_deleted = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $results[$key][] = Link::fromTextAndUrl('Reset', $url2->setOptions($link_options_deleted))->toString();
            }
            //end RESET

            //RELEASED
            if (isset($data->state) && $data->state == 'AVAILABLE') {
              $url2 = Url::fromUri("{$base_path}admin/config/release_form?table={$r}&iccid={$data->$info_param}");
              $link_options_deleted = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'my-second-class',
                  ),
                ),
              );
              $results[$key][] = Link::fromTextAndUrl('Release', $url2->setOptions($link_options_deleted))->toString();
            } //end RELEASED
          }
          //
        } else {
          //crud(Edit, Delete)
          $export[] = (array) $data;

          //only partner id=0
          if (($r == 'partner') && ($data->$primary_key == 0)) {
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
          } elseif ($r == 'input_element') {
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
          } elseif ($r == 'input_file_detail') {
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
          if ($r== 'partner') {
            $results[] = array(
              'partner_id'=>$data->partner_id,
              'partner_name'=>$data->partner_name,
              'op'=>$data->op,
              'json_secrets'=>$data->json_secrets,
              'parent_partner_id'=>$data->parent_partner_id,
              'parent_partner_name'=>'',
              'grand_parent_partner_id'=>$data->grand_parent_partner_id,
              'grand_parent_partner_name'=>''
            );
          } elseif ($r == 'imsi_range' &&
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
          }elseif ($r== 'imsi_range') {
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
          } elseif ($r== 'esim_profile_type') {
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

          if (($r == 'partner') && ($data->parent_partner_id)) {
            $query = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->parent_partner_id);
            $result = $query->execute();
            $output = $result->fetchAll();
            $results[$key]['parent_partner_name'] = $output[0]->partner_name;
          }
          //grand_parent_partner_id
          if (($r == 'partner') && ($data->grand_parent_partner_id)) {
            $query = $this->externalDatabase->select('partner', 'p')
                          ->fields('p', ['partner_name'])
                          ->condition('partner_id', $data->grand_parent_partner_id);
            $result = $query->execute();
            $output = $result->fetchAll();
            $results[$key]['grand_parent_partner_name'] = $output[0]->partner_name;
          }
          //partner op asterisk or eye view
          if (isset($data->op) && $data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?op=1&table={$r}&num={$data->$primary_key}");
              $link_options_op = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $results[$key]['op'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options_op))->toString();
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
              $results[$key]['json_secrets'] = Link::fromTextAndUrl('', $url->setOptions($link_options_jsf_edit))->toString();
          }
          //static value 1 become true and false
          if ((isset($data->static) && $data->static == 1) && ($r== 'input_element')) {
          $results[$key]['static'] = 'TRUE';
          }
          if ((isset($data->static) && $data->static == 0) && ($r== 'input_element')) {
          $results[$key]['static'] = 'FALSE';
          }
          if ($info=1) { //intentionally removed
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
      /*
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
      } 
      */
      //csv export all end

      $form['form']['filters'] = [
        '#type'  => 'details',
        '#title' => $this->t('Search / Options'),
        '#open'  => false,
        '#attributes' => array('class' => array('fa'),'title' => t("Enter Search value, select the Column to search and click Search button."))
      ];
      //fuzzy search only for usim_provision_vw
      if (($r =='usim_provision_vw') || ($r =='esim_provision') || ($r =='provision_history')) {
        $form['form']['filters']['quick_search'] = [
          '#title'         => $this->t('Search Value'),
          '#type'          => 'textfield',
          '#attributes' => [
              'class' => ['custom-quicksearch'],
          ],
          '#wrapper_attributes' => [
              'class' => ['custom-search-quicksearch-wrapper'],
              'title' => t("Search Value"),
          ],
        ];

        $form['form']['filters']['quick_submit'] = [
          '#type'  => 'submit',
          '#value' => $this->t('Quick Search'),
          '#submit' => [[$this, 'quicksearchForm']],
          '#button_type' => 'primary',
          '#default_value' => '',
          '#limit_validation_errors' => [],
          //'#attributes' => [
          //   'class' => ['custom-filter-quicksubmit'],
          //    'title' => t("Quick Search"),
          //],
        ];

      } //end fuzzy search only for usim_provision_vw

      if ($r !== 'usim_provision_vw') {
        $form['form']['filters']['text'] = [
          '#title'         => t('Search Value'),
          '#type'          => 'search',
          '#attributes' => array('class' => array('custom-search'))
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
          $headervalues_associate[$value] = t($value);
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
          '#options'       => $headervalues_associate,
          '#default_value' => 'none',
          //'#value' => (isset($default_key)) ? $default_key : '',
          '#attributes' => array('id' => array('custom-header-colmn'),'title' => t("Enter Search value, select the Column to search and click Search button."))
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

      } //if
      if ($r != 'partner') {
        $form['form']['filters']['actions']['exportselect'] = [
          '#type'          => 'select',
          "#weight" => 2,
          '#empty_value'   => 'none',
          '#empty_option'  => '- None -',
          '#size'          => 0,
          '#options' => [
                          'export_csv' => t('Export CSV'),
                          'export_all_csv' => t('Export All CSV'),
                          'export_xls' => t('Export Excel'),
                          'export_all_xls' => t('Export All Excel'),
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
        '#type'  => 'submit',
        '#submit' => [[$this, 'resetForm']],
        '#value' => $this->t('Reset'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#limit_validation_errors' => [], //no validation for back button
        '#attributes' => array('class' => array('custom-reset'),'title' => t("Reset"))
      ];

      if ($r == 'esim_10t_status_vw') {
        //table= preprod_esim_10t_status_vw
        $preprod   = Url::fromUserInput('/mydata/oss/tables2?table=def50200b9de3cb179b6ff1bd40c51c423c401d1194b33b4d8cfcf99048837a98bd7dacb330428acd93a944425ae7b11667f7986e4b29febc998c2cf6b172246a0b968b8f2fe2e38a61ace699206b4d8406914e7c860f3954cc7dd55c74f7f120a93193ba39e1fe1d6cbdf0debc8');
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
          '#attributes' => ['target' => '_self', 'class' => '', 'title' => 'Preprod view'],
        ];
      } //if
      if (($r == 'partner') || ($r == 'imsi_range') || ($r == 'esim_profile_type')) {
        if ((in_array('administrator', $roles)) || (in_array('esim_admin', $roles))) {
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
      $user = \Drupal::currentUser();
      $roles = $user->getRoles();
      if ((in_array('administrator', $roles)) || (in_array('tenant_management', $roles))) {
        if (in_array('administrator', $roles)) {
          //partner
          $query3 = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
          $result3 = $query3->fetchAll();
          $sim_partner_id = [];
          foreach ($result3 as $key3 => $data3) {
            $sim_partner_id[0] = t('--Select--');
            $sim_partner_id[$data3->partner_id] = $data3->partner_name;
          }
        } else { //tenant_management
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

          $resultpart = $query->fetchAll();

          $sim_partner_id = [];
          foreach ($resultpart as $keypart => $datapart) {
            $sim_partner_id['NULL'] = '--Select--';
            $sim_partner_id[$datapart->partner_id] = $datapart->partner_name;
          }
        }
        

        //partner end
        $form['form']['filters']['actions']['partner'] = [
          //'#title'         => 'Column',
          '#type'          => 'select',
          '#size'          => 0,
          '#options'       => $sim_partner_id,
          '#default_value' => 'none',
          '#attributes' => ['style' => 'width: 15em'],
          '#weight' => 17,
          //'#attributes' => array('id' => array('custom-header-colmn'))
        ];

        $form['form']['filters']['actions']['partner_submit'] = array(
          '#type'=>'submit',
          '#submit' => [[$this, 'AssignPartner']],
          '#value' => t('Assign Partner'),
          '#weight' => 18,
          '#attributes' => array('title' => t("Assign Partner"))
        );
        //only for esim_10t_status_vw
        if($r == 'esim_10t_status_vw') {
          $form['form']['filters']['actions']['sync_submit'] = array(
            '#type'=>'submit',
            '#submit' => [[$this, 'syncForm']],
            '#value' => t('Sync'),
            '#weight' => 19,
            '#attributes' => array('title' => t("Sync"))
          );
        }
      }
      //if header alias else keep orignal
      if ($header_alias) {
        $header_column = $header_alias;
      } else {
        $header_column = $header_table;
      } //else
      //esim_10t_status_vw addtional header column
      if ($r=='esim_10t_status_vw_removed') { //intentionally removed
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
      /*
      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $results,
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table')),
        '#prefix' => "<h1 class='uppar-case'>".$title."</h1><p class='total-records'>".t('Total number of records found: @count', ['@count' => $total_records])."</p>"
      ];
      */
      $associative_array = array_combine($header_column, $header_column);
      //remve key value 0 because it is considering in submission
      if ($results) {
        array_unshift($results,"");
        unset($results[0]);
      }

      if ($r == 'esim_10t_status_vw') {
        $custom_column = ['profile_type'=>t('profile_type'),'partner_name'=> t('partner_name'),'state'=> t('state'),'prov_iccid'=> t('prov_iccid'),'imsi'=> t('imsi'),'msisdn'=> t('msisdn'),'comment'=> t('comment'),'created_at'=> t('created_at'),'0'=> t('info'),'1'=> t('action')];
      } elseif ($r == 'usim_provision_vw') {
        $custom_column = ['partner_name'=>t('partner_name'),'master_id'=> t('master id'),'imsi'=> t('imsi'),'msisdn'=> t('msisdn'),'iccid'=> t('iccid'),'comment'=> t('comment'),'provision_date'=> t('provision_date')];
      }
      
      $form['table'] = [
          '#type' => 'tableselect',
          '#header' => $custom_column, //$associative_array
          '#options' => $results,
          '#empty' => t('No data found'),
          '#multiple' => true,
          '#attributes' => array('class' => array('custom-table')),
          '#prefix' => "<h1 class='uppar-case'>".$title."</h1><p class='total-records'>".t('Total number of records found: @count', ['@count' => $total_records])."</p>"
        ];
      // Finally add the pager.
      $form['pager'] = array(
        '#type' => 'pager'
      );
      $form['#cache'] = ['max-age' => 0];
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      //$this->session->remove('esim_field');
      //$this->session->remove('esim_column');
      return $form;      
    } else { //end if user has role then display else access denied
      $form['form'] = [
        '#prefix'  => '<h1>Access Denied</h1>',
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
      ];
      // Attach the library for pop-up dialogs/modals.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      //$this->session->remove('esim_field');
      //$this->session->remove('esim_column');

      //throw exception
      throw new AccessDeniedHttpException();

      return $form;
    } //else access denied
  }

  public function filterForm(array &$form, FormStateInterface $form_state) {
    if ($form['form']['filters']['text']['#value'] && $form['form']['filters']['column']['#value'] != 'none') {
      if ($form['form']['filters']['column']['#value'] == 'partner_id'){
        $this->session->set('esim_field', $form['form']['filters']['partner_id']['#value']);
        $this->session->set('esim_column', $form['form']['filters']['column']['#value']);
      } else {
        $this->session->set('esim_field', $form['form']['filters']['text']['#value']);
        $this->session->set('esim_column', $form['form']['filters']['column']['#value']);
      }
    } else {
      \Drupal::messenger()->addWarning(t('Please enter search value.'));
    }
    $url = Url::fromUri('internal:/mydata/oss/table', [
      'query' => [
        'table' => 'def502003717fbd6e2ba390c10e630f9c4153c4fa7f8ede807bcf623cc44b2264b10348bbf12276cc4dddf36f2552f976e02fe36b31a3de6c93c363da1d13f511c0a51b71e2884d247182bb57a9d977c524077597fa48d3e0bf96e69e057cae544e153caac67',
      ],
    ]);

    // Set the redirect URL //esim_10t_status_vw
    $form_state->setRedirectUrl($url);
  }

  public function quicksearchForm(array &$form, FormStateInterface $form_state) {
    if ($form['form']['filters']['quick_search']['#value']) {
      $this->session->set('quicksearchtxt', $form['form']['filters']['quick_search']['#value']);
    } else {
      \Drupal::messenger()->addWarning(t('Please enter search value.'));
    }
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
    $this->session->remove('esim_field');
    $this->session->remove('esim_column');
    $this->session->remove('quicksearchtxt');
    // Create a valid URL
    $url = Url::fromUri('internal:/mydata/oss/table', [
        'query' => ['table' => 'def502003717fbd6e2ba390c10e630f9c4153c4fa7f8ede807bcf623cc44b2264b10348bbf12276cc4dddf36f2552f976e02fe36b31a3de6c93c363da1d13f511c0a51b71e2884d247182bb57a9d977c524077597fa48d3e0bf96e69e057cae544e153caac67']
    ])->toString();

    // Perform redirect
    $form_state->setRedirectUrl(Url::fromUserInput($url));
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
      $tests = array_filter($form_state->getValue('table'));
        foreach ($tests as $key => $value) {
          if ($value) {
            $query = $this->defaultDatabase->query("update lineview set profile = '".$form['form']['filters']['actions']['hss']['#value']."' where imsi=".$form['table']['#options'][$key]['imsi']." and iccid=".$form['table']['#options'][$key]['iccid']."");
            if ($query) {
              \Drupal::messenger()->addStatus(t('Hss Profile Updated.'));
            }        
          }
        }
    }
  }

  public function AssignPartner(array $form, FormStateInterface $form_state) {
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

    if ($form['form']['filters']['actions']['partner']['#value'] == 0) {
      \Drupal::messenger()->addWarning(t('Partner not selected.'));
    } else { 
      $tests = array_filter($form_state->getValue('table'));
        foreach ($tests as $key => $value) {
          if ($value) {
            if($r == 'esim_10t_status_vw') {
              $query = $this->externalDatabase->query("update esim_provision set partner_id = '".$form['form']['filters']['actions']['partner']['#value']."' where imsi='".$form['table']['#options'][$key]['imsi']."' and iccid='".$form['table']['#options'][$key]['prov_iccid']."'");
            } elseif($r == 'usim_provision_vw') {
              //echo "update provision_history set partner_id = '".$form['form']['filters']['actions']['partner']['#value']."' where imsi='".$form['table']['#options'][$key]['imsi']."' and iccid='".$form['table']['#options'][$key]['prov_iccid']."'";
              //exit;
              $query = $this->externalDatabase->query("update provision_history set partner_id = '".$form['form']['filters']['actions']['partner']['#value']."' where imsi='".$form['table']['#options'][$key]['imsi']."' and iccid='".$form['table']['#options'][$key]['iccid']."'");
            } else {
              //do nothing
            }
            
            if ($query) {
              \Drupal::messenger()->addStatus(t('Partner assigned successfully.'));
            }        
          }
        }
    }
  }

  public function syncForm(array $form, FormStateInterface $form_state) {
    $current_user = \Drupal::currentUser();
    $uid = $current_user->id();
    $config = \Drupal::config('xai.settings');
    $mode = $config->get('mode');
    if ($mode == 'production') {
      //Production configuration Fetched
      $endpoint = $config->get('endpoint');
      $email = $config->get('email');
      $password = $config->get('password');
      $upload_pem = $config->get('upload_pem');
      $upload_crt = $config->get('upload_crt');
      $upload_key = $config->get('upload_key');
    } else {
      //Pre Production configuration Fetched
      $endpoint = $config->get('pre_endpoint');
      $email = $config->get('pre_email');
      $password = $config->get('pre_password');
      $upload_pem = $config->get('pre_upload_pem');
      $upload_crt = $config->get('pre_upload_crt');
      $upload_key = $config->get('pre_upload_key');
    }          

    //pem
    $filepem = \Drupal::entityTypeManager()->getStorage('file')
    ->load($upload_pem[0]);
    $certificate = \Drupal::service('file_system')->realpath($filepem->get('uri')->value);

    //crt
    $filecrt = \Drupal::entityTypeManager()->getStorage('file')
    ->load($upload_crt[0]);
    $cert = \Drupal::service('file_system')->realpath($filecrt->get('uri')->value);

    //key
    $filekey = \Drupal::entityTypeManager()->getStorage('file')
    ->load($upload_key[0]);
    $key = \Drupal::service('file_system')->realpath($filekey->get('uri')->value);

    $curl = curl_init();
    curl_setopt_array($curl, array(
              CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/user/signin',
              CURLOPT_RETURNTRANSFER => true,
              CURLOPT_ENCODING => '',
              CURLOPT_MAXREDIRS => 10,
              CURLOPT_TIMEOUT => 0,
              CURLOPT_FOLLOWLOCATION => true,
              CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
              CURLOPT_CUSTOMREQUEST => 'POST',
              CURLOPT_POSTFIELDS => 'email='.$email.'&password='.$password,
              CURLOPT_CAINFO => $certificate,
              CURLOPT_SSLKEY => $key,
              CURLOPT_SSLCERT => $cert,
              CURLOPT_HTTPHEADER => array(
                'Content-Type: application/x-www-form-urlencoded'
              ),
            ));

    $response = curl_exec($curl);

    //curl_close($curl);
    if (curl_errno($curl)) {
      $error_msg = curl_error($curl);
    }

    if (isset($error_msg)) {
      print_r($error_msg);
    }

    curl_close($curl);

    //sign start
    $path = $endpoint.'/gsma/rsp2/es2plus/user/signin';
    $activity = "SignIn";
    \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values(["-", $path, $activity, $uid, REQUEST_TIME])->execute();
    //signin end

    $resulttoken = json_decode($response);
    $tests = array_filter($form_state->getValue('table'));
    if ($tests) {
      $synched =[];
      foreach ($tests as $keyv => $value) {
        if ($value) {
          $iccid = $form['table']['#options'][$keyv]['prov_iccid'];
          if ($resulttoken->token) {
              if ($iccid) {
                $curl = curl_init();
                curl_setopt_array($curl, array(
                      CURLOPT_URL => $endpoint.'/gsma/rsp2/es2plus/getProfileStatus',
                      CURLOPT_RETURNTRANSFER => true,
                      CURLOPT_ENCODING => '',
                      CURLOPT_MAXREDIRS => 10,
                      CURLOPT_TIMEOUT => 0,
                      CURLOPT_FOLLOWLOCATION => true,
                      CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
                      CURLOPT_CUSTOMREQUEST => 'POST',
                      CURLOPT_CAINFO => $certificate,
                      CURLOPT_SSLKEY => $key,
                      CURLOPT_SSLCERT => $cert,
                      CURLOPT_POSTFIELDS => '{
                    "header": {
                        "functionRequesterIdentifier": "JCIUS",
                        "functionCallIdentifier": "JCIUS"
                    },
                    "iccidList": [{"iccid" :"'.$iccid.'"}]
                    }',
                      CURLOPT_HTTPHEADER => array(
                        "Authorization: Bearer $resulttoken->token",
                        'Content-Type: application/json'
                      ),
                    ));

                $response = curl_exec($curl);
                if (curl_errno($curl)) {
                  $error_msg = curl_error($curl);
                }

                if (isset($error_msg)) {
                  print_r($error_msg);
                }
                curl_close($curl);

                //getProfileStatus start
                $path = $endpoint.'/gsma/rsp2/es2plus/getProfileStatus';
                $activity = "Profile Status";
                \Drupal::database()->insert('10t_usage')->fields(['iccid', 'path', 'activity', 'uid', 'timestamp'])->values([$iccid, $path, $activity, $uid, REQUEST_TIME])->execute();
                //getProfileStatus end

                $data = json_decode($response, true);
                if ($data['header']['functionExecutionStatus']['status']== 'Executed-Success') {
                  // Check current state of the iccid
                  $query_check = $this->externalDatabase->query("SELECT state FROM esim_profiles WHERE iccid = '".$iccid."'");
                  $output = $query_check->fetchAll();
                  if ($output) {
                      $current_state = $output[0]->state;
                      $alreadysynched = 0;
                      // Check if the current state is not already 'RELEASED'
                      if ($current_state != $data['profileStatusList'][0]['state']) {
                          // Execute the update query
                          $query_update = $this->externalDatabase->query("UPDATE esim_profiles SET state = '".$data['profileStatusList'][0]['state']."' WHERE iccid = '".$iccid."'");
                          
                          if ($query_update) {
                              // Update successful
                              \Drupal::messenger()->addStatus(t('ICCID '.$iccid.' state updated to '.$data['profileStatusList'][0]['state']));
                          }
                      } else {
                          // ICCID is already in updated state
                          //\Drupal::messenger()->addWarning(t('ICCID '.$iccid. ' is already in '.$data['profileStatusList'][0]['state']. ' state'));
                          $alreadysynched =1;
                          
                          if($alreadysynched == 1){
                            $synched[]=$iccid;
                          } 
                      }                    

                  }
                }          
              }
          }

        }
      }
      $message = implode(', ', $synched) . ' already synced.';
      \Drupal::messenger()->addWarning($message);
    } else {
      \Drupal::messenger()->addWarning(t('Please select checkbox.'));
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
    return ['config.table_form'];
  }
}