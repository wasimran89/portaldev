<?php
//esim third setting used
namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Database\Database;
use Drupal\Core\Url;
use Drupal\Core\Link;
use Symfony\Component\HttpFoundation\Response;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * Class InputFileController.
 *
 * @package Drupal\mydata\Controller
 */
class InputFileController extends ControllerBase {
  protected $currentUser;
  protected $languageManager;

  /**
   * The current session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected SessionInterface $session;
  
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
   * @param \Drupal\Core\Database\Connection $defaultConnection
   *   The default database connection.
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(AccountProxyInterface $current_user, Connection $defaultConnection, Connection $externalConnection, LanguageManagerInterface $languageManager, SessionInterface $session, RequestStack $request_stack) {
    $this->currentUser = $current_user;
    $this->defaultDatabase = $defaultConnection;
    $this->externalDatabase = $externalConnection;
    $this->languageManager = $languageManager;
    $this->session = $session;
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
    $request = $container->get('request_stack');

    // Return the controller object with the injected database connections.
    return new static($current_user, $defaultConnection, $externalConnection, $languageManager, $session, $request);
  }  /**
   * Display.
   *
   * @return string
   *   Return Hello string.
   */
  public function display() {
    $r = 'input_file';
    $field = $this->request->query->get('field');
    $column = $this->request->query->get('column');
    $exportdata = $this->request->query->get('export');
    $exportalldata = $this->request->query->get('exportall');
    $type = $this->request->query->get('type');
    $config = \Drupal::config('xai.settings');
    $str = json_decode($config->get('bio'));
    $base_path = $config->get('domain');
    //$user = \Drupal::currentUser();
    $user = $this->currentUser->getAccount();
    $roles = $user->getRoles();    
    $data = [];
    $header_table1 = $str->$r->table_listing_fields;
    $header_table2 = $str->$r->table_listing_fields;
    $tablerole = $str->$r->access_role;
    $info = isset($str->$r->info_tag) ? $str->$r->info_tag : 0;
    $primary_key = $str->$r->primary_key;
    $info_param = isset($str->$r->info_param) ? $str->$r->info_param : 0;
    $results = [];
    $export = []; 
    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();

    if ($current_language_id=='en') {
      $header_alias = $str->$r->alias_en;
      $title = $str->$r->title_en;
    } else{
      $header_alias = $str->$r->alias_ja;
      $title = $str->$r->title_ja;
    }

    
    if (array_intersect($roles, $tablerole)) {
      //if user has role then display
      foreach ($header_table1 as $table) {
        if (strtolower('Edit') == strtolower($table) || strtolower('Delete') == strtolower($table) || strtolower('partner_name') == strtolower($table)) {
          $header_table[] = $table;
        } else {
          $dataTmp = array(
            'data' => $table,
            'field' => $table,
            'sort' => 'asc'
          );
          $header_table[] = $dataTmp;
        }
      }
      
      $query = $this->externalDatabase->select($r, 'm');

      if (($field) || ($column)) {
        if($column=='partner_name'){ //in this table partner_name column not available
          $queryp = $this->externalDatabase->query("select partner_id from partner where partner_name like '%".$field."%'");
          $outputp = $queryp->fetchAll();
          $query->condition('m.partner_id',
            $outputp[0]->partner_id,
            'LIKE'
          );
        }else
        {
          $query->condition('m.' . $column . '',
            $field,
            'LIKE'
          );
        }       
      }
      //remove partner_name
      if (($r == 'input_file') && (($key = array_search('partner_name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }
      //remove Input Elements
      if (($r == 'Input_Elements') && (($key = array_search('partner_name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }
      //remove ESIM File Detail
      if (($r == 'ESIM_File_Detail') && (($key = array_search('partner_name', $header_table2)) !== false)) {
        unset($header_table2[$key]);
      }
      //remove edit option while fetching data form database
      if (in_array("Edit", $header_table2)) {
        $values = array_splice($header_table2,
          0,
          -3 //removing "Input_Elements","ESIM_File_Detail", "Edit"
        );
        $query->fields('m', $values);
      } else {
        $query->fields('m', $header_table2);
      }
      //for all cloumn
      if ($header_table) {
        $table_sort = $query->extend('Drupal\Core\Database\Query\TableSortExtender')
        ->orderByHeader($header_table);
        $pager = $table_sort->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      } else {
        $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
      }
      $result = $pager->execute()->fetchAll();

      //export all data in csv
      $exportallresults = [];
      if($exportalldata == 1){ //querystring 1 then execute
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
          else{
            $exportallresults[] = (array) $data;
          }
        }
      }
      //export all data in csv end 

      //$results = $query->execute()->fetchAll();
      \Drupal\Core\Database\Database::setActiveConnection();
      $rows = array();

      foreach ($result as $key => $data) {
        if (!in_array("Edit", $header_table)) {
          //for view no crud
          $results[] = (array) $data;
          $export[] = (array) $data;
          //partner op asterisk
          if ($data->op) {
              $url = Url::fromUri("{$base_path}admin/config/modal_form_partner?table={$r}&num={$data->$primary_key}");
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
          if ($info) {
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
          }
        } else {
          //crud
          $export[] = (array) $data;
          $edit   = Url::fromUserInput('/mydata/form/mydata2?num=' . $data->$primary_key . '&table=' . $r);
          if(1){
            $query = $this->externalDatabase->query("select partner_name from partner where partner_id= ".$data->partner_id."");
            $output = $query->fetchAll();
                $results[] = array(
                  'input_file_id' =>$data->input_file_id,
                  'partner_id' =>$data->partner_id,  
                  'partner_name' => $output[0]->partner_name,              
                  'input_file_name' =>$data->input_file_name,
                  'date_created' =>$data->date_created,
                  'rpad_offset' =>$data->rpad_offset,
                  'input_file_type' =>$data->input_file_type,
                  'environment' =>$data->environment,
                );            
          }          
          
          //input elements
          if ($data->input_file_id) {
              $url = Url::fromUri("{$base_path}admin/config/modal_input_file?table=input_element&num={$data->input_file_id}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['Input_Elements'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }

          //ESIM_File_Detail
          if ($data->input_file_type =='ESIM') {
              //$results[$key]['op'] = "********************************";
              //
              //$base_path = "https://devportal.jcius.com/";
              $url = Url::fromUri("{$base_path}admin/config/modal_esim_file_detail?table=input_file_detail}&num={$data->input_file_id}");
              $link_options = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax button',
                    'fa fa-eye',
                  ),
                ),
              );
              $url->setOptions($link_options);
              $results[$key]['ESIM_File_Detail'] = Link::fromTextAndUrl(' ', $url->setOptions($link_options))->toString();
          }else{
              $results[$key]['ESIM_File_Detail'] = "";
          }

          //edit
          $results[$key][] = Link::fromTextAndUrl('Edit', $edit)->toString();
          //info
          if ($info) {
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
          }
          //
        }
      }
      //csv export
      if ($exportdata == '1') {
        $handle = fopen('php://temp', 'w+');

        $remove=array("Edit", "Delete","partner_name");
        $header_table1=array_diff($header_table1,$remove);
        fputcsv($handle, $header_table1);
        foreach ($export as $value) {
          fputcsv($handle, array_values($value));
        }

        rewind($handle);
        $csv_data = stream_get_contents($handle);
        fclose($handle);
        $response = new Response();
        if($type == 'xls'){
          $response->headers->set('Content-Type', 'application/vnd.ms-excel');
          //set filename session in jcius_master.theme        
          if($this->session->get('current_partner_name')){
            $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report.'.$type.'"');
          }else{
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report.'.$type.'"');
          }  
        }else{
          $response->headers->set('Content-Type', 'text/csv');
          //set filename session in jcius_master.theme        
          if($this->session->get('current_partner_name')){
            $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report.csv"');
          }else{
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report.csv"');
          }  
        } 

        $response->setContent($csv_data);

        return $response;
      }
      //csv export end

      //csv export all
      if ($exportalldata == '1') {
        $handle = fopen('php://temp', 'w+');

        $remove=array("Edit", "Delete","partner_name");
        $header_table1=array_diff($header_table1,$remove);

        fputcsv($handle, $header_table1);
        foreach ($exportallresults as $value) {
        fputcsv($handle, array_values($value));
        }

        rewind($handle);
        $csv_data = stream_get_contents($handle);
        fclose($handle);
        $response = new Response();
        
        if($type == 'xls'){
          $response->headers->set('Content-Type', 'application/vnd.ms-excel');
          //set filename session in jcius_master.theme        
          if($this->session->get('current_partner_name')){
            $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report_all.'.$type.'"');
          }else{
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report-all.'.$type.'"');
          }  
        }else{
          $response->headers->set('Content-Type', 'text/csv');
          //set filename session in jcius_master.theme        
          if($this->session->get('current_partner_name')){
            $filename = str_replace(' ', '_', $this->session->get('current_partner_name'));
            $response->headers->set('Content-Disposition', 'attachment; filename="'.$filename.'_report_all.csv"');
          }else{
            $response->headers->set('Content-Disposition', 'attachment; filename="download-report-all.csv"');
          }  
        } 

        $response->setContent($csv_data);

        return $response;
      }
      //csv export all end
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

      $form['form']['filters']['text'] = [
          '#title'         => t('Search Value'),
          '#type'          => 'search',
          '#required' => TRUE,
          '#attributes' => array('class' => array('custom-search'),'title' => t("Search Value")),
          '#wrapper_attributes' => ['class' => ['custom-search-wrapper'],'title' => t("Search Value")],
        ];

      // Attach the library for pop-up dialogs/modals.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

      //remove edit option from header_table1
      $headernew = $header_table1;
      if (in_array("Edit", $headernew)) {
        //removing "Input_Elements","ESIM_File_Detail", "Edit"
        $headervalues = array_splice($headernew, 0, -3);
      } else {
        $headervalues =  $headernew;
      }
      //query column key value
      foreach ($headervalues as $key => $value) {
        if($value == $column){
          $default_key =$key;
        }
      }
      $form['form']['filters']['column'] = [
        '#title'         => t('Search Column'),
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
      $form['form']['filters']['actions']['exportselect'] = [
        //'#title'         => 'Export',
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

      $form['form']['filters']['actions']['button2'] = [
        '#type'  => 'button',
        '#value' => $this->t('Reset'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#attributes' => array('class' => array('custom-reset-btn'),'title' => t("Reset search values and display all records."))
      ];


      $addnewurl   = Url::fromUserInput('/mydata/form/mydata2?table=' . $r);

      $form['form']['filters']['actions']['new'] = [
        '#type' => 'link',
        '#title' => $this->t('Add New'),
        "#weight" => 1,
        '#url' => $addnewurl,
        '#attributes' => ['target' => '_self', 'class' => 'add-new button button--primary js-form-submit form-submit','title' => t("Add New")],
      ];

      //if header alias else keep orignal
      if ($header_alias) {
        $header_column = $header_alias;
      } else {
        $header_column = $header_table;
      }
      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $results,
        '#empty' => t('No users found'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table')),
        '#prefix' => "<h1 class='uppar-case'>Input File</h1>"
      ];

      // Finally add the pager.
      $form['pager'] = array(
        '#type' => 'pager'
      );
      $form['#cache'] = ['max-age' => 0];
      // echo '<pre>';print_r($form['table']);exit;
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
      //if user has role then display end
    }
    else{
      //access denied if user dont have specified role
      $element = array(
        '#markup' => '<p><b>Access Denied!!!</b></p>',
      );
      return $element;
    }

  }//display function
} //DisplayTableController