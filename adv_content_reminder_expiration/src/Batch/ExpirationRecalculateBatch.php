<?php

namespace Drupal\adv_content_reminder_expiration\Batch;

use Drupal\node\Entity\Node;

/**
 * Batch operations for recalculating expiration dates.
 */
class ExpirationRecalculateBatch {

  /**
   * Batch process callback.
   *
   * @param array $context
   *   Batch context.
   */
  public static function process(&$context) {

    if (!isset($context['sandbox']['progress'])) {
      $context['sandbox']['progress'] = 0;

      /** @var \Drupal\adv_content_reminder_expiration\Service\ExpirationManager $manager */
      $manager = \Drupal::service('adv_content_reminder_expiration.expiration_manager');

      $nids = $manager->getNodesWithExpirationField();

      $context['sandbox']['nids'] = array_values($nids);
      $context['sandbox']['max'] = count($nids);
    }

    $batch_size = 50;

    $manager = \Drupal::service('adv_content_reminder_expiration.expiration_manager');

    $nids = $context['sandbox']['nids'];
    $max = $context['sandbox']['max'];

    $current_nids = array_slice(
      $nids,
      $context['sandbox']['progress'],
      $batch_size
    );

    foreach ($current_nids as $nid) {
      if ($node = Node::load($nid)) {
        $manager->recalculateNode($node);
      }

      $context['sandbox']['progress']++;
    }

    if ($context['sandbox']['progress'] < $max) {
      $context['finished'] =
        $context['sandbox']['progress'] / $max;
    }
    else {
      $context['finished'] = 1;
    }

    $context['message'] = t(
      'Processed @current of @total nodes.',
      [
        '@current' => $context['sandbox']['progress'],
        '@total' => $max,
      ]
    );
  }

  /**
   * Batch finished callback.
   *
   * @param bool $success
   *   Whether the batch completed successfully.
   * @param array $results
   *   Results array.
   * @param array $operations
   *   Remaining operations.
   */
  public static function finished($success, $results, $operations) {

    if ($success) {
      \Drupal::messenger()->addStatus(
        t('Expiration dates have been successfully recalculated.')
      );

      \Drupal::logger('adv_content_reminder_expiration')
        ->notice('Batch expiration recalculation completed successfully.');
    }
    else {
      \Drupal::messenger()->addError(
        t('An error occurred during expiration recalculation.')
      );

      \Drupal::logger('adv_content_reminder_expiration')
        ->error('Batch expiration recalculation encountered an error.');
    }
  }

}
