<?php

namespace Drupal\adv_content_reminder_expiration\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Logger\LoggerChannelFactoryInterface;
use Drupal\node\NodeInterface;
use Drupal\Core\Database\Database;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;

/**
 * Handles expiration date calculation and recalculation.
 */
class ExpirationManager {

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  protected ConfigFactoryInterface $configFactory;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected EntityFieldManagerInterface $entityFieldManager;

  /**
   * The logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * Constructor.
   */
  public function __construct(
    EntityTypeManagerInterface $entity_type_manager,
    EntityFieldManagerInterface $entity_field_manager,
    ConfigFactoryInterface $config_factory,
    LoggerChannelFactoryInterface $logger_factory,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
  ) {
    $this->entityTypeManager = $entity_type_manager;
    $this->entityFieldManager = $entity_field_manager;
    $this->configFactory = $config_factory;

    // Properly initialize logger channel.
    $this->logger = $logger_factory->get('adv_content_reminder_expiration');
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  /**
   * Calculate expiration date for a node.
   *
   * Policy: Expiration = Updated (changed) date + configured interval.
   */
  public function calculateExpirationDate(NodeInterface $node): ?string {

    if (!$node->hasField('field_expiration_date')) {
      return NULL;
    }

    $config = $this->configFactory
      ->get('adv_content_reminder_expiration.settings');

    $interval_value = (int) ($config->get('interval_value') ?? 1);
    $interval_unit = $config->get('interval_unit') ?? 'year';

    $changed_timestamp = $node->getChangedTime();

    if (!$changed_timestamp) {
      return NULL;
    }

    // Use immutable datetime for predictable behavior.
    $date = (new \DateTimeImmutable())
      ->setTimestamp($changed_timestamp)
      ->setTime(0, 0);

    // Apply interval safely.
    $interval_spec = "P{$interval_value}" . strtoupper(substr($interval_unit, 0, 1));
    $date = $date->add(new \DateInterval($interval_spec));

    return $date->format('Y-m-d');
  }

  /**
   * Recalculate expiration for a single node.
   */
  public function recalculateNode(NodeInterface $node): void {

    // Ensure the field exists on the node.
    if (!$node->hasField('field_expiration_date')) {
      return;
    }

    // Calculate new expiration date.
    $new_expiration = $this->calculateExpirationDate($node);

    if (!$new_expiration) {
      return;
    }

    $connection = Database::getConnection();

    $nid = $node->id();
    $vid = $node->getRevisionId();
    $bundle = $node->bundle();
    $langcode = $node->language()->getId();

    $field_table = 'node__field_expiration_date';
    $revision_table = 'node_revision__field_expiration_date';

    $connection->merge($field_table)
      ->key('entity_id', $nid)
      ->key('langcode', $langcode)
      ->key('delta', 0)
      ->fields([
        'bundle' => $bundle,
        'deleted' => 0,
        'revision_id' => $vid,
        'field_expiration_date_value' => $new_expiration,
      ])
      ->execute();

    if ($connection->schema()->tableExists($revision_table)) {

      $connection->merge($revision_table)
        ->key('entity_id', $nid)
        ->key('revision_id', $vid)
        ->key('langcode', $langcode)
        ->key('delta', 0)
        ->fields([
          'bundle' => $bundle,
          'deleted' => 0,
          'field_expiration_date_value' => $new_expiration,
        ])
        ->execute();
    }

    $this->entityTypeManager
      ->getStorage('node')
      ->resetCache([$nid]);

    $this->cacheTagsInvalidator->invalidateTags(['node:' . $nid]);

    $this->logger->debug(
    'Recalculated expiration for node @nid. New date: @date',
    [
      '@nid' => $nid,
      '@date' => $new_expiration,
    ]
    );
  }

  /**
   * Get node IDs that have expiration field enabled.
   *
   * Only returns nodes from bundles where field_expiration_date exists.
   */
  public function getNodesWithExpirationField(): array {

    $node_storage = $this->entityTypeManager->getStorage('node');

    $node_types = $this->entityTypeManager
      ->getStorage('node_type')
      ->loadMultiple();

    $bundles_with_field = [];

    foreach ($node_types as $bundle => $type) {

      $field_definitions = $this->entityFieldManager
        ->getFieldDefinitions('node', $bundle);

      if (isset($field_definitions['field_expiration_date'])) {
        $bundles_with_field[] = $bundle;
      }
    }

    if (empty($bundles_with_field)) {
      return [];
    }

    return $node_storage->getQuery()
      ->accessCheck(FALSE)
      ->condition('type', $bundles_with_field, 'IN')
      ->execute();
  }

}
