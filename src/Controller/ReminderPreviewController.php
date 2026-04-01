<?php

namespace Drupal\adv_content_reminder\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RendererInterface;
use Drupal\token\Token;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Controller for previewing and sending test reminder emails.
 */
class ReminderPreviewController extends ControllerBase {

  /**
   * Mail manager.
   */
  protected MailManagerInterface $mailManager;

  /**
   * Token service.
   */
  protected Token $token;

  /**
   * Renderer service.
   */
  protected RendererInterface $renderer;

  /**
   * Constructor.
   */
  public function __construct(
    MailManagerInterface $mail_manager,
    Token $token,
    RendererInterface $renderer,
  ) {
    $this->mailManager = $mail_manager;
    $this->token = $token;
    $this->renderer = $renderer;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('plugin.manager.mail'),
      $container->get('token'),
      $container->get('renderer')
    );
  }

  /**
   * AJAX preview endpoint.
   *
   * Renders subject + body with token replacement.
   */
  public function preview(Request $request) {

    $subject = $request->request->get('subject');
    $body = $request->request->get('body');

    if (empty($subject) && empty($body)) {
      return 'Nothing to preview.';
    }

    // Use current user context for safe token rendering.
    $data = [
      'user' => $this->currentUser(),
    ];

    $subject_rendered = $this->token->replace($subject, $data);
    $body_rendered = $this->token->replace($body, $data);

    $build = [
      '#type' => 'container',
      '#attributes' => ['class' => ['email-preview']],
      'subject_label' => [
        '#markup' => '<h3>Subject</h3>',
      ],
      'subject' => [
        '#markup' => '<p><strong>' . $subject_rendered . '</strong></p>',
      ],
      'body_label' => [
        '#markup' => '<h3>Body</h3>',
      ],
      'body' => [
        '#markup' => $body_rendered,
      ],
    ];

    return $this->renderer->render($build);
  }

  /**
   * AJAX test email sender.
   */
  public function sendTest(Request $request) {

    $email = $request->request->get('email');
    $stage = $request->request->get('stage');

    if (empty($email)) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Email address is required.',
      ]);
    }

    // Use ControllerBase config helper (avoid property conflict).
    $config = $this->config('adv_content_reminder.settings');
    $templates = $config->get('email_templates') ?? [];

    if (empty($templates[$stage])) {
      return new JsonResponse([
        'status' => 'error',
        'message' => 'Invalid reminder stage selected.',
      ]);
    }

    $subject_template = $templates[$stage]['subject'] ?? '';
    $body_template = $templates[$stage]['body']['value'] ?? '';

    $data = [
      'user' => $this->currentUser(),
    ];

    $subject = $this->token->replace($subject_template, $data);
    $body = $this->token->replace($body_template, $data);

    // Use ControllerBase language manager helper.
    $langcode = $this->languageManager()
      ->getCurrentLanguage()
      ->getId();

    $result = $this->mailManager->mail(
      'adv_content_reminder',
      'test_email',
      $email,
      $langcode,
      [
        'subject' => $subject,
        'body' => $body,
      ]
    );

    if (empty($result['result'])) {

      // Use ControllerBase logger helper.
      $this->logger('adv_content_reminder')
        ->error('Failed to send test reminder email to @email.', [
          '@email' => $email,
        ]);

      return new JsonResponse([
        'status' => 'error',
        'message' => 'Failed to send test email.',
      ]);
    }

    $this->logger('adv_content_reminder')
      ->info('Test reminder email sent to @email (stage: @stage).', [
        '@email' => $email,
        '@stage' => $stage,
      ]);

    return new JsonResponse([
      'status' => 'success',
      'message' => 'Test email sent successfully.',
    ]);
  }

}
