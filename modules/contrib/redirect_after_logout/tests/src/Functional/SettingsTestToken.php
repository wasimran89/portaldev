<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

/**
 * Test settings page with Token module.
 *
 * @group redirect_after_logout
 */
class SettingsTestToken extends TestBase {

  protected static $modules = [
    'redirect_after_logout',
    'token',
  ];

  /**
   * Test settings form configuration with Token module.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSettingsFormWithToken() {
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    // Check token form elements.
    $this->assertSession()
      ->elementExists('css', '#edit-token-help');
  }
}
