<?php

namespace Drupal\Tests\adv_content_reminder_expiration\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\user\Entity\User;

/**
 * Tests expiration behavior via ExpirationManager service.
 *
 * @group adv_content_reminder
 */
class ExpirationBehaviorTest extends KernelTestBase {

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

    // Install config.
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
    $this->expirationManager = $this->container
      ->get('adv_content_reminder_expiration.manager');
  }

  /**
   * Helper: create node with expiration date.
   */
  protected function createNode(int $timestamp, int $status = 1): Node {
    $node = Node::create([
      'type' => 'article',
      'title' => 'Test Node',
      'uid' => $this->user->id(),
      'status' => $status,
      'field_expiration_date' => [
        'value' => gmdate('Y-m-d\TH:i:s', $timestamp),
      ],
    ]);
    $node->save();

    return $node;
  }

  /**
   * Tests that expired nodes are unpublished.
   */
  public function testExpiredNodeUnpublished(): void {
    $node = $this->createNode(strtotime('-1 day'));

    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertFalse($node->isPublished(), 'Expired node was unpublished.');
  }

  /**
   * Tests that future nodes remain published.
   */
  public function testFutureNodeRemainsPublished(): void {
    $node = $this->createNode(strtotime('+1 day'));

    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertTrue($node->isPublished(), 'Future node remains published.');
  }

  /**
   * Tests node without expiration field is ignored.
   */
  public function testNodeWithoutExpirationField(): void {
    $node = Node::create([
      'type' => 'article',
      'title' => 'No Expiration',
      'uid' => $this->user->id(),
      'status' => 1,
    ]);
    $node->save();

    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertTrue($node->isPublished(), 'Node without expiration unchanged.');
  }

  /**
   * Tests cron processes multiple nodes correctly.
   */
  public function testCronProcessing(): void {
    $expired = $this->createNode(strtotime('-2 days'));
    $future = $this->createNode(strtotime('+2 days'));

    // Run cron.
    $this->container->get('cron')->run();

    $expired = Node::load($expired->id());
    $future = Node::load($future->id());

    $this->assertFalse($expired->isPublished(), 'Expired node unpublished via cron.');
    $this->assertTrue($future->isPublished(), 'Future node unaffected via cron.');
  }

  /**
   * Tests idempotency (multiple runs do not break state).
   */
  public function testIdempotency(): void {
    $node = $this->createNode(strtotime('-1 day'));

    $this->expirationManager->processNode($node);
    $this->expirationManager->processNode($node);

    $node = Node::load($node->id());

    $this->assertFalse($node->isPublished(), 'Repeated processing is safe.');
  }

}
