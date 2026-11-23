<?php
//esim third setting used
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
 * Class DisplayPreprodTableController2.
 *
 * @package Drupal\mydata\Controller
 */
class DisplayPreprodTableController2 extends ControllerBase {
  protected $currentUser;
  protected $languageManager;

  /**
   * The current session.
   *
   * @var \Symfony\Component\HttpFoundation\Session\SessionInterface
   */
  protected $session;
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
    $decrypted = '';
    $table = $this->request->query->get('table');

    if(strlen($table) > 50 )
    {
      $encryption_profile = EncryptionProfile::load('url');
      $decrypted = \Drupal::service('encryption')->decrypt($table, $encryption_profile);
      $r = $decrypted;
    }

    if($decrypted == ''){
     $r = $table;
    }
    
    $field = $this->request->query->get('field');
    $column = $this->request->query->get('column');
    $exportdata = $this->request->query->get('export');
    $exportalldata = $this->request->query->get('exportall');
    $type = $this->request->query->get('type');
    $config = \Drupal::config('xai.settings');
    $str = json_decode($config->get('bio'));
    $base_path = $config->get('domain');    
    $header_table1 = $str->$r->table_listing_fields;
    $header_table2 = $str->$r->table_listing_fields;
    $header_alias = $str->$r->alias;
    $user = $this->currentUser->getAccount();
    $roles = $user->getRoles();
    $tablerole = $str->$r->access_role;
    $primary_key = $str->$r->primary_key;
    $composite_key = $str->$r->composite_key;
    $info_param = $str->$r->info_param;
    $results = [];
    $export = [];      
    $data = [];
    $current_language_id = $this->languageManager->getCurrentLanguage()->getId();
    //$language = \Drupal::languageManager()->getCurrentLanguage()->getId();
    if ($current_language_id=='en') {
      $header_alias = $str->$r->alias_en;
      $title = $str->$r->title_en;
    } else {
      $header_alias = $str->$r->alias_ja;
      $title = $str->$r->title_ja;
    }
    
    //show info tag only esim_cs, esim_admin and administrator
    if ((in_array('esim_cs', $roles)) || (in_array('esim_admin', $roles)) || (in_array('administrator', $roles))) {
      $info = $str->$r->info_tag;
    } else {
      $info = false;
    }

    if (array_intersect($roles, $tablerole)) {
      //if user has role then display
      foreach ($header_table1 as $table) {
        if (strtolower('Edit') == strtolower($table) || strtolower('Delete') == strtolower($table)) {
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

      //Default sort order for the Input Element form first by input_file_id then by position
      if($r =='input_element'){
        $query->orderBy('input_file_id');
        $query->orderBy('position');
      }

      if (($field) || ($column)) {
        $query->condition('m.' . $column . '',
          $field,
          'LIKE'
        );
      }

      //Only applicable for esim_10t_status_view table
      if($r == 'esim_10t_status_vw'){
        $user_display_name = $user->getDisplayName();
        $user_display_name = $user->getEmail();
        $queryuser = $this->externalDatabase->query("select sim_partner_id_preprod from drupal_user where drupal_user = '".$user_display_name."'");
        $resultuser = $queryuser->fetchAll();
        $query->condition('m.partner_id',$resultuser[0]->sim_partner_id_preprod, '=');
      }

      //Remove edit option while fetching data form database
      if((in_array("Edit", $header_table2)) && (in_array("Delete", $header_table2))) {
        $values = array_splice($header_table2,
          0,
          -2
        );
        $query->fields('m', $values);
      }else if (in_array("Edit", $header_table2)) {
        $values = array_splice($header_table2,
          0,
          -1
        );
        $query->fields('m', $values);
      }else if (in_array("Delete", $header_table2)) {
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
      if($exportalldata == 1) { //querystring 1 then execute
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
      //Export all data in CSV end 
      $rows = array();

      foreach ($result as $key => $data) {
        if ((!in_array("Edit", $header_table)) && (!in_array("Delete", $header_table))) {
          //for view no crud
          $results[] = (array) $data;
          $export[] = (array) $data;

          //static value 1 become true and false
          if(($data->static == 1) && ($r== 'input_element')){
          $results[$key]['static'] = 'TRUE';
          }
          if(($data->static == 0) && ($r== 'input_element')){
          $results[$key]['static'] = 'FALSE';
          }
          //date format change
          if(($data->created_at) && ($r== 'esim_10t_status_vw')){ 
          $newDate = date("Y-m-d", strtotime($data->created_at)); 
          $results[$key]['created_at'] = $newDate;
          }

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
            $url = Url::fromUri("{$base_path}admin/config/preprod_modal_form2?table={$r}&iccid={$data->$info_param}");
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
            //For QR
            if($data->state == 'RELEASED'){
              $url2 = Url::fromUri("{$base_path}admin/config/preprod_qr_form?table={$r}&iccid={$data->$info_param}");
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
            }
            //For RELEASED
            if($data->state == 'DELETED'){
              $url2 = Url::fromUri("{$base_path}admin/config/preprod_release_form?table={$r}&iccid={$data->$info_param}");
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
            }
          }

        } else {
          //crud
          $export[] = (array) $data;
          if(($r == 'partner') && ($data->$primary_key == 0)){
            //only partner id=0 
            $edit   = Url::fromUserInput('/mydata/form/mydata2?num=00&table=' . $r);
            $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key . '?table=' . $r);
          }else if($r == 'input_element'){ //added one more parameter if input_element table
            //only partner id=0 
            $edit   = Url::fromUserInput('/mydata/form/mydata2?num='.$data->$primary_key.'&num1='.$data->$composite_key.'&table=' . $r);
            $delete = Url::fromUserInput('/mydata/form/delete2/'.$data->$primary_key.'?&num1='.$data->$composite_key.'&table=' . $r);
          }else{
          $edit   = Url::fromUserInput('/mydata/form/mydata2?num=' . $data->$primary_key . '&table=' . $r);
          $delete = Url::fromUserInput('/mydata/form/delete2/' . $data->$primary_key . '?table=' . $r);
          }
          
          $results[] = (array) $data;

          //if in json array Edit then show this
          if (in_array("Edit", $header_table2))
          {
            $results[$key][] = Link::fromTextAndUrl('Edit', $edit)->toString();
          }
          //if in json array Delete then show this
          if (in_array("Delete", $header_table2))
          {
            $results[$key][] = Link::fromTextAndUrl('Delete', $delete)->toString();
          }         
          
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

          //static value 1 become true and false
          if(($data->static == 1) && ($r== 'input_element')){
          $results[$key]['static'] = 'TRUE';
          }
          if(($data->static == 0) && ($r== 'input_element')){
          $results[$key]['static'] = 'FALSE';
          }

          if ($info) {
            $url = Url::fromUri("{$base_path}admin/config/preprod_modal_form2?table={$r}&iccid={$data->$info_param}");
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

            //For QR
            if($data->state == 'RELEASED'){
              $url2 = Url::fromUri("{$base_path}admin/config/preprod_qr_form?table={$r}&iccid={$data->$info_param}");
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
            }

            //For RELEASED
            if($data->state == 'DELETED'){
              $url2 = Url::fromUri("{$base_path}admin/config/preprod_release_form?table={$r}&iccid={$data->$info_param}");
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
            }
          }
        }
      }

      //csv export
      if (($exportdata == '1') && ($r !='partner')) {
        $handle = fopen('php://temp', 'w+');
        if ((!in_array("Edit", $header_table1)) && (!in_array("Delete", $header_table1))) {
          fputcsv($handle, $header_table1);
          foreach ($results as $value) {
            fputcsv($handle, array_values($value));
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
        //$session = \Drupal::request()->getSession();

        if($type=='xls'){
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
      } //csv export end

      //csv export all
      if (($exportalldata == '1') && ($r !='partner')) {
        $handle = fopen('php://temp', 'w+');

        $remove=array("Edit", "Delete","partner name");
        $header_table1=array_diff($header_table1,$remove);

        fputcsv($handle, $header_table1);
        foreach ($exportallresults as $value) {
        fputcsv($handle, array_values($value));
        }

        rewind($handle);
        $csv_data = stream_get_contents($handle);
        fclose($handle);
        $response = new Response();
        
        if($type=='xls'){
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
      } //csv export all end

      $form = [];

      $form['form'] = [
        '#type'  => 'form',
      ];

      $form['form']['filters'] = [
        '#type'  => 'details',
        '#title' => $this->t('Search / Options'),
        '#open'  => false,
        '#attributes' => array('class' => array('fa'),'title' => t("Enter Search value, select the Column to search and click Search button."))
      ];

      $form['form']['filters']['text'] = [
          '#title'         => 'Search Value',
          '#type'          => 'search',
          '#required' => TRUE,
          '#value' => $field,
          '#attributes' => array('class' => array('custom-search'),'title' => t("Enter Search value, select the Column to search and click Search button."))
        ];

      // Attach the library for pop-up dialogs/modals.
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

      //remove edit option from header_table1
      $headernew = $header_table1;
      if (in_array("Edit", $headernew)) {
        $headervalues = array_splice($headernew, 0, -2);
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
        '#title'         => 'Search Column',
        '#type'          => 'select',
        '#empty_value'   => 'none',
        '#empty_option'  => '- None -',
        '#size'          => 0,
        '#options'       => $headervalues,
        '#default_value' => 'none',
        '#value' => (isset($default_key)) ? $default_key : '',
        '#attributes' => array('id' => array('custom-header-colmn'),'title' => t("Enter Search value, select the Column to search and click Search button."))
      ];

      $form['form']['filters']['actions'] = [
        '#type'       => 'actions'
      ];

      $form['form']['filters']['actions']['submit'] = [
        '#type'  => 'submit',
        '#value' => $this->t('Filter'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#attributes' => array('class' => array('custom-filter-btn'),'title' => t("Enter Search value, select the Column to search and click Search button."))
      ];

      if($r != 'partner')
      {
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
      }

      $form['form']['filters']['actions']['button2'] = [
        '#type'  => 'button',
        '#value' => $this->t('Reset'),
        '#button_type' => 'primary',
        '#default_value' => '',
        '#attributes' => array('class' => array('custom-reset-btn'),'title' => t("Reset search values and display all records."))
      ];

      $preprod   = Url::fromUserInput('/mydata/oss/table?table=def502003717fbd6e2ba390c10e630f9c4153c4fa7f8ede807bcf623cc44b2264b10348bbf12276cc4dddf36f2552f976e02fe36b31a3de6c93c363da1d13f511c0a51b71e2884d247182bb57a9d977c524077597fa48d3e0bf96e69e057cae544e153caac67');
      $form['form']['filters']['actions']['preprod'] = [
      '#type' => 'link',
      //'#title' => $this->t('Preprod'),
      "#weight" => 1,
      '#url' => $preprod,
      '#attributes' => ['target' => '_self', 'class' => 'preprod fa fa-toggle-on'],
      ];

      $form['form']['filters']['actions']['preprodbtn'] = [
      '#type' => 'link',
      '#title' => $this->t(' Preprod'),
      "#weight" => 1,
      '#url' => $preprod,
      '#attributes' => ['target' => '_self', 'class' => '','title' => t("Pre Production view")],
      ];

      if($r == 'partner'){
        $addnewurl   = Url::fromUserInput('/mydata/form/mydata2?table=' . $r);

        $form['form']['filters']['actions']['new'] = [
          '#type' => 'link',
          '#title' => $this->t('Add New'),
          "#weight" => 1,
          '#url' => $addnewurl,
          '#attributes' => ['target' => '_self', 'class' => 'add-new button button--primary js-form-submit form-submit','title' => t("Add New Input File.")],
        ];
      }      

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
      // echo '<pre>';print_r($form['table']);exit;
      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
      //if user has role then display end
    }
    else{
      $form['form'] = [
        '#prefix'  => '<h1>Access Denied</h1>',
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        ];
      return $form;
    }
  }//display function
} //DisplayTableController
