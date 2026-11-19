<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
/**
 * Class EncryptionForm.
 *
 * @package Drupal\mydata\Form
 */
class EncryptionForm extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'encryptionForm';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['name'] = [
      '#type' => 'textfield',
      '#title' => t('Name'),
      '#required'=> true
      ];

    $form['submit_data'] = [
      '#type' => 'submit',
      '#value' => 'Generate',
      '#attributes' => [
        'class' => [
          'use-ajax',
        ],
      ],
      '#ajax' => [
        'callback' => [$this, 'submitModalFormAjax'],
        'event' => 'click',
      ],
    ];

    $form['actions']['previous'] = [
        '#type' => 'submit',
        '#submit' => [[$this, 'previousForm']],
        '#value' => 'Previous',
        '#limit_validation_errors' => [], //no validation for back button
        '#attributes' => [
                          'onClick' => 'javascript:window.history.go(-1); return false;'
                          ],
    ];    

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }


  public function previousForm(array &$form, FormStateInterface $form_state) {
    $response = new RedirectResponse("/mydata/oss/table?table=" . $_GET['table']);
    $response->send();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  //
  }

  /**
   * {@inheritdoc}
   */
  public function submitModalFormAjax(array &$form, FormStateInterface $form_state) {
    $response = new AjaxResponse();
    // If there are any form errors, AJAX replace the form.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(new ReplaceCommand('#modal_example_form', $form));
    } else {
      $field = $form_state->getValues();       
      $title = "Encrypt";
      $options = [
          'buttons' => [
            'button1' => [
              'text' => $this->t('Ok'),
              'id' => 'ok-button',
              'onclick' => "jQuery('.ui-dialog-titlebar-close').trigger('click')",
            ],
          ],
          'width' => 1300
      ];
      $encryption_profile = EncryptionProfile::load('url');
      $valueencrypted = \Drupal::service('encryption')->encrypt($field['name'], $encryption_profile);
      $content = $valueencrypted;
        
      $response->addCommand(new OpenModalDialogCommand($title, $content, $options));
    }
    return $response;    
  }
}