<?php

namespace Drupal\last_visited_pages\Form;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Configures the Quantity of Last Visited Pages displayed for Last Visited Pages Block.
 */
class LastVisitedPagesSettingsForm extends ConfigFormBase {

  /**
   * Constructs a LastVisitedPagesSettingsForm object.
   *
   * @param \Drupal\Core\Config\ConfigFactoryInterface $config_factory
   *   The factory for configuration objects.
   */
  public function __construct(ConfigFactoryInterface $config_factory) {
      parent::__construct($config_factory);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'last_visited_pages_settings';
  }

  /**
  * {@inheritdoc}
  */
  protected function getEditableConfigNames() {
      return ['last_visited_pages.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['max_places'] = [
      '#type' => 'select',
      '#title' => $this->t('Maximum number of places to display'),
      '#options' => array_combine(range(1,50), range(1,50)),
      '#default_value' => $this->config('last_visited_pages.settings')->get('max_places'),
      '#description' => $this->t('Maximum Links allowed in Last Visited Pages Block'),
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
    $this->config('last_visited_pages.settings')
      ->set('max_places', $form_state->getValue('max_places'))
      ->save();
    parent::submitForm($form, $form_state);
  }
}
