<?php

namespace Drupal\adv_content_reminder_expiration\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Batch\BatchBuilder;

/**
 * Configuration form for Advanced Content Reminder Expiration settings.
 */
class ExpirationSettingsForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      'adv_content_reminder_expiration.settings',
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'adv_content_reminder_expiration_settings_form';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {

    $config = $this->config('adv_content_reminder_expiration.settings');

    $form['interval'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Expiration interval'),
    ];

    $form['interval']['interval_value'] = [
      '#type' => 'number',
      '#title' => $this->t('Interval value'),
      '#default_value' => $config->get('interval_value') ?? 1,
      '#min' => 1,
      '#required' => TRUE,
      '#description' => $this->t('Amount of time added to the content updated date to calculate expiration.'),
    ];

    $form['interval']['interval_unit'] = [
      '#type' => 'select',
      '#title' => $this->t('Interval unit'),
      '#default_value' => $config->get('interval_unit') ?? 'year',
      '#options' => [
        'year' => $this->t('Year(s)'),
        'month' => $this->t('Month(s)'),
        'day' => $this->t('Day(s)'),
      ],
      '#required' => TRUE,
    ];

    $form['recalculate'] = [
      '#type' => 'fieldset',
      '#title' => $this->t('Recalculate expiration dates'),
    ];

    $form['recalculate']['recalculate_all'] = [
      '#type' => 'submit',
      '#value' => $this->t('Recalculate all nodes'),
      '#submit' => ['::submitRecalculateAll'],
      '#button_type' => 'primary',
      '#description' => $this->t('Recalculate expiration dates for all nodes that have the expiration field enabled.'),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {

    $this->config('adv_content_reminder_expiration.settings')
      ->set('interval_value', $form_state->getValue('interval_value'))
      ->set('interval_unit', $form_state->getValue('interval_unit'))
      ->save();

    parent::submitForm($form, $form_state);
  }

  /**
   * Submit handler for "Recalculate all nodes".
   */
  public function submitRecalculateAll(array &$form, FormStateInterface $form_state) {

    $batch_builder = new BatchBuilder();

    $batch_builder
      ->setTitle($this->t('Recalculating expiration dates'))
      ->setInitMessage($this->t('Starting expiration recalculation...'))
      ->setProgressMessage($this->t('Processed @current out of @total nodes.'))
      ->setErrorMessage($this->t('An error occurred during recalculation.'))
      ->addOperation(
        '\Drupal\adv_content_reminder_expiration\Batch\ExpirationRecalculateBatch::process'
      )
      ->setFinishCallback(
        '\Drupal\adv_content_reminder_expiration\Batch\ExpirationRecalculateBatch::finished'
      );

    batch_set($batch_builder->toArray());
  }

}
