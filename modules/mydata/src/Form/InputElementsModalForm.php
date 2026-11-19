<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * SendToDestinationsForm class.
 */
class InputElementsModalForm extends FormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;
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
   * Constructor for MyForm.
   *
   * @param \Drupal\Core\Pager\PagerManagerInterface $pager_manager
   *   The pager manager service.
   */
  public function __construct(Connection $externalConnection, PagerManagerInterface $pagerManager, RequestStack $request_stack) {
    $this->externalDatabase = $externalConnection;
    $this->pagerManager = $pagerManager;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');
    $pagerManager = $container->get('pager.manager');
    $request = $container->get('request_stack');
    return new static($externalConnection, $pagerManager,$request);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'input_elements_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    // Get the 'num' parameter from the request.
    $num = $this->request->query->get('num');

    // Perform the database query.
    $query = $this->externalDatabase->select('input_element', 'ie')
                                    ->fields('ie',['input_file_id', 'input_element', 'position', 'static', 'static_value','date_created'])
                                    ->condition('input_file_id', $num)
                                    ->orderBy('position');

    // Fetch all results as objects.
    $result = $query->execute()->fetchAll();

    $rows = [];
    foreach ($result as $key => $value) {       
    $rows[] = (array) $value;
    }
    $rowPiece = $this->pagerArray($rows, 50);
    $header_table = [];
    $header_column = ['input_file_id', 'input_element', 'position', 'static', 'static_value','date_created'];

    foreach ($header_column as $table) {
        $dataTmp = array(          
          'data' => $table,
          //'field' => $table,
          //removed sorting intentionally ajax issue
          //'sort' => 'asc'
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
    return ['config.input_elements_modal_form'];
  }
}