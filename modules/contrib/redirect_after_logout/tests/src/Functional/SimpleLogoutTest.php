<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

/**
 * Test simple logout, without tokens.
 *
 * @group redirect_after_logout
 */
class SimpleLogoutTest extends TestBase {

  protected static $modules = [
    'redirect_after_logout',
  ];

  /**
   * Test logout without tokens.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLogoutWithoutToken() {
    $message = $this->randomMachineName();
    $config = $this->config('redirect_after_logout.settings');
    $config->set('message', $message)->save();
    // not redirect authenticated user
    $config->set('destination', $this->authenticated_user->toUrl()->toString())->save();
    $this->drupalLogin($this->authenticated_user);
    $this->drupalLogout();
    $this->assertSession()
      ->addressNotEquals($this->authenticated_user->toUrl()->toString());
    $this->assertSession()
      ->pageTextNotContains($message);
    // redirect regular user
    $config->set('destination', $this->regular_user->toUrl()->toString())->save();
    $this->drupalLogin($this->regular_user);
    $this->drupalLogout();
    $this->assertSession()
      ->addressEquals($this->regular_user->toUrl()->toString());
    $this->assertSession()
      ->pageTextContains($message);
    // redirect regular user to external website
    $config->set('destination', 'http://example.com/')->save();
    $this->drupalLogin($this->regular_user);
    $this->drupalLogoutWithoutCheck();
    $this->assertSession()
      ->addressEquals('http://example.com/');
  }
}
