<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * SendToDestinationsForm class.
 */
class Simlineviewmodalform extends FormBase {
  /**
   * The default database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $defaultDatabase;
  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   *
   * @param \Drupal\Core\Database\Connection $defaultConnection
   *   The default database connection.
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   */
  public function __construct(Connection $defaultConnection, RequestStack $request_stack) {
    $this->defaultDatabase = $defaultConnection;
    $this->request = $request_stack->getCurrentRequest();
  }

  /**
   * {@inheritdoc}
   */
  public static function create(\Symfony\Component\DependencyInjection\ContainerInterface $container) {
    // Create instances of the database connections.
    $defaultConnection = $container->get('database');
    $request = $container->get('request_stack');

    // Return the controller object with the injected database connections.
    return new static($defaultConnection, $request);
  } 
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'simlineview_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $response = new AjaxResponse();

    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      try {
        //\Drupal\Core\Database\Database::setActiveConnection();
        //$conn = \Drupal\Core\Database\Database::getConnection('default', 'default'); 
        if($_GET['issuspend']== 0 ){
          $query = $this->defaultDatabase->query("update lineview set issuspend= 1  where imsi=".$_GET['imsi']." and iccid=".$_GET['iccid']."");
        } 
        else{
          $query = $this->defaultDatabase->query("update lineview set issuspend= 0  where imsi=".$_GET['imsi']." and iccid=".$_GET['iccid']."");        }  
        
        //$resultuser = $query->fetchAll(); 
        if($query){
          //\Drupal::messenger()->addStatus(t('Successful Suspended.'));
        }
        $response->addCommand(new OpenModalDialogCommand("Success!", 'Form has been updated succesfully', ['width' => 700]));
        //$response->addCommand(new ReplaceCommand('#modal_example_form', $form));
      } catch (\Exception $e) {
        print_r($e);
      }
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
    return ['config.simlineview_modal_form'];
  }
}
