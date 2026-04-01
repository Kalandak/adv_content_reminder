<?php

namespace Drupal\adv_content_reminder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Configuration form for selecting monitored content types.
 */
class ReminderContentTypesForm extends ConfigFormBase {

  /**
   * Bundle info service.
   */
  protected EntityTypeBundleInfoInterface $bundleInfo;

  public function __construct(EntityTypeBundleInfoInterface $bundle_info) {
    $this->bundleInfo = $bundle_info;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.bundle.info')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'adv_content_reminder_content_types_form';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return ['adv_content_reminder.settings'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->config('adv_content_reminder.settings');
    $selected_types = $config->get('monitored_content_types') ?? [];

    $bundles = $this->bundleInfo->getBundleInfo('node');

    $options = [];
    foreach ($bundles as $machine_name => $bundle) {
      $options[$machine_name] = $bundle['label'];
    }

    $form['description'] = [
      '#markup' => '
        <div class="messages messages--status">
        <h3>Advanced Content Reminder</h3>
        <p>This module sends automated email notifications based on a node’s expiration date.</p>
        <p><strong>Note:</strong></p>
        <ul>
          <li>The Advanced Content Reminder Expiration module must be installed & set up.</li>
          <li>This module only works if <code>field_expiration</code> is present on the node type.</li>
        </ul>
        </div>
      ',
    ];

    $form['monitored_content_types'] = [
      '#type' => 'checkboxes',
      '#title' => $this->t('Monitored Content Types'),
      '#options' => $options,
      '#default_value' => $selected_types,
      '#required' => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $values = array_filter($form_state->getValue('monitored_content_types'));

    if (empty($values)) {
      $form_state->setErrorByName(
        'monitored_content_types',
        $this->t('You must select at least one content type.')
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $selected = array_filter($form_state->getValue('monitored_content_types'));

    $this->configFactory
      ->getEditable('adv_content_reminder.settings')
      ->set('monitored_content_types', array_values($selected))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
