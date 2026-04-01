<?php

namespace Drupal\adv_content_reminder\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Datetime\DrupalDateTime;
use Drupal\node\NodeInterface;

/**
 * Determines whether a node should trigger a reminder.
 */
class ReminderEvaluator {

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
   * Determines if a reminder should be sent.
   *
   * @return int|null
   *   Offset if matched, otherwise NULL.
   */
  public function evaluate(NodeInterface $node): ?int {
    $templates = $this->config->get('email_templates') ?? [];

    if (empty($templates)) {
      return NULL;
    }

    if (!$node->hasField('field_expiration_date')) {
      return NULL;
    }

    $expiration_value = $node->get('field_expiration_date')->value;
    if (empty($expiration_value)) {
      return NULL;
    }

    // Normalize templates by offset.
    $templates_by_offset = [];
    foreach ($templates as $template) {
      if (!isset($template['offset'])) {
        continue;
      }
      $templates_by_offset[(int) $template['offset']] = $template;
    }

    if (empty($templates_by_offset)) {
      return NULL;
    }

    $today = new DrupalDateTime('today');

    $expiration = new DrupalDateTime($expiration_value);
    $expiration->setTime(0, 0, 0);

    $days_difference = (int) $today
      ->diff($expiration)
      ->format('%r%a');

    return $templates_by_offset[$days_difference]['offset'] ?? NULL;
  }

}
