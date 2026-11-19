<?php

namespace Drupal\Tests\redirect_after_logout\Functional;

use Drupal\Tests\Traits\Core\PathAliasTestTrait;

/**
 * Test settings page.
 *
 * @group redirect_after_logout
 */
class SettingsTest extends TestBase {

  use PathAliasTestTrait;

  protected static $modules = [
    'node',
    'redirect_after_logout',
    'token',
  ];

  /**
   * Test settings page access.
   */
  public function testSettingsPageAccess() {
    $this->drupalGet('admin/config/system/redirect_after_logout');
    self::assertEquals(403, $this->getSession()->getStatusCode());
    $this->drupalLogin($this->regular_user);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    self::assertEquals(403, $this->getSession()->getStatusCode());
    $this->drupalLogout();
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    self::assertEquals(200, $this->getSession()->getStatusCode());
  }

  /**
   * Test settings form configuration.
   *
   * @throws \Behat\Mink\Exception\ElementNotFoundException
   * @throws \Behat\Mink\Exception\ExpectationException
   */
  public function testSettingsForm() {
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('admin/config/system/redirect_after_logout');
    // Check form elements.
    $this->assertSession()
      ->fieldExists('edit-redirect-after-logout-destination');
    $this->assertSession()
      ->fieldExists('edit-redirect-after-logout-message');
    $this->assertSession()
      ->buttonExists('edit-submit');
    // Check form value saving.
    $redirect_after_logout_destination = '<front>';
    $redirect_after_logout_message = $this->randomString();
    $this->submitForm([
      'redirect_after_logout_destination' => $redirect_after_logout_destination,
      'redirect_after_logout_message' => $redirect_after_logout_message,
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
    $this->assertSession()
      ->addressEquals('admin/config/system/redirect_after_logout');
    $this->assertSession()
      ->fieldValueEquals('edit-redirect-after-logout-destination', $redirect_after_logout_destination);
    $this->assertSession()
      ->fieldValueEquals('edit-redirect-after-logout-message', $redirect_after_logout_message);
    // Check configuration values.
    $config = $this->config('redirect_after_logout.settings');
    $this::assertEquals($redirect_after_logout_destination, $config->get('destination'));
    $this::assertEquals($redirect_after_logout_message, $config->get('message'));
  }

  /**
   * Test destination path validation.
   *
   * @throws \Behat\Mink\Exception\ResponseTextException
   * @throws \Drupal\Core\Entity\EntityMalformedException
   */
  public function testSettingsFormDestinationValidation() {
    // Test node.
    $node = $this->drupalCreateNode(['title' => 'Node title']);
    $this->createPathAlias($node->toUrl()->toString(), '/foobar-example');
    $config = $this->config('system.site');
    $config->set('name', 'example');
    $config->save();
    $this->drupalLogin($this->admin_user);
    // Path without slash.
    $this->drupalGet('admin/config/system/redirect_after_logout');
    $destination = $this->randomMachineName();
    $this->submitForm([
      'redirect_after_logout_destination' => $destination,
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The path '$destination' has to start with a slash.");
    // Invalid path.
    $this->submitForm([
      'redirect_after_logout_destination' => '/' . $destination,
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("Either the path '/$destination' is invalid or you do not have access to it.");
    // External path.
    $this->submitForm([
      'redirect_after_logout_destination' => 'http://example.com/',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
    // External path with token.
    $this->submitForm([
      'redirect_after_logout_destination' => 'http://[site:name].com/',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
    // Valid node path.
    $this->createContentType(['type' => 'page']);
    $node = $this->drupalCreateNode(['title' => 'Node title']);
    $this->createPathAlias($node->toUrl()->toString(), '/node-alias');
    $this->submitForm([
      'redirect_after_logout_destination' => '/node-alias',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The configuration options have been saved.");
    // Path only token.
    $this->submitForm([
      'redirect_after_logout_destination' => '[current-page:url]',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
    // Path with token.
    $this->submitForm([
      'redirect_after_logout_destination' => '/foobar-[site:name]',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
    // Totally invalid path without token.
    $this->submitForm([
      'redirect_after_logout_destination' => 'fooBarBaz://fooBarBaz',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains("The path 'fooBarBaz://fooBarBaz' is invalid.");
    // Token with slash: /http:\\example.com\
    $this->submitForm([
      'redirect_after_logout_destination' => '/[current-page:url]',
    ], 'Save configuration');
    $this->assertSession()
      ->pageTextContains('The configuration options have been saved.');
  }
}
