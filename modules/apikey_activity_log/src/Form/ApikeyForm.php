<?php

namespace Drupal\apikey_activity_log\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Class ApikeyForm.
 */
class ApikeyForm extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'api_key_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $group = $user->get('field_group_type')->getValue();
    $group_id = $group[0]['target_id'] ?? NULL;

    // Fetch existing API key from DB
    $connection = \Drupal::database();
    $query = $connection->select('group__field_api_key', 'k');
    $query->fields('k', ['field_api_key_value']);
    $query->condition('k.entity_id', $group_id);
    $record = $query->execute()->fetchField();

    // If a new key was generated during AJAX, override DB key
    if ($form_state->get('generated_api_key')) {
      $record = $form_state->get('generated_api_key');
    }

    // Wrapper for AJAX refresh
    $form['api_key_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'api-key-field'],
      '#tree' => TRUE,   // IMPORTANT
    ];


    // Textfield will always display latest value
    $form['api_key_wrapper']['field_api_key'] = [
      '#type' => 'textfield',
      '#title' => 'API Key',
      '#value' => $record ?? '',
      '#attributes' => [
        'readonly' => 'readonly',
      ],
    ];

    // AJAX Generate Button
    $form['generate_api_key'] = [
      '#type' => 'submit',
      '#value' => $this->t('Generate API Key'),
      '#submit' => ['::generateApiKeySubmit'],
      '#ajax' => [
        'callback' => '::generateApiKeyAjax',
        'wrapper' => 'api-key-field',
      ],
      '#limit_validation_errors' => [],
    ];

    // Show API Key button
    $form['toggle_api_key'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => t('Show Key'),
        '#attributes' => [
          'type' => 'button',
          'id' => 'api-key-toggle2',
          'class' => ['api-key-toggle-btn2', 'button'],
          'disabled' => $record ? NULL : 'disabled',
        ],
      ];

      $form['copy_api_key'] = [
        '#type' => 'html_tag',
        '#tag' => 'button',
        '#value' => t('Copy Key'),
        '#attributes' => [
          'type' => 'button',
          'id' => 'api-key-copy2',
          'class' => ['api-key-copy-btn2', 'button'],
          'disabled' => $record ? NULL : 'disabled',
        ],
      ];

    // Save button for the whole form
    $form['submit'] = [
      '#type' => 'submit',
      '#value' => 'Save',
    ];

    $form['back'] = [
      '#type' => 'button',
      '#value' => t('Back'),
      '#attributes' => [
        'onclick' => 'history.back(); return false;',
        'class' => ['button'],
      ],
    ];


    // Prevent Drupal caching
    $form['#cache'] = ['max-age' => 0];

    // Attach JS libraries
    $form['#attached']['library'][] = 'apikey_activity_log/apikey_libraries';

    return $form;
  }

  public function validateForm(array &$form, FormStateInterface $form_state) {
    $values = $form_state->getValues();
    $new_api_key = $values['api_key_wrapper']['field_api_key'];

    // Check in DB if API key exists
    $connection = \Drupal::database();
    $query = $connection->select('group__field_api_key', 'k');
    $query->fields('k', ['entity_id']);
    $query->condition('k.field_api_key_value', $new_api_key);
    $exists = $query->execute()->fetchField();

    // Current user's group
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $group = $user->get('field_group_type')->getValue();
    $current_group_id = $group[0]['target_id'] ?? NULL;

    // Only error if key exists for a *different* group
    if ($exists && $exists != $current_group_id) {
      $form_state->setErrorByName(
        'api_key_wrapper][field_api_key',
        $this->t('API Key must be unique. This key already exists for another group.')
      );
    }
  }


  /**
   * Default submit for save button.
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $group = $user->get('field_group_type')->getValue();
    $group_id = $group[0]['target_id'] ?? NULL;
    $data = $form_state->getValues();

    // Load group entity
    $group = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->load($group_id);

    if ($group) {
      $newKey = $data['api_key_wrapper']['field_api_key'];
      $oldKey = $group->get('field_api_key')->value;

      // Set new API key
      $group->set('field_api_key', $data['api_key_wrapper']['field_api_key']);

      // Save changes
      $group->save();
      \Drupal::messenger()->addStatus($this->t('API Key updated successfully.'));
      // Log only if NEW KEY was generated + saved
      if ($newKey && $newKey !== $oldKey) {

        $label = $group->label();
        $connection = \Drupal::database();

        $connection->insert('apikey_activity_log')
          ->fields([
            'uid' => \Drupal::currentUser()->id(),
            'group_name' => $label,
            'api_key_value' => $newKey,
            'activity' => "Generated API Key",
            'created_at' => \Drupal::time()->getRequestTime(),
          ])
          ->execute();
      }

      \Drupal::logger('apikey')->notice("API key updated for Group ID: $group_id");
    }
    else {
      \Drupal::logger('apikey')->error("Group not found: $group_id");
    }

  }

  /**
   * Generate API Key submit handler.
   */
  public function generateApiKeySubmit(array &$form, FormStateInterface $form_state) {

    $connection = \Drupal::database();

    // Generate a unique 32-char key
    do {
      $key = substr(str_shuffle('0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz'), 0, 32);

      // Check if key already exists
      $query = $connection->select('group__field_api_key', 'k');
      $query->fields('k', ['entity_id']);
      $query->condition('k.field_api_key_value', $key);
      $exists = $query->execute()->fetchField();
    } while ($exists);

    // Store generated key for rebuild
    $form_state->set('generated_api_key', $key);

    $user = \Drupal\user\Entity\User::load(\Drupal::currentUser()->id());
    $group = $user->get('field_group_type')->getValue();
    $group_id = $group[0]['target_id'] ?? NULL;

    $group = \Drupal::entityTypeManager()
      ->getStorage('group')
      ->load($group_id);

    if ($group) {
      $label = $group->label();
      \Drupal::logger('debug')->notice('Group label: @l', ['@l' => $label]);
    }
    else {
      $label = 'NA';
    }

    // Log activity
    /*
    $connection->insert('apikey_activity_log')
      ->fields([
        'uid' => \Drupal::currentUser()->id(),
        'group_name' => $label,
        'api_key_value' => $key,
        'activity' => "Generated API Key",
        'created_at' => \Drupal::time()->getRequestTime(),
      ])
      ->execute();

    */

    // Trigger AJAX rebuild
    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback to update the API key field.
   */
  public function generateApiKeyAjax(array &$form, FormStateInterface $form_state) {
    return $form['api_key_wrapper'];
  }

}
