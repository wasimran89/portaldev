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
use Drupal\encrypt\Entity\EncryptionProfile;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Pager\PagerManagerInterface;
use Drupal\Core\Database\Connection;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\RequestStack;
/**
 * SendToDestinationsForm class.
 */
class StatsModalForm extends FormBase {
  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externaldatabase;
  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   * @param \Drupal\Core\Database\Connection $externalconnection
   *   The external database connection.
   */
  protected $pagermanager;
  /**
   * The current request object.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  protected $session;

  public function __construct(
    Connection $externalconnection, 
    PagerManagerInterface $pagermanager, 
    SessionInterface $session,
    RequestStack $request_stack
  ) {
    $this->externaldatabase = $externalconnection;
    $this->pagermanager = $pagermanager;
    $this->session = $session;
    $this->request = $request_stack->getCurrentRequest();
  }

  public static function create(ContainerInterface $container) {
    $externalconnection = $container->get('mydata.external_database');
    $pagermanager = $container->get('pager.manager');
    $session = $container->get('session');
    $request = $container->get('request_stack');
    return new static($externalconnection, $pagermanager, $session, $request);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'stats_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $decrypted = '';
    $imsi_range_id = $this->request->query->get('imsi_range_id');
    $header_alias = ['esim_usim', 'start', 'end', 'count', 'available', 'used'];
    $user = \Drupal::currentUser();
    $roles = $user->getRoles();
    $tablerole = ['authenticated'];
    if (array_intersect($roles, $tablerole)) {
      //if user has role then display
      $results = [];
      $query = $this->externaldatabase->query("SELECT esim_usim, imsi_start, imsi_end, imsi_range_count, imsi_available, imsi_used FROM imsi_range_stats($imsi_range_id)");
      $result = $query->fetchAll();
      foreach ($result as $key => $data) {
        $results[] = (array) $data;
      }

      $form['table'] = [
        '#type' => 'table',
        '#header' => $header_alias,
        '#rows' => $results,
        '#empty' => t('No data found'),
        '#cache' => array('max-age' => 0),
        '#attributes' => array('class' => array('custom-table'))
      ];

      // Finally add the pager.
      $form['pager'] = array(
        '#type' => 'pager'
      );
      $form['#cache'] = ['max-age' => 0];

      $form['#attached']['library'][] = 'core/drupal.dialog.ajax';
      return $form;
      //if user has role then display end
    } else {
      //access denied if user dont have specified role
      $element = array(
        '#markup' => '<p><b>Access Denied!!!</b></p>',
      );
      return $element;
    }
  }//display function

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
    return ['config.stats_modal_form'];
  }
}
