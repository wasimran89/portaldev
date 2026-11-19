<?php

namespace Drupal\jcius_custom\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Line API secret keys configuration form.
 */
class JciusCustomApiConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jcius_custom.simslineviewapiconfigform',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'jcius_api_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('jcius_custom.simslineviewapiconfigform');
    $form['admin_email'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Admin Email'),
      '#description' => $this->t('The administrator email to perform login on the API'),
      '#default_value' => $config->get('admin_email'),
    ];
    $form['password'] = [
      '#type' => 'password',
      '#title' => $this->t('Admin Password'),
      '#description' => $this->t('API administrator password'),
      '#default_value' => $config->get('password'),
    ];
    $form['client_id'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client ID'),
      '#description' => $this->t('The client id'),
      '#default_value' => $config->get('client_id'),
    ];
    $form['client_secret'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Client Secret'),
      '#description' => $this->t('The client secret'),
      '#default_value' => $config->get('client_secret'),
    ];
    $form['api_url'] = [
      '#type' => 'textfield',
      '#title' => $this->t('API Url'),
      '#description' => $this->t('The API URL'),
      '#default_value' => $config->get('api_url'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('jcius_custom.simslineviewapiconfigform')
      ->set('admin_email', $form_state->getValue('admin_email'))
      ->set('password', $form_state->getValue('password'))
      ->set('client_id', $form_state->getValue('client_id'))
      ->set('client_secret', $form_state->getValue('client_secret'))
      ->set('api_url', $form_state->getValue('api_url'))
      ->save();
  }

}
