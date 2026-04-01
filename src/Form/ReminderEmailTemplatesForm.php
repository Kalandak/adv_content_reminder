<?php

namespace Drupal\adv_content_reminder\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;

/**
 * Email template configuration form.
 */
class ReminderEmailTemplatesForm extends ConfigFormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'adv_content_reminder_email_templates_form';
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

    $form['#tree'] = TRUE;

    $config = $this->config('adv_content_reminder.settings');
    $templates = $config->get('email_templates') ?? [];

    // Use updated templates from form state (after remove).
    $templates = $form_state->get('templates') ?? $templates;

    // Determine count.
    $count = $form_state->get('template_count');
    if ($count === NULL) {
      $count = max(count($templates), 1);
      $form_state->set('template_count', $count);
    }

    // Wrapper for AJAX.
    $form['templates_wrapper'] = [
      '#type' => 'container',
      '#attributes' => ['id' => 'templates-wrapper'],
    ];

    for ($i = 0; $i < $count; $i++) {

      $template = $templates[$i] ?? [];

      $form['templates_wrapper']['templates'][$i] = [
        '#type' => 'details',
        '#title' => $this->t('Template @num', ['@num' => $i + 1]),
        '#open' => TRUE,
      ];

      $form['templates_wrapper']['templates'][$i]['offset'] = [
        '#type' => 'number',
        '#title' => $this->t('Days relative to expiration'),
        '#description' => $this->t('Use negative values for before expiration (e.g. -30), 0 for same day.'),
        '#default_value' => $template['offset'] ?? '',
        '#required' => TRUE,
      ];

      $form['templates_wrapper']['templates'][$i]['subject'] = [
        '#type' => 'textfield',
        '#title' => $this->t('Email Subject'),
        '#default_value' => $template['subject'] ?? '',
        '#required' => TRUE,
      ];

      $form['templates_wrapper']['templates'][$i]['body'] = [
        '#type' => 'text_format',
        '#title' => $this->t('Email Body'),
        '#format' => $template['body']['format'] ?? 'basic_html',
        '#default_value' => $template['body']['value'] ?? '',
        '#required' => TRUE,
      ];

      // Remove button.
      $form['templates_wrapper']['templates'][$i]['remove'] = [
        '#type' => 'submit',
        '#value' => $this->t('Remove'),
        '#name' => 'remove_template_' . $i,
        '#submit' => ['::removeOne'],
        '#ajax' => [
          'callback' => '::addMoreCallback',
          'wrapper' => 'templates-wrapper',
        ],
        '#limit_validation_errors' => [],
        '#attributes' => [
          'class' => ['button--danger'],
        ],
        '#template_index' => $i,
      ];
    }

    // Add another button.
    $form['templates_wrapper']['add_template'] = [
      '#type' => 'submit',
      '#value' => $this->t('Add another template'),
      '#submit' => ['::addOne'],
      '#ajax' => [
        'callback' => '::addMoreCallback',
        'wrapper' => 'templates-wrapper',
      ],
    ];

    // Additional emails.
    $form['additional_emails'] = [
      '#type' => 'textfield',
      '#title' => $this->t('Additional notification emails'),
      '#description' => $this->t('Enter multiple emails separated by comma. Example: admin@example.com, editor@example.com'),
      '#default_value' => implode(', ', $config->get('additional_emails') ?? []),
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * Add one template.
   */
  public function addOne(array &$form, FormStateInterface $form_state): void {
    $count = $form_state->get('template_count');
    $form_state->set('template_count', $count + 1);
    $form_state->setRebuild(TRUE);
  }

  /**
   * Remove one template.
   */
  public function removeOne(array &$form, FormStateInterface $form_state): void {

    $trigger = $form_state->getTriggeringElement();
    $remove_index = $trigger['#template_index'];

    $templates = $form_state->getValue(['templates_wrapper', 'templates']) ?? [];

    // Prevent removing last template.
    if (count($templates) <= 1) {
      $this->messenger()->addWarning($this->t('At least one template is required.'));
      return;
    }

    // Remove selected template.
    unset($templates[$remove_index]);

    // Reindex.
    $templates = array_values($templates);

    $form_state->setValue(['templates_wrapper', 'templates'], $templates);
    $form_state->set('templates', $templates);
    $form_state->set('template_count', count($templates));

    $form_state->setRebuild(TRUE);
  }

  /**
   * AJAX callback.
   */
  public function addMoreCallback(array &$form, FormStateInterface $form_state): array {
    return $form['templates_wrapper'];
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $templates = $form_state->getValue(['templates_wrapper', 'templates']) ?? [];

    $offsets = [];

    foreach ($templates as $index => $template) {
      $offset = $template['offset'];

      if (in_array($offset, $offsets, TRUE)) {
        $form_state->setErrorByName(
          "templates_wrapper][templates][$index][offset",
          $this->t('Each template must have a unique offset value.')
        );
      }

      $offsets[] = $offset;
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * Save configuration.
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $config = $this->configFactory->getEditable('adv_content_reminder.settings');

    $values = $form_state->getValue(['templates_wrapper', 'templates']) ?? [];

    $email_templates = [];

    foreach ($values as $template) {
      $body = $template['body'];

      $email_templates[] = [
        'offset' => (int) $template['offset'],
        'subject' => $template['subject'],
        'body' => [
          'value' => $body['value'] ?? '',
          'format' => $body['format'] ?? 'basic_html',
        ],
      ];
    }

    // Sort by offset.
    usort($email_templates, fn($a, $b) => $a['offset'] <=> $b['offset']);

    $additional_emails = $form_state->getValue('additional_emails');
    $emails = array_filter(array_map('trim', explode(',', $additional_emails)));

    $config
      ->set('email_templates', $email_templates)
      ->set('additional_emails', $emails)
      ->save();

    parent::submitForm($form, $form_state);
  }

}
