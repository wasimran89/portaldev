<?php

namespace Drupal\mydata\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\user\Entity\User;

/**
 * Controller for removing users from the email list.
 */
class RemoveUserController extends ControllerBase {

  /**
   * Displays the description page.
   *
   * @return array
   *   A render array.
   */
  public function getContent(): array {
    return [
      'description' => [
        '#theme' => 'mydata_description',
        '#description' => 'foo',
        '#attributes' => [],
      ],
    ];
  }

  /**
   * Removes users whose email addresses exist in the text file.
   *
   * @return array
   *   A render array containing the deletion result.
   */
  public function display(): array {

    $file_path = '/var/www/html/usim-webportal/remove_user_emails.txt';

    // Check whether the email file exists.
    if (!file_exists($file_path) || !is_readable($file_path)) {
      return [
        '#markup' => $this->t('Email file does not exist or is not readable.'),
      ];
    }

    // Read and clean email addresses.
    $email_list = file(
      $file_path,
      FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES
    );

    $email_list = array_filter(array_map(
      static fn($email) => strtolower(trim($email)),
      $email_list
    ));

    if (empty($email_list)) {
      return [
        '#markup' => $this->t('No email addresses found in the file.'),
      ];
    }

    // Create a lookup array for faster email matching.
    $email_lookup = array_fill_keys($email_list, TRUE);

    // Explicitly disable access checks for this administrative cleanup.
    $ids = \Drupal::entityQuery('user')
      ->accessCheck(FALSE)
      ->execute();

    $users = User::loadMultiple($ids);

    $deleted_count = 0;

    foreach ($users as $user) {

      // Protect the Drupal superuser account (UID 1).
      if ($user->id() == 1) {
        continue;
      }

      $email = strtolower(trim($user->getEmail()));

      if (isset($email_lookup[$email])) {
        $user->delete();
        $deleted_count++;
      }
    }

    return [
      '#markup' => $this->t('@count user account(s) deleted successfully.', [
        '@count' => $deleted_count,
      ]),
    ];
  }

}
