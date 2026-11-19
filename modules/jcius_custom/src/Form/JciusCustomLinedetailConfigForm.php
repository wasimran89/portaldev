<?php

namespace Drupal\jcius_custom\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Form\ConfigFormBase;

/**
 * Line API secret keys configuration form.
 */
class JciusCustomLinedetailConfigForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'jcius_custom.linedetailviewconfig_form',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'line_detail_view_config_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config('jcius_custom.linedetailviewconfig_form');

    $form['view_machine_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('View Machine Name'),
      '#description' => $this->t('Add view machine name to apply row highliter Style'),
      '#default_value' => $config->get('view_machine_name'),
    ];
    $form['node_field_name'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field Name'),
      '#description' => $this->t('Field name to set the highlighter based on value'),
      '#default_value' => $config->get('node_field_name'),
    ];
    $form['node_field_value'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Field Value'),
      '#description' => $this->t('Set field value for highlighter,based on value it will apply'),
      '#default_value' => $config->get('node_field_value'),
    ];
    $form['issuspend_row_class'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Add Row CSS Class'),
      '#description' => $this->t('Please add css class for view row  to apply style. default class "rowbg-color"'),
      '#default_value' => 'rowbg-color ',
    ];
    $form['issuspend_enabled'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Row highlighting setting Enable.'),
      '#description' => $this->t('If this setting enable the row will highlighted by red color for line_detail View.'),
      '#default_value' => $config->get('issuspend_enabled'),
    ];
    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $this->config('jcius_custom.linedetailviewconfig_form')
      ->set('view_machine_name', $form_state->getValue('view_machine_name'))
      ->set('node_field_name', $form_state->getValue('node_field_name'))
      ->set('node_field_value', $form_state->getValue('node_field_value'))
      ->set('issuspend_row_class', $form_state->getValue('issuspend_row_class'))
      ->set('issuspend_enabled', $form_state->getValue('issuspend_enabled'))->save();
  }

}
