<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Ajax\AjaxResponse;
use Drupal\Core\Ajax\CloseModalDialogCommand;
use Drupal\Core\Ajax\OpenModalDialogCommand;
use Drupal\Core\Ajax\ReplaceCommand;
use Drupal\Core\Database\Connection;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Drupal\Core\Ajax\RedirectCommand;

/**
 * Comment form.
 */
class UsimCommentForm extends FormBase {

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected Connection $externalDatabase;

  /**
   * The current request.
   *
   * @var \Symfony\Component\HttpFoundation\Request
   */
  protected $request;

  /**
   * Constructs the CommentForm.
   *
   * @param \Symfony\Component\HttpFoundation\RequestStack $request_stack
   *   The request stack service.
   * @param \Drupal\Core\Database\Connection $external_connection
   *   The external database connection.
   */
  public function __construct(
    RequestStack $request_stack,
    Connection $external_connection
  ) {
    $this->request = $request_stack->getCurrentRequest();
    $this->externalDatabase = $external_connection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('request_stack'),
      $container->get('mydata.external_database')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'comment_modal_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $options = NULL
  ) {
    $master_id = $this->request->query->get('master_id');

    // Store profile ID for AJAX submit.
    $form_state->set('master_id', $master_id);

    // Prefetch existing comment.
    $existing_comment = '';

    if ($master_id) {
      $existing_comment = $this->externalDatabase
        ->select('usim_status_comment', 'usc')
        ->fields('usc', ['comment'])
        ->condition('master_id', $master_id)
        ->range(0, 1)
        ->execute()
        ->fetchField() ?: '';
    }

    $form['#prefix'] = '<div id="modal_example_form">';
    $form['#suffix'] = '</div>';

    $form['comment'] = [
      '#type' => 'textarea',
      '#title' => $this->t('Comment'),
      '#default_value' => $existing_comment,
      '#rows' => 5,
      '#cols' => 40,
      '#prefix' => '<div class="custom-wrapper2">',
    ];

    $form['send'] = [
      '#type' => 'submit',
      '#value' => $this->t('Save'),
      '#attributes' => [
        'class' => ['use-ajax'],
      ],
      '#ajax' => [
        'callback' => [$this, 'sendModalFormAjax'],
        'event' => 'click',
      ],
      '#suffix' => '</div>',
    ];

    $form['#attached']['library'][] = 'core/drupal.dialog.ajax';

    return $form;
  }

  /**
   * AJAX callback.
   */
  public function sendModalFormAjax(
    array $form,
    FormStateInterface $form_state
  ) {
    $response = new AjaxResponse();

    // Return the form if validation fails.
    if ($form_state->hasAnyErrors()) {
      $response->addCommand(
        new ReplaceCommand('#modal_example_form', $form)
      );

      return $response;
    }

    $comment = trim((string) $form_state->getValue('comment'));
    $master_id = $form_state->get('master_id');

    /*if ($comment === '') {
      $response->addCommand(
        new OpenModalDialogCommand(
          $this->t('Error'),
          $this->t('Comment cannot be empty.'),
          ['width' => 700]
        )
      );

      return $response;
    }
    */

    if (empty($master_id)) {
      $response->addCommand(
        new OpenModalDialogCommand(
          $this->t('Error'),
          $this->t('USIM master id is missing.'),
          ['width' => 700]
        )
      );

      return $response;
    }

    try {
      // Insert new comment or update existing comment.
      $this->externalDatabase
        ->merge('usim_status_comment')
        ->key('master_id', $master_id)
        ->fields([
          'comment' => $comment,
        ])
        ->execute();

      $referer = $this->request->headers->get('referer');

      if ($referer) {
        $this->messenger()->addStatus(
          $this->t('Comment saved successfully.')
        );

        $response->addCommand(
          new RedirectCommand($referer)
        );
      }
      
    }
    catch (\Exception $e) {
      $response->addCommand(
        new OpenModalDialogCommand(
          $this->t('Error'),
          $this->t('An error occurred while saving the comment: @message', [
            '@message' => $e->getMessage(),
          ]),
          ['width' => 700]
        )
      );
    }
    return $response;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state
  ) {
    // Form is handled by AJAX callback.
  }

}