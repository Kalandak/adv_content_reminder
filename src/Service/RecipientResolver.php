<?php

namespace Drupal\adv_content_reminder\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\node\NodeInterface;

/**
 * Resolves recipients for reminder emails.
 */
class RecipientResolver {

  /**
   * The module configuration.
   *
   * @var \Drupal\Core\Config\ImmutableConfig
   */
  protected $config;

  public function __construct(ConfigFactoryInterface $config_factory) {
    $this->config = $config_factory->get('adv_content_reminder.settings');
  }

  /**
   * Builds recipient list for a node.
   */
  public function getRecipients(NodeInterface $node): array {
    $additional_emails = $this->config->get('additional_emails') ?? [];

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

    return array_unique(array_filter($emails));
  }

}
