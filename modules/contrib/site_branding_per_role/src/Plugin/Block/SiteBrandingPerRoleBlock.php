<?php

namespace Drupal\site_branding_per_role\Plugin\Block;

use Drupal\Core\Block\BlockBase;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Url;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Path\PathValidatorInterface;

/**
 * Provides a block to display 'Site branding per role' elements.
 *
 * @Block(
 *   id = "site_branding_per_role_block",
 *   admin_label = @Translation("Site branding per role block"),
 * )
 */
class SiteBrandingPerRoleBlock extends BlockBase implements ContainerFactoryPluginInterface {

  /**
   * Stores the configuration factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected $configFactory;

  /**
   * Drupal\Core\Session\AccountInterface definition.
   *
   * @var \Drupal\Core\Session\AccountInterface
   */
  protected $currentUser;

  /**
   * Drupal\Core\Path\PathValidatorInterface definition.
   *
   * @var \Drupal\Core\Path\PathValidatorInterface
   */
  protected $pathValidator;

  /**
   * Creates a SiteBrandingBlock instance.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   * @param \Drupal\Core\Session\AccountInterface $current_user
   *   The AccountProxy objects.
   * @param \Drupal\Core\Path\PathValidatorInterface $path_validator
   *   The PathValidator objects.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, ConfigFactoryInterface $config_factory, AccountInterface $current_user, PathValidatorInterface $path_validator) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->configFactory = $config_factory;
    $this->currentUser = $current_user;
    $this->pathValidator = $path_validator;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('config.factory'),
      $container->get('current_user'),
      $container->get('path.validator')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {
    $path = [];
    foreach (user_role_names() as $user => $name) {
      $path[$user] = '';
    }
    return [
      'access_site_logo' => TRUE,
      'access_site_name' => TRUE,
      'access_site_slogan' => TRUE,
      'site_label_display' => FALSE,
      'logo_link' => $path,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function blockForm($form, FormStateInterface $form_state) {
    // Get the theme.
    $theme = $form_state->get('block_theme');

    // Get permissions.
    $url_system_theme_settings = new Url('system.theme_settings');
    $url_system_theme_settings_theme = new Url('system.theme_settings_theme', ['theme' => $theme]);

    if ($url_system_theme_settings->access() && $url_system_theme_settings_theme->access()) {
      // Provide links to the Appearance Settings and Theme Settings pages
      // if the user has access to administer themes.
      $site_logo_description = $this->t('Defined on the <a href=":appearance">Appearance Settings</a> or <a href=":theme">Theme Settings</a> page.', [
        ':appearance' => $url_system_theme_settings->toString(),
        ':theme' => $url_system_theme_settings_theme->toString(),
      ]);
    }
    else {
      // Explain that the user does not have access to the Appearance and Theme
      // Settings pages.
      $site_logo_description = $this->t('Defined on the Appearance or Theme Settings page. You do not have the appropriate permissions to change the site logo.');
    }
    $url_system_site_information_settings = new Url('system.site_information_settings');
    if ($url_system_site_information_settings->access()) {
      // Get paths to settings pages.
      $site_information_url = $url_system_site_information_settings->toString();

      // Provide link to Site Information page if the user has access to
      // administer site configuration.
      $site_name_description = $this->t('Defined on the <a href=":information">Site Information</a> page.', [':information' => $site_information_url]);
      $site_slogan_description = $this->t('Defined on the <a href=":information">Site Information</a> page.', [':information' => $site_information_url]);
    }
    else {
      // Explain that the user does not have access to the Site Information
      // page.
      $site_name_description = $this->t('Defined on the Site Information page. You do not have the appropriate permissions to change the site logo.');
      $site_slogan_description = $this->t('Defined on the Site Information page. You do not have the appropriate permissions to change the site logo.');
    }

    $form['site_block_branding'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Toggle branding elements'),
      '#description' => $this->t('Choose which branding elements you want to show in this block instance.'),
    ];
    $form['site_block_branding']['access_site_logo'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site logo'),
      '#description' => $site_logo_description,
      '#default_value' => $this->configuration['access_site_logo'],
    ];

    $form['site_block_branding']['access_site_name'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site name'),
      '#description' => $site_name_description,
      '#default_value' => $this->configuration['access_site_name'],
    ];
    $form['site_block_branding']['access_site_slogan'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Site slogan'),
      '#description' => $site_slogan_description,
      '#default_value' => $this->configuration['access_site_slogan'],
    ];

    $form['site_block_branding']['roles'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('All roles'),
    ];

    foreach (user_role_names() as $user => $name) {
      $form['site_block_branding']['roles'][$user] = [
        '#type' => 'textfield',
        '#title' => $name,
        '#size' => 60,
        '#maxlength' => 128,
        '#description' => $this->t('Add a valid url or &ltfront> for main page'),
        '#required' => TRUE,
        '#default_value' => isset($this->configuration['logo_link'][$user]) ? $this->configuration['logo_link'][$user] : '',
      ];
    }

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function blockValidate($form, FormStateInterface $form_state) {

    $block_branding = $form_state->getValue('site_block_branding');
    foreach (user_role_names() as $user => $name) {
      $path = $block_branding['roles'][$user];
      if (!(preg_match('/^[#?\/]+/', $path) || $path == '<front>')) {
        $form_state->setErrorByName($user, $this->t('This URL %url is not valid for role %role.', [
          '%url' => $form_state->getValue($user),
          '%role' => $name,
        ]));
      }
      $is_valid = $this->pathValidator->isValid($path);
      if ($is_valid == NULL) {
        $form_state->setErrorByName($user, $this->t('Path does not exists for role %role.', [
          '%role' => $name,
        ]));
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function blockSubmit($form, FormStateInterface $form_state) {
    $block_branding = $form_state->getValue('site_block_branding');
    $this->configuration['access_site_logo'] = $block_branding['access_site_logo'] ? TRUE : FALSE;
    $this->configuration['access_site_name'] = $block_branding['access_site_name'] ? TRUE : FALSE;
    $this->configuration['access_site_slogan'] = $block_branding['access_site_slogan'] ? TRUE : FALSE;
    foreach (user_role_names() as $user => $name) {
      $this->configuration['logo_link'][$user] = $block_branding['roles'][$user];
    }
  }

  /**
   * {@inheritdoc}
   */
  public function build() {
    $build = [];
    $site_config = $this->configFactory->get('system.site');
    $logo_link = $this->getCurrentRoleLink();
    if ($logo_link == '<front>') {
      $logo_link = '/';
    }
    $base_path = rtrim(base_path(), "/");

    $build['site_logo'] = [
      '#theme' => 'image',
      '#uri' => theme_get_setting('logo.url'),
      '#alt' => $this->t('Home'),
      '#access' => $this->configuration['access_site_logo'],
    ];

    $build['site_name'] = [
      '#markup' => $site_config->get('name'),
      '#access' => $this->configuration['access_site_name'],
    ];

    $build['site_slogan'] = [
      '#markup' => $site_config->get('slogan'),
      '#access' => $this->configuration['access_site_slogan'],
    ];
    return [
      '#theme' => 'site_branding_per_role_block',
      '#build' => $build,
      '#link' => $base_path . $logo_link,
    ];

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheTags() {
    return Cache::mergeTags(
      parent::getCacheTags(),
      $this->configFactory->get('system.site')->getCacheTags()
    );
  }

  /**
   * Get current user brand path.
   */
  public function getCurrentRoleLink() {
    $roles = $this->currentUser->getRoles();
    $link = '<front>';
    if (in_array('administrator', $roles)) {
      $link = $this->configuration['logo_link']['administrator'];
    }
    elseif (in_array('anonymous', $roles)) {
      $link = $this->configuration['logo_link']['anonymous'];
    }
    elseif (in_array('authenticated', $roles) && count($roles) > 1) {
      $key = array_search('authenticated', $roles);
      if (FALSE !== $key) {
        unset($roles[$key]);
      }
      $current_user_role = reset($roles);
      $link = $this->configuration['logo_link'][$current_user_role];
    }
    elseif (in_array('authenticated', $roles)) {
      $link = $this->configuration['logo_link']['authenticated'];
    }
    return $link;
  }

}
