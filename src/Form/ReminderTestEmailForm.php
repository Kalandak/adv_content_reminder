<?php

namespace Drupal\adv_content_reminder\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\adv_content_reminder\Service\ReminderManager;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Test email form for Advanced Content Reminder.
 */
class ReminderTestEmailForm extends FormBase {

  /**
   * The reminder manager service.
   *
   * @var \Drupal\adv_content_reminder\Service\ReminderManager
   */
  protected ReminderManager $reminderManager;

  public function __construct(ReminderManager $reminder_manager) {
    $this->reminderManager = $reminder_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('adv_content_reminder.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'adv_content_reminder_test_email_form';
  }

  /**
   * {@inheritdoc}
   *
   * Enforce permission access.
   */
  public function access($account = NULL): bool {
    $account = $account ?: $this->currentUser();
    return $account->hasPermission('send test reminder emails');
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {

    $config = $this->reminderManager->getConfig();
    $templates = $config->get('email_templates') ?? [];

    $options = [];

    foreach ($templates as $template) {
      if (!isset($template['offset'])) {
        continue;
      }

      $offset = (int) $template['offset'];

      // Build human-readable label.
      if ($offset < 0) {
        $label = $this->t('@days days before expiration', [
          '@days' => abs($offset),
        ]);
      }
      elseif ($offset === 0) {
        $label = $this->t('Day of expiration');
      }
      else {
        $label = $this->t('@days days after expiration', [
          '@days' => $offset,
        ]);
      }

      $options[$offset] = $label;
    }

    // Sort options by offset.
    ksort($options);

    $form['email'] = [
      '#type' => 'email',
      '#title' => $this->t('Test Email Address'),
      '#required' => TRUE,
    ];

    $form['offset'] = [
      '#type' => 'select',
      '#title' => $this->t('Reminder Timing'),
      '#options' => $options,
      '#empty_option' => $this->t('- Select -'),
      '#required' => TRUE,
    ];

    if (empty($options)) {
      $this->messenger()->addWarning($this->t('No email templates configured. Please add templates first.'));
    }

    $form['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Send Test Email'),
    ];

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {

    $offset = $form_state->getValue('offset');

    if (!is_numeric($offset)) {
      $form_state->setErrorByName('offset', $this->t('Invalid reminder timing selected.'));
    }

    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {

    $email = $form_state->getValue('email');
    $offset = (int) $form_state->getValue('offset');

    $this->reminderManager->sendTestEmail($email, $offset);

    $this->messenger()->addStatus($this->t('Test email sent to @email.', [
      '@email' => $email,
    ]));
  }

}
