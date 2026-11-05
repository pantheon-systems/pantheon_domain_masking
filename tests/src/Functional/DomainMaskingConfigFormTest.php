<?php

namespace Drupal\Tests\pantheon_domain_masking\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain masking configuration form.
 *
 * @group pantheon_domain_masking
 */
#[Group('pantheon_domain_masking')]
#[RunTestsInSeparateProcesses]
class DomainMaskingConfigFormTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['pantheon_domain_masking'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Tests access, saving, and host validation.
   */
  public function testConfigForm(): void {
    $this->drupalGet('admin/config/pantheon-domain-masking/options');
    $this->assertSession()->statusCodeEquals(403);

    $this->drupalLogin($this->drupalCreateUser(['administer site configuration']));
    $this->drupalGet('admin/config/pantheon-domain-masking/options');
    $this->assertSession()->statusCodeEquals(200);

    $this->submitForm([
      'enabled' => 'no',
      'domain' => 'https://www.example.com:8080/ignored',
      'subpath' => 'blog',
      'allow_platform' => 'yes',
    ], 'Save configuration');
    $this->assertSession()->pageTextContains('The configuration options have been saved.');

    $config = $this->config('pantheon_domain_masking.settings');
    $this->assertSame('no', $config->get('enabled'));
    $this->assertSame('www.example.com:8080', $config->get('domain'));
    $this->assertSame('blog', $config->get('subpath'));
    $this->assertSame('yes', $config->get('allow_platform'));

    $this->submitForm(['domain' => ''], 'Save configuration');
    $this->assertSession()->pageTextContains('Invalid domain specified.');
  }

}
