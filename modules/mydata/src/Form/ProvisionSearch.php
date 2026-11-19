<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
/**
 * Class MydataForm.
 *
 * @package Drupal\mydata\Form
 */
class ProvisionSearch extends FormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
  */
  protected $externalDatabase;
  protected $session;
  /**
   *
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   * @param \Symfony\Component\HttpFoundation\Session\SessionInterface $session
   *   The session
  */
  public function __construct(Connection $externalConnection, SessionInterface $session) {
    $this->externalDatabase = $externalConnection;
    $this->session = $session;
  }
  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');
    $session = $container->get('session');

    // Return the controller object with the injected database connections.
    return new static($externalConnection, $session);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'provision_search_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['#prefix'] = '<h1>Provision Search</h1><div id="modal_example_form">';
    $form['#suffix'] = '</div>';
    $query = $this->externalDatabase->query("select item_identifier_key from item_identifier_key order by 1");
    $result = $query->fetchAll();
    $item_identifier_key = [];
    foreach ($result as $key => $data) {
      $item_identifier_key[$data->item_identifier_key] = $data->item_identifier_key;
    }

    $form['search_key'] = array(
      '#title' => t('Search Key'),
      '#type' => 'select',
      '#required' => TRUE,
      //'#description' => 'Select sample description here.',
      '#options' => $item_identifier_key,
    );

    $form['search_value'] = array(
      '#type' => 'textfield',
      '#title' => t('Search Value'),
      '#default_value' => '',
      '#cache' => array('max-age' => 0),
      '#required' => true
    );  

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => t('Search'),
      '#attributes' => [
        'class' => [
          'custom-provision-search',
        ],
      ]
    ];
    // Get the data:
    $search_key = $this->session->get('search_key');
    $search_value = $this->session->get('search_value');
    if($search_key)
    {
      try{
        $query = $this->externalDatabase->query("select * from id_provision_status('".$search_key."','".$search_value."')");
        $result = $query->fetchAll();
      } catch (\Exception $e) {
        $this->session->set('search_key', '');
        $this->session->set('search_value', '');
        \Drupal::messenger()->addError($e);
      }
      $rows = array();
      foreach ($result as $row => $content) {
        $rows[] = array(
          'data' => array($content->id_provision_status));
      }

      $header_column = ['provision status'];
      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_column,
        '#rows' => $rows,
        '#empty' => t('Key ACC Value test does not exist in item_identifier, proceeding.'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table'))
        ];

        // Finally add the pager.
        $form['pager'] = array(
        '#type' => 'pager'
        );
        $form['#cache'] = ['max-age' => 0];
        $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
        $this->session->set('search_key', '');
    }

    return $form;
  }


  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $field = $form_state->getValues();
    $this->session->set('search_key', $field['search_key']);
    $this->session->set('search_value', $field['search_value']);

    // Rebuild the form.
    $form_state->setRebuild(TRUE);
   }
}