<?php
namespace Drupal\user_credit_tabs\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\user\Entity\User;
use Drupal\group\Entity\Group;

class UserCreditTabsController extends ControllerBase {

  /**
   * Display user credits.
   *
   * @param \Drupal\user\Entity\User $user
   *   The user entity.
   *
   * @return array
   *   A render array.
   */
  public function view(User $user) {
    if ($user) {
      $cgroup = $user->field_group_type->getValue(); //current user group load
      $group_id = $cgroup[0]['target_id']; //current user group id
      $group = Group::load($group_id);
      if ($group) {
        $group_label = $group->label();
        // Fetch the credit fields.
        $credit_reset = $group->get('field_credit_reset')->value;
        $credit_release = $group->get('field_credit_release')->value;

        $markup = '<div class="content"><h2>' . $group_label . ' Credits</h2>';
        $markup .= '<p><strong>eSIM Reset Credits:</strong> ' . $credit_reset . '</p>';
        $markup .= '<p><strong>eSIM Release Credits:</strong> ' . $credit_release . '</p>';
        $markup .= '</div>';
        $markup .= '<a href="/credit-allotment?group_name='.$group_label.'">Credit Allotment</a>';

        return [
          '#markup' => $markup,
          '#cache' => [
            'max-age' => 0,
          ],
        ];

      } else {
        return [
          '#markup' => t('Group not found.'),
          '#cache' => [
            'max-age' => 0,
          ],
        ];
      }
    }
  }
}
?>
