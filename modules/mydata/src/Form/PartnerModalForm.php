<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * SendToDestinationsForm class.
 */
class PartnerModalForm extends FormBase {
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
   *
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
  public function __construct(Connection $externalConnection, RequestStack $request_stack) {
    $this->externalDatabase = $externalConnection;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');
    $request = $container->get('request_stack');

    // Return the controller object with the injected database connections.
    return new static($externalConnection, $request);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'partner_op_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $record = array();
    //Encryption technique logic
    $decrypted = '';
    if(strlen($this->request->query->get('table')) > 50 )
    {
      $encryption_profile = EncryptionProfile::load('url');
      $decrypted = \Drupal::service('encryption')->decrypt($this->request->query->get('table'), $encryption_profile);
      $r = $decrypted;
    }

    if($decrypted == ''){
     $r = $this->request->query->get('table');
    }

    $num = $this->request->query->get('num');
    $ki = $this->request->query->get('ki');
    $opc = $this->request->query->get('opc');
    $op = $this->request->query->get('op');
    $k4 = $this->request->query->get('k4');

    $config = \Drupal::config('xai.settings');
    $str = json_decode($config->get('bio'));
    if($ki == 1){
      $header_table = ['ki'];
    }
    elseif($opc == 1){
      $header_table = ['opc'];
    }
    elseif($op == 1){
      $header_table = ['op'];
    }
    elseif($k4 == 1){
      $header_table = ['k4'];
    }
    else{
      $header_table = $str->$r->table_listing_fields;
    }

    $primary_key = $str->$r->primary_key;

    if (isset($num)) {
      $query = $this->externalDatabase->select($r, 'm')
                                    ->condition($primary_key, $num)
                                    ->fields('m');
      $record = $query->execute()->fetchAssoc();
    }    

      foreach ($header_table as $column) {
        if (($column != 'Edit') && ($column != 'Delete')) {
          $form[$column] = array(
            '#type' => 'textfield',
            '#title' => t($column),
            '#disabled' => true,
            '#default_value' => (isset($record[$column]) && $num) ? $record[$column] : '',
            '#cache' => array('max-age' => 0)
          );
        } //if
      } //for
    return $form;
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
    return ['config.partner_op_modal_form'];
  }
}