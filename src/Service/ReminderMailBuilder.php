<?php

namespace Drupal\adv_content_reminder\Service;

use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Utility\Token;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityRepositoryInterface;
use Drupal\Core\Config\ConfigFactoryInterface;
use Psr\Log\LoggerInterface;
use Drupal\node\NodeInterface;

/**
 * Builds and sends reminder emails.
 */
class ReminderMailBuilder {

  /**
   * The token service.
   *
   * @var \Drupal\Core\Utility\Token
   */
  protected Token $token;

  /**
   * The mail manager.
   *
   * @var \Drupal\Core\Mail\MailManagerInterface
   */
  protected MailManagerInterface $mailManager;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity repository.
   *
   * @var \Drupal\Core\Entity\EntityRepositoryInterface
   */
  protected EntityRepositoryInterface $entityRepository;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The logger.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $logger;

  public function __construct(
    Token $token,
    MailManagerInterface $mail_manager,
    EntityTypeManagerInterface $entity_type_manager,
    EntityRepositoryInterface $entity_repository,
    ConfigFactoryInterface $config_factory,
    LoggerInterface $logger,
  ) {
    $this->token = $token;
    $this->mailManager = $mail_manager;
    $this->entityTypeManager = $entity_type_manager;
    $this->entityRepository = $entity_repository;
    $this->configFactory = $config_factory;
    $this->logger = $logger;
  }

  /**
   * Sends reminder email for a node and offset.
   */
  public function sendReminder(NodeInterface $node, string $to, int $offset): bool {

    $node = $this->entityRepository->getTranslationFromContext($node);

    $config = $this->configFactory->get('adv_content_reminder.settings');
    $templates = $config->get('email_templates') ?? [];

    $template = NULL;

    // Find matching template by offset.
    foreach ($templates as $t) {
      if (isset($t['offset']) && (int) $t['offset'] === $offset) {
        $template = $t;
        break;
      }
    }

    if (!$template) {
      $this->logger->error('Reminder template missing for offset @offset.', [
        '@offset' => $offset,
      ]);
      return FALSE;
    }

    $subject_template = $template['subject'] ?? '';
    $body_template = $template['body']['value'] ?? '';

    if (empty($subject_template) || empty($body_template)) {
      $this->logger->error('Reminder email templates are empty for offset @offset.', [
        '@offset' => $offset,
      ]);
      return FALSE;
    }

    $token_data = [
      'node' => $node,
      'user' => $node->getOwner(),
    ];

    $metadata = new BubbleableMetadata();

    $subject = $this->token->replace(
      $subject_template,
      $token_data,
      ['clear' => TRUE],
      $metadata
    );

    $body = $this->token->replace(
      $body_template,
      $token_data,
      ['clear' => TRUE],
      $metadata
    );

    $this->logger->notice('Processed reminder subject for node @nid (offset @offset): @subject', [
      '@nid' => $node->id(),
      '@offset' => $offset,
      '@subject' => $subject,
    ]);

    $langcode = $node->language()->getId();

    $result = $this->mailManager->mail(
      'adv_content_reminder',
      'expiration_reminder',
      $to,
      $langcode,
      [
        'subject' => $subject,
        'body' => $body,
        'offset' => $offset,
      ],
      NULL,
      TRUE
    );

    if (empty($result['result'])) {
      $this->logger->error('Reminder email failed for node @nid (offset @offset).', [
        '@nid' => $node->id(),
        '@offset' => $offset,
      ]);
      return FALSE;
    }

    return TRUE;
  }

}
