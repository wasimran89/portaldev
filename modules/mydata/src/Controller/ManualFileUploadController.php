<?php
#esim line view info modal
namespace Drupal\mydata\Controller;

use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Form\FormBuilder;

/**
 * ManualFileUploadController class.
 */
class ManualFileUploadController extends ControllerBase {

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
    $optionss = [
                  'buttons' => [
                      'button1' => [
                        'text' => $this->t('Ok'),
                        'id' => 'close-button',
                        'onclick' => 'window.location.reload()',
                      ],
                    ],
                  'width' => '800'
                ];
    // Get the modal form using the form builder.
    $modal_form = \Drupal::formBuilder()->getForm('Drupal\mydata\Form\ManualFileUploadForm');

    // Add an AJAX command to open a modal dialog with the form as the content.
    $response->addCommand(new OpenModalDialogCommand('File Upload', $modal_form, $optionss));

    return $response;
  }

}
