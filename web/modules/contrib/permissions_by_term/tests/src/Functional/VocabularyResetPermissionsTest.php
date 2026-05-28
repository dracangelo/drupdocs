<?php

namespace Drupal\Tests\permissions_by_term\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Tests resetting access control when vocabularies are changed.
 *
 * @group permissions_by_term
 */
class VocabularyResetPermissionsTest extends BrowserTestBase {

  use TaxonomyTestTrait;

  /**
   * Modules to enable.
   *
   * @var array
   */
  protected static $modules = ['node', 'user', 'permissions_by_term'];

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * The permissions by term access storage service.
   *
   * @var \Drupal\permissions_by_term\Service\AccessStorage
   */
  protected $accessStorage;

  /**
   * First vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $firstVocab;

  /**
   * Second vocabulary.
   *
   * @var \Drupal\taxonomy\VocabularyInterface
   */
  protected $secondVocab;

  /**
   * First vocabulary term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $firstVocabTerm;

  /**
   * Second vocabulary term.
   *
   * @var \Drupal\taxonomy\TermInterface
   */
  protected $secondVocabTerm;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->accessStorage = $this->container->get('permissions_by_term.access_storage');

    $adminUser = $this->drupalCreateUser(['administer site configuration', 'access pbt settings']);
    $this->drupalLogin($adminUser);

    $this->firstVocab = $this->createVocabulary();
    $this->secondVocab = $this->createVocabulary();

    $this->firstVocabTerm = $this->createTerm($this->firstVocab);
    $this->secondVocabTerm = $this->createTerm($this->secondVocab);

    $this->accessStorage->addTermPermissionsByUserIds([$this->loggedInUser->id()], $this->firstVocabTerm->id(), 'en');
    $this->accessStorage->addTermPermissionsByUserIds([$this->loggedInUser->id()], $this->secondVocabTerm->id(), 'en');
  }

  /**
   * Tests that resetting access control for one vocabulary only removes
   * permissions for that vocabulary, while keeping permissions for others.
   */
  public function testResetAccessControlForOneVocab(): void {
    $this->config('permissions_by_term.settings')
      ->set('target_bundles', [
        $this->firstVocab->id(),
        $this->secondVocab->id(),
      ])
      ->save();

    $this->drupalGet('/admin/permissions-by-term/settings');

    $this->submitForm([
      "target_bundles[{$this->firstVocab->id()}]" => TRUE,
      "target_bundles[{$this->secondVocab->id()}]" => FALSE,
    ], 'Save configuration');

    // Check that permissions for the first vocabulary remain.
    $first_vocab_term_permission = $this->accessStorage->getUserTermPermissionsByTid($this->firstVocabTerm->id(), 'en');
    $this->assertIsArray($first_vocab_term_permission, 'Permissions for the first vocabulary term should remain.');

    // Check that permissions for the second vocabulary were removed.
    $second_vocab_term_permission = $this->accessStorage->getUserTermPermissionsByTid($this->secondVocabTerm->id(), 'en');
    $this->assertEmpty($second_vocab_term_permission, 'Permissions for the second vocabulary term should be removed.');

    $this->assertSession()->pageTextContains("Term permissions have been deleted for the following vocabularies: {$this->secondVocab->label()}.");
  }

  /**
   * Tests that unchecking all vocabularies does not remove permissions.
   */
  public function testUncheckAllVocab() {
    $this->config('permissions_by_term.settings')
      ->set('target_bundles', [
        $this->firstVocab->id(),
        $this->secondVocab->id(),
      ])
      ->save();

    $this->drupalGet('/admin/permissions-by-term/settings');

    $this->submitForm([
      "target_bundles[{$this->firstVocab->id()}]" => FALSE,
      "target_bundles[{$this->secondVocab->id()}]" => FALSE,
    ], 'Save configuration');

    $first_vocab_term_permission = $this->accessStorage->getUserTermPermissionsByTid($this->firstVocabTerm->id(), 'en');
    $this->assertIsArray($first_vocab_term_permission, 'Permissions for the first vocabulary term should remain.');

    $second_vocab_term_permission = $this->accessStorage->getUserTermPermissionsByTid($this->secondVocabTerm->id(), 'en');
    $this->assertIsArray($second_vocab_term_permission, 'Permissions for the second vocabulary term should be removed.');

    $this->assertSession()->pageTextNotContains("Term permissions have been deleted for the following vocabularies");
  }

  /**
   * Tests that enabling only one vocabulary keeps only its permissions.
   */
  public function testKeepOnlyOneVocab() {
    $this->config('permissions_by_term.settings')
      ->set('target_bundles', [])
      ->save();

    $this->drupalGet('/admin/permissions-by-term/settings');

    $this->submitForm([
      "target_bundles[{$this->firstVocab->id()}]" => TRUE,
    ], 'Save configuration');

    $first_vocab_term_permission = $this->accessStorage->getUserTermPermissionsByTid($this->firstVocabTerm->id(), 'en');
    $this->assertIsArray($first_vocab_term_permission, 'Permissions for the first vocabulary term should remain.');

    $second_vocab_term_permission = $this->accessStorage->getUserTermPermissionsByTid($this->secondVocabTerm->id(), 'en');
    $this->assertEmpty($second_vocab_term_permission, 'Permissions for the second vocabulary term should be removed.');

    $this->assertSession()->pageTextContains("Term permissions have been deleted for the following vocabularies: {$this->secondVocab->label()}.");
  }

}
