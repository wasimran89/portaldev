<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

use Drupal\Core\Session\AnonymousUserSession;
use Drupal\Core\Url;
use Drupal\Tests\block\Traits\BlockCreationTrait;
use Drupal\Tests\BrowserTestBase;

/**
 * redirect_after_logout test base.
 *
 * @group redirect_after_logout
 */
abstract class TestBase extends BrowserTestBase {

  use BlockCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'block',
    'redirect_after_logout',
  ];

  protected $defaultTheme = 'stable';

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $admin_user;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $authenticated_user;

  /**
   * @var \Drupal\user\Entity\User
   */
  protected $regular_user;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->admin_user = $this->drupalCreateUser(['administer site configuration']);
    $this->authenticated_user = $this->drupalCreateUser();
    $this->regular_user = $this->drupalCreateUser(['redirect user after logout']);
    $this->placeBlock('user_login_block', [
      'id' => $this->defaultTheme . '_' . strtolower($this->randomMachineName(8)),
    ]);
  }

  /**
   * Logs a user out of the Mink controlled browser, without check login page.
   *
   * @see \Drupal\Tests\UiHelperTrait::drupalLogout
   */
  protected function drupalLogoutWithoutCheck() {
    // Make a request to the logout page, and redirect to the user page, the
    // idea being if you were properly logged out you should be seeing a login
    // screen.
    $destination = Url::fromRoute('user.page')->toString();
    $this->drupalGet(Url::fromRoute('user.logout', [], ['query' => ['destination' => $destination]]));
    // @see BrowserTestBase::drupalUserIsLoggedIn()
    unset($this->loggedInUser->sessionId);
    $this->loggedInUser = FALSE;
    \Drupal::currentUser()->setAccount(new AnonymousUserSession());
  }
}
