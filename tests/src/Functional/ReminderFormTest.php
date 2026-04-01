<?php

namespace Drupal\Tests\adv_content_reminder\Functional;

use Drupal\Tests\BrowserTestBase;

/**
 * Tests the Advanced Content Reminder content types form.
 *
 * @group adv_content_reminder
 */
class ReminderFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'adv_content_reminder',
  ];

  /**
   * Admin user.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Create a content type so checkboxes appear.
    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    $this->drupalCreateContentType([
      'type' => 'page',
      'name' => 'Basic page',
    ]);

    // Admin user.
    $this->adminUser = $this->drupalCreateUser([
      'administer site configuration',
    ]);

    $this->drupalLogin($this->adminUser);
  }

  /**
   * Tests that the form loads and displays content types.
   */
  public function testFormLoads(): void {
    $this->drupalGet('/admin/config/content/adv-content-reminder');

    $this->assertSession()->statusCodeEquals(200);
    $this->assertSession()->pageTextContains('Advanced Content Reminder');

    // Checkboxes exist.
    $this->assertSession()->fieldExists('monitored_content_types[article]');
    $this->assertSession()->fieldExists('monitored_content_types[page]');
  }

  /**
   * Tests validation when no content types are selected.
   */
  public function testValidationRequiresSelection(): void {
    $this->drupalGet('/admin/config/content/adv-content-reminder');

    // Submit with nothing selected.
    $this->submitForm([], 'Save configuration');

    $this->assertSession()->pageTextContains('You must select at least one content type.');
  }

  /**
   * Tests successful form submission.
   */
  public function testFormSubmission(): void {
    $this->drupalGet('/admin/config/content/adv-content-reminder');

    $edit = [
      'monitored_content_types[article]' => 'article',
      'monitored_content_types[page]' => 0,
    ];

    $this->submitForm($edit, 'Save configuration');

    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('adv_content_reminder.settings');

    $this->assertEquals(['article'], $config->get('monitored_content_types'));
  }

  /**
   * Tests access control.
   */
  public function testAccessControl(): void {
    $this->drupalLogout();

    $user = $this->drupalCreateUser([]);
    $this->drupalLogin($user);

    $this->drupalGet('/admin/config/content/adv-content-reminder');

    $this->assertSession()->statusCodeEquals(403);
  }

}
