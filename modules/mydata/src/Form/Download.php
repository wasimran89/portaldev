<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
/**
 * SendToDestinationsForm class.
 */
class Download extends FormBase {
  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'download_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state, $options = NULL) {   
    try{

      $file = $_GET['filename'];
      $type = $_GET['type'];
      $response = new AjaxResponse();  
      $filename = '/home/clarion/oss_scripts/data/'.$type."/".$file;
      $response = new BinaryFileResponse($filename);
      //for file name
      $response->setContentDisposition(
          ResponseHeaderBag::DISPOSITION_ATTACHMENT,
          $file
      );
      return $response;
    } catch (\Exception $e) {
      \Drupal::messenger()->addError('Zip Not Exist');
    }   
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
  }
}