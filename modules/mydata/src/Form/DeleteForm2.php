<?php

namespace Drupal\mydata\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\encrypt\Entity\EncryptionProfile;
use Drupal\Core\Database\Connection;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Form\ConfirmFormBase;

/**
 * Confirmation form for deleting records from the external database.
 */
class DeleteForm2 extends ConfirmFormBase {

  /**
   * The external database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $externalDatabase;

  /**
   * The record ID.
   *
   * @var int|string|null
   */
  protected $id;

  /**
   * The confirmation ID.
   *
   * @var int|string|null
   */
  public $cid;

  /**
   * Constructs the delete confirmation form.
   *
   * @param \Drupal\Core\Database\Connection $externalConnection
   *   The external database connection.
   */
  public function __construct(Connection $externalConnection) {
    $this->externalDatabase = $externalConnection;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    $externalConnection = $container->get('mydata.external_database');

    return new static($externalConnection);
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'delete_form';
  }

  /**
   * {@inheritdoc}
   */
  public function getQuestion(): string {
    return $this->t('Do you want to delete %cid?', [
      '%cid' => $this->cid,
    ]);
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelUrl(): Url {
    return Url::fromRoute('mydata.display_table_controller_display2');
  }

  /**
   * {@inheritdoc}
   */
  public function getDescription(): string {
    $request = \Drupal::request();
    $table = $request->query->get('table', '');

    if ($table === 'input_file_detail') {
      return $this->t(
        'Delete Input File Detail @id @mnoid @profile_type?',
        [
          '@id' => $this->id,
          '@mnoid' => $request->query->get('mnoid', ''),
          '@profile_type' => $request->query->get('profile_type', ''),
        ]
      );
    }

    if ($table === 'input_element') {
      return $this->t(
        'Delete Input Element @element for Input File ID @id?',
        [
          '@element' => $request->query->get('input_element', ''),
          '@id' => $this->id,
        ]
      );
    }

    return $this->t('Only do this if you are sure!');
  }

  /**
   * {@inheritdoc}
   */
  public function getConfirmText(): string {
    return $this->t('Delete');
  }

  /**
   * {@inheritdoc}
   */
  public function getCancelText(): string {
    return $this->t('Cancel');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(
    array $form,
    FormStateInterface $form_state,
    $cid = NULL
  ): array {
    $this->id = $cid;
    $this->cid = $cid;

    $form['cancel'] = [
      '#type' => 'button',
      '#value' => $this->t('Cancel'),
      '#weight' => 1002,
      '#attributes' => [
        'onclick' => 'window.history.go(-1); return false;',
      ],
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(
    array &$form,
    FormStateInterface $form_state
  ): void {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(
    array &$form,
    FormStateInterface $form_state
  ): void {

    $request = \Drupal::request();
    $table = $request->query->get('table', '');

    // Decrypt the table name when an encrypted value is provided.
    if (strlen($table) > 50) {
      $encryption_profile = EncryptionProfile::load('url');

      if ($encryption_profile) {
        $decrypted = \Drupal::service('encryption')->decrypt(
          $table,
          $encryption_profile
        );

        if (!empty($decrypted)) {
          $table = $decrypted;
        }
      }
    }

    // Get table configuration.
    $config = \Drupal::config('xai.settings');
    $bio = json_decode($config->get('bio'), TRUE);

    if (
      !is_array($bio) ||
      !isset($bio[$table]) ||
      !isset($bio[$table]['primary_key'])
    ) {
      $this->messenger()->addError(
        $this->t('Invalid table configuration.')
      );
      return;
    }

    $table_config = $bio[$table];
    $primary_key = $table_config['primary_key'];

    // Build the delete query using the external database connection.
    $delete_query = $this->externalDatabase->delete($table)
      ->condition($primary_key, $this->id);

    // Apply the additional composite key condition for input_element.
    if ($table === 'input_element') {
      if (empty($table_config['composite_key'])) {
        $this->messenger()->addError(
          $this->t('Composite key configuration is missing.')
        );
        return;
      }

      $delete_query->condition(
        $table_config['composite_key'],
        $request->query->get('num1')
      );
    }

    // Execute deletion.
    $deleted = $delete_query->execute();

    if ($deleted) {
      $this->messenger()->addStatus(
        $this->t('Successfully deleted.')
      );
    }
    else {
      $this->messenger()->addWarning(
        $this->t('No matching record was found to delete.')
      );
    }

    // Redirect to the table listing page.
    $response = Url::fromUserInput('/mydata/oss/table2', [
      'query' => [
        'table' => $table,
      ],
    ]);

    $form_state->setRedirectUrl($response);
  }

}
