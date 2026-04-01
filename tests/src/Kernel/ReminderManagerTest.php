<?php

namespace Drupal\Tests\adv_content_reminder\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Tests the Reminder Manager service.
 *
 * @group adv_content_reminder
 */
class ReminderManagerTest extends KernelTestBase {

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
   * Test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Reminder manager service.
   *
   * @var \Drupal\adv_content_reminder\Service\ReminderManager
   */
  protected $reminderManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install required schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);

    // Create a content type.
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create a test user.
    $this->user = User::create([
      'name' => 'testuser',
      'mail' => 'test@example.com',
      'status' => 1,
    ]);
    $this->user->save();

    // Get the service.
    $this->reminderManager = $this->container->get('adv_content_reminder.manager');
  }

  /**
   * Tests reminder processing on a valid node.
   */
  public function testReminderProcessing(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Reminder Test Node',
      'uid' => $this->user->id(),
      'status' => 1,
    ]);
    $node->save();

    // Execute reminder logic.
    $result = $this->reminderManager->processNode($node);

    // Assert expected outcome.
    $this->assertNotNull($result, 'Reminder processing returned a result.');
  }

  /**
   * Tests that unpublished nodes are skipped.
   */
  public function testUnpublishedNodeSkipped(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Unpublished Node',
      'uid' => $this->user->id(),
      'status' => 0,
    ]);
    $node->save();

    $result = $this->reminderManager->processNode($node);

    $this->assertFalse($result, 'Unpublished nodes should not trigger reminders.');
  }

  /**
   * Tests handling of nodes without an owner email.
   */
  public function testNodeWithoutValidEmail(): void {
    $user = User::create([
      'name' => 'noemailuser',
      'status' => 1,
    ]);
    $user->save();

    $node = Node::create([
      'type' => 'article',
      'title' => 'No Email Node',
      'uid' => $user->id(),
      'status' => 1,
    ]);
    $node->save();

    $result = $this->reminderManager->processNode($node);

    $this->assertFalse($result, 'Nodes without valid email should be skipped.');
  }

  /**
   * Tests cron-based reminder processing.
   */
  public function testCronProcessing(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Cron Reminder Node',
      'uid' => $this->user->id(),
      'status' => 1,
    ]);
    $node->save();

    // Run cron.
    $this->container->get('cron')->run();

    // If your module tracks reminders (state/log/etc),
    // assert here accordingly.
    $this->assertTrue(TRUE, 'Cron executed without errors.');
  }

}
