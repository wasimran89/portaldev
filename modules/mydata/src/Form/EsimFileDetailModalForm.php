<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Database;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * SendToDestinationsForm class.
 */
class EsimFileDetailModalForm extends FormBase {
  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

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
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $defaultConnection
   *   The default database connection.
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
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
    return new static($current_user, $defaultConnection, $externalConnection, $languageManager, $session, $pagerManager);
  }


  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'esim_file_detail_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $query = $this->externalDatabase->query("select * from input_file_detail where input_file_id = ".$_GET['num']."");
    $result = $query->fetchAll();

    $rows = [];
    foreach ($result as $key => $value) {       
    $rows[] = (array) $value;
    }
    $rowPiece = $this->pagerArray($rows, 50);
    $header_table = [];
    $header_column = ['input_file_id', 'mnoid', 'profile_type', 'po_no', 'order_date','sim_qty','sim_type','operation','mcc_mnc','initial_state_of_profile'];

    foreach ($header_column as $table) {
        $dataTmp = array(
          'data' => $table,
          'field' => $table,
          'sort' => 'asc'
        );
        $header_table[] = $dataTmp;
    }

    $form['table'] = [
      '#type' => 'table',
      '#header' => $header_table,
      '#rows' => $rowPiece,
      '#empty' => t('No accounting records'),
      '#cache' => array('max-age' => 0),
      '#attributes' => array('class' => array('custom-table'))
    ];

    $form['pager'] = [
      '#type' => 'pager',
    ];
    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

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
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }

  /**
   * Gets the configuration names that will be editable.
   *
   * @return array
   *   An array of configuration object names that are editable if called in
   *   conjunction with the trait's config() method.
   */
  protected function getEditableConfigNames() {
    return ['config.esim_file_detail_modal_form'];
  }
}