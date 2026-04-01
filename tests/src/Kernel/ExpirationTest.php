<?php

namespace Drupal\Tests\adv_content_reminder_expiration\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Tests expiration behavior for content reminders.
 *
 * @group adv_content_reminder
 */
class ExpirationTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'datetime',
    'filter',
    'adv_content_reminder',
    'adv_content_reminder_expiration',
  ];

  /**
   * Test user.
   *
   * @var \Drupal\user\Entity\User
   */
  protected $user;

  /**
   * Expiration manager service.
   *
   * @var \Drupal\adv_content_reminder_expiration\Service\ExpirationManager
   */
  protected $expirationManager;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    // Install schemas.
    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('system', ['sequences']);

    // Install config (important if your module defines fields/config).
    $this->installConfig([
      'node',
      'adv_content_reminder',
      'adv_content_reminder_expiration',
    ]);

    // Create content type.
    $this->createContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);

    // Create test user.
    $this->user = User::create([
      'name' => 'expiration_user',
      'mail' => 'expiration@example.com',
      'status' => 1,
    ]);
    $this->user->save();

    // Get service.
    $this->expirationManager = $this->container->get('adv_content_reminder_expiration.manager');
  }

  /**
   * Helper to create a node with expiration date.
   */
  protected function createNodeWithExpiration(int $timestamp, int $status = 1): Node {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Expiring Node',
      'uid' => $this->user->id(),
      'status' => $status,
      // Adjust field name if different in your module.
      'field_expiration_date' => [
        'value' => gmdate('Y-m-d\TH:i:s', $timestamp),
      ],
    ]);
    $node->save();

    return $node;
  }

  /**
   * Tests that an expired node gets unpublished.
   */
  public function testNodeExpires(): void {
    $node = $this->createNodeWithExpiration(strtotime('-1 day'));

    // Run expiration logic.
    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertEquals(0, $node->isPublished(), 'Expired node was unpublished.');
  }

  /**
   * Tests that a future-dated node remains published.
   */
  public function testFutureNodeNotExpired(): void {
    $node = $this->createNodeWithExpiration(strtotime('+1 day'));

    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertEquals(1, $node->isPublished(), 'Future node remains published.');
  }

  /**
   * Tests that nodes without expiration field are ignored.
   */
  public function testNodeWithoutExpirationField(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'No Expiration Node',
      'uid' => $this->user->id(),
      'status' => 1,
    ]);
    $node->save();

    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertEquals(1, $node->isPublished(), 'Node without expiration is unchanged.');
  }

  /**
   * Tests cron processing for expiration.
   */
  public function testCronExpirationProcessing(): void {
    $expired_node = $this->createNodeWithExpiration(strtotime('-2 days'));
    $future_node = $this->createNodeWithExpiration(strtotime('+2 days'));

    // Run cron.
    $this->container->get('cron')->run();

    $expired_node = Node::load($expired_node->id());
    $future_node = Node::load($future_node->id());

    $this->assertEquals(0, $expired_node->isPublished(), 'Expired node unpublished via cron.');
    $this->assertEquals(1, $future_node->isPublished(), 'Future node unchanged via cron.');
  }

  /**
   * Tests idempotency (processing twice does not break state).
   */
  public function testIdempotentProcessing(): void {
    $node = $this->createNodeWithExpiration(strtotime('-1 day'));

    $this->expirationManager->processNode($node);
    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertEquals(0, $node->isPublished(), 'Processing twice does not change outcome.');
  }

}
