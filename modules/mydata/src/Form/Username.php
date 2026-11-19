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
class Username extends FormBase {
  protected $currentUser;
  protected $languageManager;
  /**
   * The default database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $defaultDatabase;

  /**
   * Constructs a new YourController object.
   *
   * @param \Drupal\Core\Database\Connection $defaultConnection
   *   The default database connection.
   */
  /**
   * The pager manager.
   *
   * @var \Drupal\Core\Pager\PagerManagerInterface
   */
  protected $pagerManager;

  public function __construct(AccountProxyInterface $current_user, Connection $defaultConnection, LanguageManagerInterface $languageManager, SessionInterface $session, PagerManagerInterface $pagerManager) {
    $this->currentUser = $current_user;
    $this->defaultDatabase = $defaultConnection;
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
    $languageManager = $container->get('language_manager');
    $session = $container->get('session');
    $pagerManager = $container->get('pager.manager');

    // Return the controller object with the injected database connections.
    return new static($current_user, $defaultConnection, $languageManager, $session,$pagerManager);
  }
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'username_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {
    $user = \Drupal::currentUser();
    $session = \Drupal::request()->getSession();
    $roles = $user->getRoles();
    $user_display_name = $user->getEmail();
    if($this->session->get('masquerading')) {
        $form['username'] = [
          '#type' => 'item',
          '#markup' => t("<a href='#'>Masqueraded as '@username'</a>", ['@username' => $user_display_name]),
        ];
    } else {
        $form['username'] = [
          '#type' => 'item',
          '#markup' => t("<a href='#'>@username</a>", ['@username' => $user_display_name]),
        ];
    }
    
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  //   
  }

  public function resetForm(array &$form, FormStateInterface $form_state) {
  //
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