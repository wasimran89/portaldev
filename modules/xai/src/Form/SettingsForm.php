<?php

/**
 * @file
 * Contains Drupal\xai\Form\SettingsForm.
 */

namespace Drupal\xai\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class SettingsForm.
 *
 * @package Drupal\xai\Form
 */
class SettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'xai.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('xai.settings');
    $form['prod'] = [
      '#type'  => 'details',
      '#title' => $this->t('Production server settings'),
      '#open' => TRUE,
    ];
    $form['prod']['endpoint'] = array(
    '#type' => 'textfield',
    '#title' => t('Endpoint'),
    '#default_value' => $config->get('endpoint'),
    '#cache' => array('max-age' => 0)
    );

    $form['prod']['email'] = array(
    '#type' => 'textfield',
    '#title' => t('Email'),
    '#default_value' => $config->get('email'),
    '#cache' => array('max-age' => 0)
    );

    $form['prod']['password'] = array(
    '#type' => 'textfield',
    '#title' => t('Password'),
    '#default_value' => $config->get('password'),
    '#cache' => array('max-age' => 0)
    );
    
    $form['prod']['upload_pem'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload PEM File'),
      '#default_value' => $config->get('upload_pem'),
      '#upload_location' => 'public://content/secret/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pem'],
      ],
    ];

    $form['prod']['upload_crt'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CRT File'),
      '#default_value' => $config->get('upload_crt'),
      '#upload_location' => 'public://content/secret/',
      '#upload_validators' => [
        'file_validate_extensions' => ['crt'],
      ],
    ];

    $form['prod']['upload_key'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload KEY File'),
      '#default_value' => $config->get('upload_key'),
      '#upload_location' => 'public://content/secret/',
      '#upload_validators' => [
        'file_validate_extensions' => ['key'],
      ],
    ];
    //Pre prod
    $form['preprod'] = [
      '#type'  => 'details',
      '#title' => $this->t('Pre Production server settings'),
      '#open' => TRUE,
    ];
    $form['preprod']['pre_endpoint'] = array(
    '#type' => 'textfield',
    '#title' => t('Endpoint'),
    '#default_value' => $config->get('pre_endpoint'),
    '#cache' => array('max-age' => 0)
    );

    $form['preprod']['pre_email'] = array(
    '#type' => 'textfield',
    '#title' => t('Email'),
    '#default_value' => $config->get('pre_email'),
    '#cache' => array('max-age' => 0)
    );

    $form['preprod']['pre_password'] = array(
    '#type' => 'textfield',
    '#title' => t('Password'),
    '#default_value' => $config->get('pre_password'),
    '#cache' => array('max-age' => 0)
    );

    $form['preprod']['pre_upload_pem'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload PEM File'),
      '#default_value' => $config->get('pre_upload_pem'),
      '#upload_location' => 'public://content/secret/',
      '#upload_validators' => [
        'file_validate_extensions' => ['pem'],
      ],
    ];

    $form['preprod']['pre_upload_crt'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload CRT File'),
      '#default_value' => $config->get('pre_upload_crt'),
      '#upload_location' => 'public://content/secret/',
      '#upload_validators' => [
        'file_validate_extensions' => ['crt'],
      ],
    ];

    $form['preprod']['pre_upload_key'] = [
      '#type' => 'managed_file',
      '#title' => $this->t('Upload KEY File'),
      '#default_value' => $config->get('pre_upload_key'),
      '#upload_location' => 'public://content/secret/',
      '#upload_validators' => [
        'file_validate_extensions' => ['key'],
      ],
    ];

    //domain
    $form['mode'] = array(
      '#type' => 'select',
      '#title' => $this->t('Mode'),
      '#options'=> ['production'=>'Production','pre_production'=>'Pre Production'],
      '#default_value' => $config->get('mode'),
      '#description'=> 'Select mode',
    );

    //domain
    $form['domain'] = array(
      '#type' => 'textfield',
      '#title' => $this->t('Domain Url'),
      '#default_value' => $config->get('domain'),
      '#description'=> 'http://example.com/',
    );
    $form['bio'] = array(
      '#type' => 'textarea',
      '#title' => $this->t('Table Json'),
      '#default_value' => $config->get('bio'),
    );
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /*
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    $this->config('xai.settings')
      ->set('bio', $form_state->getValue('bio'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('mode', $form_state->getValue('mode'))
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('email', $form_state->getValue('email'))
      ->set('password', $form_state->getValue('password'))
      ->set('upload_pem', $form_state->getValue('upload_pem'))
      ->set('upload_crt', $form_state->getValue('upload_crt'))
      ->set('upload_key', $form_state->getValue('upload_key'))
      //pre prod
      ->set('pre_endpoint', $form_state->getValue('pre_endpoint'))
      ->set('pre_email', $form_state->getValue('pre_email'))
      ->set('pre_password', $form_state->getValue('pre_password'))
      ->set('pre_upload_pem', $form_state->getValue('pre_upload_pem'))
      ->set('pre_upload_crt', $form_state->getValue('pre_upload_crt'))
      ->set('pre_upload_key', $form_state->getValue('pre_upload_key'))
      ->save();
  }
  */

  /**
   * {@inheritdoc}
  */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);

    // Load and make the uploaded files permanent.
    $pem_file_id = $form_state->getValue('upload_pem');
    if (!empty($pem_file_id)) {
      $pem_file = \Drupal\file\Entity\File::load($pem_file_id[0]);
      if ($pem_file) {
        $pem_file->setPermanent();
        $pem_file->save();
      }
    }

    $crt_file_id = $form_state->getValue('upload_crt');
    if (!empty($crt_file_id)) {
      $crt_file = \Drupal\file\Entity\File::load($crt_file_id[0]);
      if ($crt_file) {
        $crt_file->setPermanent();
        $crt_file->save();
      }
    }

    $key_file_id = $form_state->getValue('upload_key');
    if (!empty($key_file_id)) {
      $key_file = \Drupal\file\Entity\File::load($key_file_id[0]);
      if ($key_file) {
        $key_file->setPermanent();
        $key_file->save();
      }
    }

    // For pre-prod files
    $pre_pem_file_id = $form_state->getValue('pre_upload_pem');
    if (!empty($pre_pem_file_id)) {
      $pre_pem_file = \Drupal\file\Entity\File::load($pre_pem_file_id[0]);
      if ($pre_pem_file) {
        $pre_pem_file->setPermanent();
        $pre_pem_file->save();
      }
    }

    $pre_crt_file_id = $form_state->getValue('pre_upload_crt');
    if (!empty($pre_crt_file_id)) {
      $pre_crt_file = \Drupal\file\Entity\File::load($pre_crt_file_id[0]);
      if ($pre_crt_file) {
        $pre_crt_file->setPermanent();
        $pre_crt_file->save();
      }
    }

    $pre_key_file_id = $form_state->getValue('pre_upload_key');
    if (!empty($pre_key_file_id)) {
      $pre_key_file = \Drupal\file\Entity\File::load($pre_key_file_id[0]);
      if ($pre_key_file) {
        $pre_key_file->setPermanent();
        $pre_key_file->save();
      }
    }

    // Save other form data.
    $this->config('xai.settings')
      ->set('bio', $form_state->getValue('bio'))
      ->set('domain', $form_state->getValue('domain'))
      ->set('mode', $form_state->getValue('mode'))
      ->set('endpoint', $form_state->getValue('endpoint'))
      ->set('email', $form_state->getValue('email'))
      ->set('password', $form_state->getValue('password'))
      ->set('upload_pem', $pem_file_id)
      ->set('upload_crt', $crt_file_id)
      ->set('upload_key', $key_file_id)
      ->set('pre_endpoint', $form_state->getValue('pre_endpoint'))
      ->set('pre_email', $form_state->getValue('pre_email'))
      ->set('pre_password', $form_state->getValue('pre_password'))
      ->set('pre_upload_pem', $pre_pem_file_id)
      ->set('pre_upload_crt', $pre_crt_file_id)
      ->set('pre_upload_key', $pre_key_file_id)
      ->save();
  }
}