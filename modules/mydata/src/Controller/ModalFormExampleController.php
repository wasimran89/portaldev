<?php

namespace Drupal\mydata\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;

/**
 * ModalFormExampleController class.
 */
class ModalFormExampleController extends ControllerBase {

  /**
   * The form builder.
   *
   * @var \Drupal\Core\Form\FormBuilder
   */
  protected $formBuilder;

  /**
   * The ModalFormExampleController constructor.
   *
   * @param \Drupal\Core\Form\FormBuilder $formBuilder
   *   The form builder.
   */
  public function __construct(FormBuilder $formBuilder) {
    $this->formBuilder = $formBuilder;
  }

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DependencyInjection\ContainerInterface $container
   *   The Drupal service container.
   *
   * @return static
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('form_builder')
    );
  }

  /**
   * Callback for opening the modal form.
   */
  public function openModalForm() {
    $response = new AjaxResponse();

    // Get the modal form using the form builder.
    $modal_form = \Drupal::formBuilder()->getForm('Drupal\mydata\Form\ModalForm');
    $options = [
      'buttons' => [
          'button1' => [
            'text' => $this->t('Close'),
            'id' => 'close-button',
            'onclick' => "jQuery('.ui-dialog-titlebar-close').click()"
          ],
          'button2' => [
            'text' => $this->t('Sync'),
            'id' => 'close-button',
            'onclick' => 'window.location.reload()'
          ],
      ],
      'width' => '1200'
    ];
    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('Logs', $modal_form, $options));

    return $response;
  }

  public function statsModalForm() {
    $response = new AjaxResponse();

    // Get the modal form using the form builder.
    $modal_form = \Drupal::formBuilder()->getForm('Drupal\mydata\Form\StatsModalForm');
    $options = [
      'buttons' => [
        'button1' => [
          'text' => $this->t('Ok'),
          'id' => 'close-button',
          'onclick' => 'window.location.reload();',
        ],
      ],
      'width' => '1200'
    ];
    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand($_GET['imsi_range_name'], $modal_form, $options));

    return $response;
  }

}
