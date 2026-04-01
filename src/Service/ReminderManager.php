<?php

namespace Drupal\adv_content_reminder\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\node\NodeInterface;

/**
 * Handles reminder orchestration and queueing.
 */
class ReminderManager {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The queue factory.
   *
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected QueueFactory $queueFactory;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The time service.
   *
   * @var \Drupal\Component\Datetime\TimeInterface
   */
  protected TimeInterface $time;

  /**
   * The reminder mail builder.
   *
   * @var \Drupal\adv_content_reminder\Service\ReminderMailBuilder
   */
  protected ReminderMailBuilder $mailBuilder;

  /**
   * The reminder evaluator service.
   *
   * @var \Drupal\adv_content_reminder\Service\ReminderEvaluator
   */
  protected ReminderEvaluator $evaluator;

  /**
   * The queue instance.
   *
   * @var \Drupal\Core\Queue\QueueInterface
   */
  protected $queue;

  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    ConfigFactoryInterface $config_factory,
    QueueFactory $queue_factory,
    LoggerChannelFactoryInterface $logger_factory,
    TimeInterface $time,
    ReminderMailBuilder $mail_builder,
    ReminderEvaluator $evaluator,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->configFactory = $config_factory;
    $this->queueFactory = $queue_factory;
    $this->loggerFactory = $logger_factory;
    $this->time = $time;
    $this->mailBuilder = $mail_builder;
    $this->evaluator = $evaluator;

    $this->queue = $this->queueFactory->get('adv_content_reminder_queue');
  }

  /**
   * Get module config.
   */
  public function getConfig() {
    return $this->configFactory->get('adv_content_reminder.settings');
  }

  /**
   * Cron entry point: process all eligible nodes.
   */
  public function processAll(): void {
    $config = $this->getConfig();
    $content_types = $config->get('monitored_content_types') ?? [];

    if (empty($content_types)) {
      return;
    }

    $nids = $this->entityTypeManager
      ->getStorage('node')
      ->getQuery()
      ->accessCheck(FALSE)
      ->condition('status', 1)
      ->condition('type', $content_types, 'IN')
      ->condition('field_expiration_date', NULL, 'IS NOT NULL')
      ->execute();

    if (empty($nids)) {
      return;
    }

    $nodes = $this->entityTypeManager
      ->getStorage('node')
      ->loadMultiple($nids);

    foreach ($nodes as $node) {
      $this->processNode($node);
    }
  }

  /**
   * Processes a single node for reminder evaluation.
   */
  public function processNode(NodeInterface $node): void {
    $offset = $this->evaluator->evaluate($node);

    if ($offset === NULL) {
      return;
    }

    $this->queueReminder($node->id(), $offset);
  }

  /**
   * Queue a reminder email (queue-safe).
   */
  protected function queueReminder(int $nid, int $offset): void {

    // Create a unique key per node + offset + day using immutable datetime.
    $today = (new \DateTimeImmutable())
      ->setTimestamp($this->time->getRequestTime())
      ->setTime(0, 0);

    $today_key = $today->format('Y-m-d');

    $unique_key = "{$nid}:{$offset}:{$today_key}";

    // Prevent duplicate queue items (runtime).
    static $queued = [];
    if (isset($queued[$unique_key])) {
      return;
    }

    $this->queue->createItem([
      'nid' => $nid,
      'offset' => $offset,
      'created' => $this->time->getRequestTime(),
      'unique_key' => $unique_key,
    ]);

    $queued[$unique_key] = TRUE;

    $this->loggerFactory
      ->get('adv_content_reminder')
      ->info('Queued reminder for node @nid at offset @offset.', [
        '@nid' => $nid,
        '@offset' => $offset,
      ]);
  }

  /**
   * Sends a test email.
   */
  public function sendTestEmail(string $email, int $offset): void {

    $config = $this->getConfig();
    $templates = $config->get('email_templates') ?? [];

    $template = NULL;

    foreach ($templates as $t) {
      if ((int) $t['offset'] === $offset) {
        $template = $t;
        break;
      }
    }

    if (!$template) {
      $this->loggerFactory
        ->get('adv_content_reminder')
        ->error('Invalid reminder offset "@offset" for test email.', [
          '@offset' => $offset,
        ]);
      return;
    }

    // Load a sample node for token replacement.
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load(1);

    if (!$node instanceof NodeInterface) {
      return;
    }

    $this->mailBuilder->sendReminder($node, $email, $offset);
  }

}
