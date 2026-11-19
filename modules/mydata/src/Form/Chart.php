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
class Chart extends FormBase {
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
    return 'chart_form';
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
    $user = \Drupal::currentUser();
    $session = \Drupal::request()->getSession();
    $roles = $user->getRoles();
    $user_display_name = $user->getEmail();
    $queryuser = $this->externalDatabase->query("select sim_partner_id from drupal_user where drupal_user = '".$user_display_name."'");
    $resultuser = $queryuser->fetchAll();

    $query = $this->externalDatabase->select('esim_10t_status_counts_vw', 'e');
    $query->fields('e', ['partner_id', 'partner_name']);
    $query->addExpression('SUM(CASE WHEN state = :allocated THEN count ELSE 0 END)', 'allocated', [':allocated' => 'ALLOCATED']);
    $query->addExpression('SUM(CASE WHEN state = :available THEN count ELSE 0 END)', 'available', [':available' => 'AVAILABLE']);
    $query->addExpression('SUM(CASE WHEN state = :deleted THEN count ELSE 0 END)', 'deleted', [':deleted' => 'DELETED']);
    $query->addExpression('SUM(CASE WHEN state = :enabled THEN count ELSE 0 END)', 'enabled', [':enabled' => 'ENABLED']);
    $query->addExpression('SUM(CASE WHEN state = :error THEN count ELSE 0 END)', 'error', [':error' => 'ERROR']);
    $query->addExpression('SUM(CASE WHEN state = :installed THEN count ELSE 0 END)', 'installed', [':installed' => 'INSTALLED']);
    $query->addExpression('SUM(CASE WHEN state = :released THEN count ELSE 0 END)', 'released', [':released' => 'RELEASED']);
    $query->addExpression('SUM(CASE WHEN state = :total THEN count ELSE 0 END)', 'total', [':total' => 'TOTAL']);
    $query->condition('state', ['ALLOCATED', 'AVAILABLE', 'DELETED', 'ENABLED', 'ERROR', 'INSTALLED', 'RELEASED', 'TOTAL'], 'IN');
    $query->groupBy('partner_id');
    $query->groupBy('partner_name');
    $query->orderBy('partner_name');
    //$query->range(0, 15);

    if (
        in_array('administrator', $roles) ||
        in_array('esim_dashboard_readonly', $roles)
    ) {
      if($session->get('pie_partner_id')){
        $query->condition('partner_id', $session->get('pie_partner_id'));
      }
    }else{
        if ($session->get('pie_partner_id')) {
          // Initialize an array to hold all partner IDs related to partner_id 1
          $all_related_partners = [];
          $this->fetchRelatedPartners($session->get('pie_partner_id'), $all_related_partners);
          $all_related_partners[] = $session->get('pie_partner_id');
          $query->condition('e.partner_id', $all_related_partners, 'IN');
        } else {
          $all_related_partners = [];
          $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
          $all_related_partners[] = $resultuser[0]->sim_partner_id;
          $query->condition('e.partner_id', $all_related_partners, 'IN'); 
        }
                  
    }
    $config = \Drupal::config('xai.settings');
    $base_path = $config->get('domain');
    $esim_line_view = 'def502003717fbd6e2ba390c10e630f9c4153c4fa7f8ede807bcf623cc44b2264b10348bbf12276cc4dddf36f2552f976e02fe36b31a3de6c93c363da1d13f511c0a51b71e2884d247182bb57a9d977c524077597fa48d3e0bf96e69e057cae544e153caac67';
    $link_options_ki = array(
                'attributes' => array(
                  'class' => array(
                    'use-ajax123 button',
                  ),
                ),
              );

    //Added 5aug 2025 because of pagination issue
    $pager = $query->extend('Drupal\Core\Database\Query\PagerSelectExtender')->limit(15);
    $results = $pager->execute()->fetchAll();

    //$results = $query->execute()->fetchAll();

    $piedata = [];   
    foreach ($results as $key => $value) {
        $rows[] = (array) $value;
        $piedata[] = array(
                  'allocated'=>$value->allocated,
                  'available'=>$value->available,
                  'deleted'=>$value->deleted,
                  'enabled'=>$value->enabled,
                  'error'=>$value->error,
                  'installed'=>$value->installed,
                  'released'=>$value->released
                );
        //parner_id
        if($rows[$key]['partner_id']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&field={$value->partner_name}&column=partner_name");
          $rows[$key]['partner_id'] = Link::fromTextAndUrl($value->partner_id, $url->setOptions($link_options_ki))->toString();
        }
        //partner_name
        if($rows[$key]['partner_name']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&field={$value->partner_name}&column=partner_name");          
          $rows[$key]['partner_name'] = Link::fromTextAndUrl($value->partner_name, $url->setOptions($link_options_ki))->toString();
        }
        //allocated
        if($rows[$key]['allocated']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=allocated&column=state");   
          $rows[$key]['allocated'] = Link::fromTextAndUrl($value->allocated, $url->setOptions($link_options_ki))->toString();
        }
        //available
        if($rows[$key]['available']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=available&column=state");   
          $rows[$key]['available'] = Link::fromTextAndUrl($value->available, $url->setOptions($link_options_ki))->toString();
        }
        //deleted
        if($rows[$key]['deleted']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=deleted&column=state");   
          $rows[$key]['deleted'] = Link::fromTextAndUrl($value->deleted, $url->setOptions($link_options_ki))->toString();
        }
        //enabled
        if($rows[$key]['enabled']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=enabled&column=state");   
          $rows[$key]['enabled'] = Link::fromTextAndUrl($value->enabled, $url->setOptions($link_options_ki))->toString();
        }
        //error
        if($rows[$key]['error']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=error&column=state");   
          $rows[$key]['error'] = Link::fromTextAndUrl($value->error, $url->setOptions($link_options_ki))->toString();
        }
        //installed
        if($rows[$key]['installed']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=installed&column=state");   
          $rows[$key]['installed'] = Link::fromTextAndUrl($value->installed, $url->setOptions($link_options_ki))->toString();
        }
        //released
        if($rows[$key]['released']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}&field=released&column=state");   
          $rows[$key]['released'] = Link::fromTextAndUrl($value->released, $url->setOptions($link_options_ki))->toString();
        }
        //total
        if($rows[$key]['total']){
          $url = Url::fromUri("{$base_path}mydata/oss/table?table={$esim_line_view}&partner_name={$value->partner_name}");   
          $rows[$key]['total'] = Link::fromTextAndUrl($value->total, $url->setOptions($link_options_ki))->toString();
        }
    }

    $header_column = ["Partner ID"=> "Partner ID","Partner Name"=> "Partner Name", "ALLOCATED"=>"ALLOCATED", "AVAILABLE"=>"AVAILABLE", "DELETED"=>"DELETED","ENABLED"=>"ENABLED", "ERROR"=>"ERROR", "INSTALLED"=>"INSTALLED", "RELEASED"=>"RELEASED","TOTAL"=>"TOTAL"];

      // Initialize sum variables
      $sumAllocated = 0;
      $sumAvailable = 0;
      $sumDeleted = 0;
      $sumEnabled = 0;
      $sumError = 0;
      $sumInstalled = 0;
      $sumReleased = 0;

      // Loop through the array and calculate sums
      foreach ($piedata as $item) {
          $sumAllocated += $item['allocated'];
          $sumAvailable += $item['available'];
          $sumDeleted += $item['deleted'];
          $sumEnabled += $item['enabled'];
          $sumError += $item['error'];
          $sumInstalled += $item['installed'];
          $sumReleased += $item['released'];
      }

      $flatArray = [$sumAllocated, $sumAvailable, $sumDeleted, $sumEnabled, $sumError, $sumInstalled, $sumReleased];

    if (
        in_array('administrator', $roles) ||
        in_array('esim_dashboard_readonly', $roles)
    ) {
      $queryp = $this->externalDatabase->query("select partner_id, partner_name from partner order by partner_name");
      $resultp = $queryp->fetchAll();
      $partner_id = [];
      foreach ($resultp as $keyp => $datap) {
        $partner_id[0] = "--SELECT--";
        $partner_id[$datap->partner_id] = $datap->partner_name;
      }

      $form['partner_id'] = [
        '#type' => 'select',
        '#title' => t('Partner Name'),
        '#options' => $partner_id,
        '#default_value' => ($session->get('pie_partner_id')) ? $session->get('pie_partner_id') : '',
        '#required' => true,
        '#cache' => array('max-age' => 0)
      ];

      $form['pie_submit'] = [
        '#type' => 'submit',
        '#value' => t('Generate Pie Chart'),
        '#name' => 'save'
      ];

      $form['reset'] = [
        '#type' => 'submit',
        '#submit' => [[$this, 'resetForm']],
        '#value' => t('Reset'),
        '#limit_validation_errors' => [], //no validation for back button
      ];
    }

    if ($resultuser[0]->sim_partner_id) {
      $queryc = $this->externalDatabase->query("select count(*) from partner where parent_partner_id = {$resultuser[0]->sim_partner_id}");
      $resultc = $queryc->fetchAll();
      if (
          ($resultc[0]->count) &&
          !in_array('administrator', $roles) &&
          !in_array('esim_dashboard_readonly', $roles)
      ) {
        //child partner start + grand child
        //$querypid = $this->externalDatabase->query("select partner_id, partner_name from partner where parent_partner_id={$resultuser[0]->sim_partner_id} or grand_parent_partner_id={$resultuser[0]->sim_partner_id}");
        $all_related_partners = [];
        $this->fetchRelatedPartners($resultuser[0]->sim_partner_id, $all_related_partners);
        $all_related_partners[] = $resultuser[0]->sim_partner_id;
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
        $partner_pid[0] = "--SELECT--";
        //$partner_pid[$session->get('current_partner_id')] = $session->get('current_partner_name');
        $partner_pid[$datapid->partner_id] = $datapid->partner_name;
        }

        $form['partner_id'] = [
          '#type' => 'select',
          '#title' => t('Partner Name'),
          '#options' => $partner_pid,
          '#default_value' => ($session->get('pie_partner_id')) ? $session->get('pie_partner_id') : '',
          '#required' => true,
          '#cache' => array('max-age' => 0)
        ];

        $form['pie_submit'] = [
          '#type' => 'submit',
          '#value' => t('Generate Pie Chart'),
          '#name' => 'save'
        ];

        $form['reset'] = [
          '#type' => 'submit',
          '#submit' => [[$this, 'resetForm']],
          '#value' => t('Reset'),
          '#limit_validation_errors' => [], //no validation for back button
        ];
        //child partner end
      }
    }

    $form['export_csv'] = [
      '#type' => 'submit',
      '#value' => t('Export CSV'),
      '#submit' => [[$this, 'exportCsv']],
      '#limit_validation_errors' => [],
      '#attributes' => [
        'class' => ['button', 'button--primary'],
      ],
    ];

    $form['chart_table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $rows,
        '#name' => 'esim_line_view_chart',
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        '#prefix' => "<a href='/mydata/oss/table?table=def502003717fbd6e2ba390c10e630f9c4153c4fa7f8ede807bcf623cc44b2264b10348bbf12276cc4dddf36f2552f976e02fe36b31a3de6c93c363da1d13f511c0a51b71e2884d247182bb57a9d977c524077597fa48d3e0bf96e69e057cae544e153caac67'><h1 class='uppar-case'>".t('ESIM Line View Summary')."</h1></a>",
        '#attributes' => array('class' => array('custom-table'))
      ];
    $form['pager'] = array(
        '#type' => 'pager'
      );
    $form['#cache'] = ['max-age' => 0];
    $form['#attached']['library'][] = 'mydata/mydata_libraries';
    $form['#attached']['drupalSettings']['mydata']['piechartdata'] = $flatArray;
    $this->session->set('pie_partner_id','');
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
    //$session = \Drupal::request()->getSession();
    $this->session->set('pie_partner_id',$field['partner_id']); 
    $this->session->set('export_partner_id', $field['partner_id']);   
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
    //$session = \Drupal::request()->getSession();
    $this->session->set('pie_partner_id','');
    $this->session->set('export_partner_id','');
  }

  /**
 * Export ESIM summary data as CSV.
 */
  public function exportCsv(array &$form, FormStateInterface $form_state) {

    $roles = $this->currentUser->getRoles();

    // IMPORTANT:
    // Get the partner selected when "Generate Pie Chart" was clicked.
    $partnerId = $this->session->get('export_partner_id');

    $query = $this->externalDatabase->select(
      'esim_10t_status_counts_vw',
      'e'
    );

    $query->fields('e', [
      'partner_id',
      'partner_name',
    ]);

    $query->addExpression(
      'SUM(CASE WHEN state = :allocated THEN count ELSE 0 END)',
      'allocated',
      [':allocated' => 'ALLOCATED']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :available THEN count ELSE 0 END)',
      'available',
      [':available' => 'AVAILABLE']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :deleted THEN count ELSE 0 END)',
      'deleted',
      [':deleted' => 'DELETED']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :enabled THEN count ELSE 0 END)',
      'enabled',
      [':enabled' => 'ENABLED']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :error THEN count ELSE 0 END)',
      'error',
      [':error' => 'ERROR']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :installed THEN count ELSE 0 END)',
      'installed',
      [':installed' => 'INSTALLED']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :released THEN count ELSE 0 END)',
      'released',
      [':released' => 'RELEASED']
    );

    $query->addExpression(
      'SUM(CASE WHEN state = :total THEN count ELSE 0 END)',
      'total',
      [':total' => 'TOTAL']
    );

    $query->condition(
      'state',
      [
        'ALLOCATED',
        'AVAILABLE',
        'DELETED',
        'ENABLED',
        'ERROR',
        'INSTALLED',
        'RELEASED',
        'TOTAL',
      ],
      'IN'
    );

    $query->groupBy('partner_id');
    $query->groupBy('partner_name');
    $query->orderBy('partner_name');

    /*
     * Apply EXACTLY the same partner filtering
     * used by buildForm().
     */
    if (
      in_array('administrator', $roles) ||
      in_array('esim_dashboard_readonly', $roles)
    ) {

      if (!empty($partnerId)) {
        $query->condition(
          'partner_id',
          $partnerId
        );
      }

    }
    else {

      if (!empty($partnerId)) {

        $all_related_partners = [];

        $this->fetchRelatedPartners(
          $partnerId,
          $all_related_partners
        );

        $all_related_partners[] = $partnerId;

        $query->condition(
          'e.partner_id',
          $all_related_partners,
          'IN'
        );

      }
      else {

        // No partner selected.
        // Use logged-in user's partner.
        $userDisplayName = $this->currentUser->getEmail();

        $queryUser = $this->externalDatabase->query(
          'SELECT sim_partner_id
           FROM drupal_user
           WHERE drupal_user = :drupal_user',
          [
            ':drupal_user' => $userDisplayName,
          ]
        );

        $resultUser = $queryUser->fetchAll();

        if (!empty($resultUser[0]->sim_partner_id)) {

          $all_related_partners = [];

          $this->fetchRelatedPartners(
            $resultUser[0]->sim_partner_id,
            $all_related_partners
          );

          $all_related_partners[] =
            $resultUser[0]->sim_partner_id;

          $query->condition(
            'e.partner_id',
            $all_related_partners,
            'IN'
          );
        }
      }
    }

    // Do NOT apply pager here.
    $results = $query->execute()->fetchAll();

    // Create CSV.
    $handle = fopen('php://temp', 'r+');

    fputcsv($handle, [
      'Partner ID',
      'Partner Name',
      'ALLOCATED',
      'AVAILABLE',
      'DELETED',
      'ENABLED',
      'ERROR',
      'INSTALLED',
      'RELEASED',
      'TOTAL',
    ]);

    foreach ($results as $row) {
      fputcsv($handle, [
        $row->partner_id,
        $row->partner_name,
        $row->allocated ?? 0,
        $row->available ?? 0,
        $row->deleted ?? 0,
        $row->enabled ?? 0,
        $row->error ?? 0,
        $row->installed ?? 0,
        $row->released ?? 0,
        $row->total ?? 0,
      ]);
    }

    rewind($handle);

    $csv = stream_get_contents($handle);

    fclose($handle);

    $response = new Response($csv);

    $response->headers->set(
      'Content-Type',
      'text/csv; charset=UTF-8'
    );

    $response->headers->set(
      'Content-Disposition',
      'attachment; filename="esim_line_summary_' .
      date('Y-m-d_H-i-s') .
      '.csv"'
    );

    $response->headers->set(
      'Cache-Control',
      'no-cache, no-store, must-revalidate'
    );

    $form_state->setResponse($response);
    $this->session->set('export_partner_id','');
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.chart_form'];
  }
}