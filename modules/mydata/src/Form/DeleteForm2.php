<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Url;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Symfony\Component\DependencyInjection\ContainerInterface;
/**
 * Class DeleteForm.
 *
 * @package Drupal\mydata\Form
 */
class DeleteForm2 extends ConfirmFormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;
  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
  public function __construct(Connection $externalConnection) {
    $this->externalDatabase = $externalConnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');

    // Return the controller object with the injected database connections.
    return new static($externalConnection);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'delete_form';
  }

  public $cid;

  public function getQuestion() {
    return t('Do you want to delete %cid?', array('%cid' => $this->cid));
  }

  public function getCancelUrl() {
    //return new Url('mydata.display_table_controller_display2');
  }

  public function getDescription() {
      if($_GET['table']=='input_file_detail'){
        return t('Delete Input File Detail '.$this->id.' '.$_GET
          ['mnoid'].' '.$_GET['profile_type'].'?');
      }
      else if($_GET['table']=='input_element'){
        return t('Delete Input Element '.$_GET['input_element'].' for Input File ID '.$this->id.'?');
      }
      else{
        return t('Only do this if you are sure!');
      }      
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText() {
    return t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText() {
    return t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $cid = NULL) {
    $this->id = $cid;

    $form['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#weight' => 1002,
      '#attributes' => [
      'onClick' => 'javascript:window.history.go(-1); return false;'
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
    * {@inheritdoc}
    */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    //Encryption technique logic
    $decrypted = '';
    if(strlen($_GET['table']) > 50 )
    {
      $encryption_profile = EncryptionProfile::load('url');
      $decrypted = \Drupal::service('encryption')->decrypt($_GET['table'], $encryption_profile);
      $r = $decrypted;
    }

    if($decrypted == ''){
     $r = $_GET['table'];
    }

    $config = \Drupal::config('xai.settings');
    $str = json_decode($config->get('bio'));
    $header_table = $str->$r->table_listing_fields;
    $primary_key = $str->$r->primary_key;
    $composite_key = $str->$r->composite_key;
    if($r == 'input_element'){
      $query->delete($r)
            ->condition($primary_key,$this->id)
            ->condition($composite_key,$_GET['num1'])
            ->execute();
    }else
    {
      $query->delete($r)
            ->condition($primary_key,$this->id)
            ->execute();
    }

    \Drupal::messenger()->addMessage($this->t('Succesfully Deleted'));
    $response = Url::fromUserInput('/mydata/oss/table2?table='.$r);
    $form_state->setRedirectUrl($response);
  }
}