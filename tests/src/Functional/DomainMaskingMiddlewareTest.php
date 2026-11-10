<?php

namespace Drupal\Tests\pantheon_domain_masking\Functional;

use Drupal\Tests\BrowserTestBase;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;

/**
 * Tests the domain masking middleware.
 *
 * @group pantheon_domain_masking
 */
#[Group('pantheon_domain_masking')]
#[RunTestsInSeparateProcesses]
class DomainMaskingMiddlewareTest extends BrowserTestBase {

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['pantheon_domain_masking'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * Header marking a request as coming through the masked domain.
   */
  protected const CDN_HEADER = ['adv-cdn-origin' => '1'];

  /**
   * Saves the masking settings.
   */
  protected function setMasking(string $enabled, string $allowPlatform, string $subpath = ''): void {
    $this->config('pantheon_domain_masking.settings')
      ->set('enabled', $enabled)
      ->set('domain', 'www.example.com')
      ->set('allow_platform', $allowPlatform)
      ->set('subpath', $subpath)
      ->save();
  }

  /**
   * Asserts the login form posts to $action.
   */
  protected function assertLoginAction(string $action): void {
    $form = $this->assertSession()->elementExists('css', '#user-login-form');
    $this->assertSame($action, $form->getAttribute('action'));
  }

  /**
   * Tests that nothing changes while masking is disabled.
   */
  public function testDisabled(): void {
    $this->setMasking('no', 'no', 'us');
    $this->drupalGet('user/login', [], self::CDN_HEADER);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertLoginAction('/user/login');
    $this->assertStringNotContainsString('adv-cdn-origin', (string) $this->getSession()->getResponseHeader('Vary'));
  }

  /**
   * Tests the Vary header and platform domain handling.
   */
  public function testPlatformDomain(): void {
    $this->setMasking('yes', 'yes', 'us');
    $this->drupalGet('user/login');
    $this->assertSession()->statusCodeEquals(200);
    $this->assertStringContainsString('adv-cdn-origin', (string) $this->getSession()->getResponseHeader('Vary'));
    $this->assertLoginAction('/user/login');

    $this->drupalGet('user/login', [], self::CDN_HEADER);
    $this->assertLoginAction('/us/user/login');

    $this->setMasking('yes', 'no', 'us');
    $this->drupalGet('user/login');
    $this->assertLoginAction('/us/user/login');
  }

  /**
   * Tests a path that starts with the subpath string.
   *
   * @see https://github.com/pantheon-systems/pantheon_domain_masking/issues/43
   */
  public function testSubpathPrefixedPath(): void {
    $this->setMasking('yes', 'no', 'us');
    $this->drupalGet('user/login', [], self::CDN_HEADER);
    $this->assertSession()->statusCodeEquals(200);
    $this->assertLoginAction('/us/user/login');
  }

}
