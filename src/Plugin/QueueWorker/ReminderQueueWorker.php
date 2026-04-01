<?php

namespace Drupal\adv_content_reminder\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\adv_content_reminder\Service\ReminderMailBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes expiration reminder queue items.
 *
 * @QueueWorker(
 *   id = "adv_content_reminder_queue",
 *   title = @Translation("Advanced Content Reminder Queue"),
 *   cron = {"time" = 60}
 * )
 */
class ReminderQueueWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The logger factory.
   *
   * @var \Drupal\Core\Logger\LoggerChannelFactoryInterface
   */
  protected LoggerChannelFactoryInterface $loggerFactory;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The reminder mail builder service.
   *
   * @var \Drupal\adv_content_reminder\Service\ReminderMailBuilder
   */
  protected ReminderMailBuilder $mailBuilder;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    LoggerChannelFactoryInterface $logger_factory,
    ConfigFactoryInterface $config_factory,
    ReminderMailBuilder $mail_builder,
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
    $this->loggerFactory = $logger_factory;
    $this->configFactory = $config_factory;
    $this->mailBuilder = $mail_builder;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('logger.factory'),
      $container->get('config.factory'),
      $container->get('adv_content_reminder.reminder_mail_builder'),
    );
  }

  /**
   * Processes a single queue item.
   */
  public function processItem($data): void {

    // Validate required data.
    if (empty($data['nid']) || !isset($data['offset'])) {
      return;
    }

    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($data['nid']);

    // Skip if node missing or unpublished.
    if (!$node || !$node->isPublished()) {
      return;
    }

    $offset = (int) $data['offset'];

    // Build recipients.
    $emails = $this->buildRecipients($node);

    if (empty($emails)) {
      return;
    }

    foreach ($emails as $email) {

      $result = $this->mailBuilder->sendReminder($node, $email, $offset);

      if (!$result) {
        $this->loggerFactory
          ->get('adv_content_reminder')
          ->error(
            'Failed sending reminder for node @nid at offset @offset to @email.',
            [
              '@nid' => $node->id(),
              '@offset' => $offset,
              '@email' => $email,
            ]
          );
        continue;
      }

      $this->loggerFactory
        ->get('adv_content_reminder')
        ->info(
          'Reminder sent for node @nid (@type) at offset @offset to @email.',
          [
            '@nid' => $node->id(),
            '@type' => $node->bundle(),
            '@offset' => $offset,
            '@email' => $email,
          ]
        );
    }
  }

  /**
   * Builds recipient list for a node.
   */
  protected function buildRecipients($node): array {
    $config = $this->configFactory->get('adv_content_reminder.settings');
    $additional_emails = $config->get('additional_emails') ?? [];

    $emails = [];

    // Node owner.
    $owner = $node->getOwner();
    if ($owner && $owner->getEmail()) {
      $emails[] = $owner->getEmail();
    }

    // Additional configured emails.
    if (!empty($additional_emails)) {
      $emails = array_merge($emails, $additional_emails);
    }

    // Deduplicate + sanitize.
    return array_unique(array_filter($emails));
  }

}
