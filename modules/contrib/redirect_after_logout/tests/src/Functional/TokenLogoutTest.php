<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Test simple logout with tokens.
 *
 * @group redirect_after_logout
 */
class TokenLogoutTest extends TestBase {

  use PathAliasTestTrait;

  protected static $modules = [
    'node',
    'redirect_after_logout',
    'token',
  ];

  /**
   * Test logout without tokens.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testLogoutWithoutToken() {
    // Create test node with path alias.
    $this->createContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['title' => 'Node title']);
    $this->createPathAlias($node->toUrl()->toString(), '/node-alias-' . $this->regular_user->id());
    // Set redirect configuration.
    $message = $this->randomMachineName();
    $config = $this->config('redirect_after_logout.settings');
    $config->set('destination', '/node-alias-[current-user:uid]');
    $config->set('message', $message)->save();
    // Test redirect.
    $this->drupalLogin($this->regular_user);
    $this->drupalLogout();
    $this->assertSession()
      ->addressEquals('/node-alias-' . $this->regular_user->id());
    $this->assertSession()
      ->pageTextContains($message);
  }
}
