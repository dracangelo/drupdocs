<?php

namespace Drupal\Tests\permissions_by_term\Functional;

use Drupal\Tests\BrowserTestBase;
use Drupal\Tests\taxonomy\Functional\TaxonomyTranslationTestTrait;
use Drupal\Tests\taxonomy\Traits\TaxonomyTestTrait;

/**
 * Functional tests for the Permissions by Term module.
 *
 * Tests batch processing triggered by term edits and user edits.
 *
 * @group permissions_by_term
 */
class NodeBatchTest extends BrowserTestBase {

  use TaxonomyTestTrait;
  use TaxonomyTranslationTestTrait;

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
   * The term.
   *
   * @var \Drupal\taxonomy\Entity\Term
   */
  protected $term;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();
    $this->vocabulary = $this->createVocabulary();
    $this->term = $this->createTerm($this->vocabulary);

    $this->drupalCreateContentType([
      'type' => 'article',
      'name' => 'Article',
    ]);
    $this->setUpTermReferenceField();

    $this->config('permissions_by_term.settings')
      ->set('disable_node_access_records', FALSE)
      ->save();
  }

  /**
   * Tests batch execution when editing a term.
   *
   * @dataProvider batchArgsProvider
   *
   * @param int $node_qty
   *   Number of nodes to create.
   * @param bool $batch_should_start
   *   Whether a batch is expected to execute.
   */
  public function testBatchOnTermEditForm(int $node_qty, bool $batch_should_start) {
    $admin = $this->drupalCreateUser(['administer site configuration', 'administer taxonomy', 'show term permission form on term page']);
    $this->drupalLogin($admin);

    $this->createNodesWithTag($node_qty);

    $this->drupalGet("/taxonomy/term/{$this->term->id()}/edit");

    $this->submitForm([
      'access[role][authenticated]' => TRUE,
    ], 'Save');

    $this->assertBatch($batch_should_start);
  }

  /**
   * Tests batch execution when editing a user.
   *
   * @dataProvider batchArgsProvider
   *
   * @param int $node_qty
   *   Number of nodes to create.
   * @param bool $batch_should_start
   *   Whether a batch is expected to execute.
   */
  public function testBatchOnUserEditForm(int $node_qty, bool $batch_should_start) {
    $admin = $this->drupalCreateUser(['administer site configuration', 'show term permissions on user edit page']);
    $this->drupalLogin($admin);

    $this->createNodesWithTag($node_qty);

    $current_user_id = $this->loggedInUser->id();
    $this->drupalGet("user/{$current_user_id}/edit");

    $this->submitForm([
      'terms[' . $this->vocabulary->id() . '][]' => [$this->term->id()],
    ], 'Save');

    $this->assertBatch($batch_should_start);
  }

  /**
   * Provides arguments for batch tests.
   *
   * @return array
   *   Each item is an array with the node quantity and whether the batch
   *   should be executed.
   */
  public static function batchArgsProvider() {
    return [
      [50, TRUE],
      [5, FALSE],
    ];
  }

  /**
   * Creates multiple nodes tagged with the test term.
   *
   * @param int $qty
   *   Number of nodes to create.
   */
  protected function createNodesWithTag(int $qty): void {
    for ($i = 0; $i <= $qty; $i++) {
      $node = $this->drupalCreateNode([
        'type' => 'article',
        'field_tag' => $this->term->id(),
      ]);
      $node->save();
    }
  }

  /**
   * Asserts whether a batch process was executed.
   *
   * @param bool $batch_executed
   *   TRUE if batch should have run, FALSE otherwise.
   */
  protected function assertBatch(bool $batch_executed): void {
    $history = $this->getSession()->getDriver()->getClient()->getHistory();

    $previousRequest = $history->back();
    $previous_uri = $previousRequest->getUri();

    $batch_pattern = '/batch?id=1&op=finished';

    if ($batch_executed) {
      $this->assertStringContainsString($batch_pattern, $previous_uri);
    }
    else {
      $this->assertStringNotContainsString($batch_pattern, $previous_uri);
    }
  }

}
