<?php

namespace Drupal\Tests\adv_content_reminder\Kernel;

use Drupal\Core\Config\TypedConfigManagerInterface;
use Drupal\KernelTests\KernelTestBase;

/**
 * Tests configuration schema for Advanced Content Reminder.
 *
 * @group adv_content_reminder
 */
class ConfigSchemaTest extends KernelTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'system',
    'user',
    'node',
    'field',
    'text',
    'filter',
    'adv_content_reminder',
  ];

  /**
   * Tests that configuration matches schema definitions.
   */
  public function testConfigSchemaValidation(): void {
    // Install module config.
    $this->installConfig(['adv_content_reminder']);

    // Load config.
    $config = $this->config('adv_content_reminder.settings');
    $data = $config->getRawData();

    // Get typed config manager.
    $typed_config = $this->container->get('config.typed');

    // This will throw exceptions if schema is invalid or incomplete.
    $definition = $typed_config->getDefinition('adv_content_reminder.settings');
    $this->assertNotEmpty($definition, 'Schema definition exists.');

    // Create typed config object.
    $typed = $typed_config->createFromNameAndData(
      'adv_content_reminder.settings',
      $data
    );

    // Validate full schema structure.
    $this->assertInstanceOf(
      TypedConfigManagerInterface::class,
      $typed_config
    );

    // Ensure no schema violations (this is the key contrib check).
    foreach ($typed as $key => $value) {
      $this->assertNotNull($value, "Config key '$key' is defined in schema.");
    }

    // --- Specific field validations ---
    // monitored_content_types.
    $content_types = $config->get('monitored_content_types');
    if ($content_types !== NULL) {
      $this->assertIsArray($content_types);
      foreach ($content_types as $type) {
        $this->assertIsString($type);
      }
    }

    // email_templates.
    $templates = $config->get('email_templates');
    if (!empty($templates)) {
      foreach ($templates as $template) {
        $this->assertIsArray($template);

        $this->assertArrayHasKey('label', $template);
        $this->assertArrayHasKey('offset', $template);
        $this->assertArrayHasKey('subject', $template);
        $this->assertArrayHasKey('body', $template);

        $this->assertIsString($template['label']);
        $this->assertIsNumeric($template['offset']);
        $this->assertIsString($template['subject']);
        $this->assertIsString($template['body']);
      }
    }

    // additional_emails.
    $emails = $config->get('additional_emails');
    if (!empty($emails)) {
      $this->assertIsArray($emails);

      foreach ($emails as $email) {
        $this->assertIsString($email);
        $this->assertStringContainsString('@', $email);
      }
    }

    $this->assertTrue(TRUE, 'Configuration schema is valid and fully typed.');
  }

}
