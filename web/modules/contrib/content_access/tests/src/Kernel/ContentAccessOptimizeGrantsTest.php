<?php

namespace Drupal\Tests\content_access\Kernel;

use Drupal\KernelTests\KernelTestBase;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\Entity\Role;

/**
 * Tests optimization behavior for content access grants.
 *
 * @group content_access
 */
class ContentAccessOptimizeGrantsTest extends KernelTestBase {

  use UserCreationTrait;

  /**
   * {@inheritdoc}
   */
  protected static $modules = [
    'field',
    'system',
    'user',
    'text',
    'node',
    'content_access',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The test node used to calculate grant defaults.
   *
   * @var \Drupal\node\Entity\Node
   */
  protected Node $node;

  /**
   * {@inheritdoc}
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installSchema('node', ['node_access']);
    $this->installConfig(['field']);
    $this->installConfig(['system', 'user', 'node', 'content_access']);

    $node_type = NodeType::create([
      'type' => 'optimize_test',
      'name' => 'Optimize test',
    ]);
    $node_type->save();

    Role::create([
      'id' => 'editor',
      'label' => 'Editor',
    ])->save();

    \Drupal::configFactory()->getEditable('content_access.settings')
      ->set('content_access_roles_gids', [
        AccountInterface::ANONYMOUS_ROLE => 1,
        AccountInterface::AUTHENTICATED_ROLE => 2,
        'editor' => 3,
      ])
      ->save();

    $owner = $this->createUser();

    $this->node = Node::create([
      'type' => 'optimize_test',
      'title' => 'Optimization node',
      'uid' => $owner->id(),
      'status' => 1,
    ]);
    $this->node->save();
  }

  /**
   * Ensures anonymous + authenticated view grants collapse to realm all.
   */
  public function testOptimizeCreatesAllRealmForPublicViewAccess(): void {
    $anonymous_gid = content_access_get_role_gid(AccountInterface::ANONYMOUS_ROLE);
    $authenticated_gid = content_access_get_role_gid(AccountInterface::AUTHENTICATED_ROLE);

    $grants = [
      content_access_process_grant(['grant_view' => 1], $anonymous_gid, $this->node),
      content_access_process_grant(['grant_view' => 1], $authenticated_gid, $this->node),
    ];

    content_access_optimize_grants($grants, $this->node);

    $this->assertCount(1, $grants);
    $this->assertArrayHasKey('all', $grants);
    $this->assertSame('all', $grants['all']['realm']);
    $this->assertSame(1, $grants['all']['grant_view']);
    $this->assertSame(0, $grants['all']['grant_update']);
    $this->assertSame(0, $grants['all']['grant_delete']);
  }

  /**
   * Ensures authenticated update grant removes redundant role grants.
   */
  public function testOptimizeRemovesRoleGrantCoveredByAuthenticated(): void {
    $authenticated_gid = content_access_get_role_gid(AccountInterface::AUTHENTICATED_ROLE);
    $editor_gid = content_access_get_role_gid('editor');

    foreach (['view', 'update', 'delete'] as $op) {
      $grants = [
        content_access_process_grant(["grant_{$op}" => 1], $authenticated_gid, $this->node),
        content_access_process_grant(["grant_{$op}" => 1], $editor_gid, $this->node),
      ];

      content_access_optimize_grants($grants, $this->node);

      $this->assertCount(1, $grants, "Expected only authenticated grant for {$op}.");
      $optimized = reset($grants);
      $this->assertSame($authenticated_gid, $optimized['gid']);
      foreach (['view', 'update', 'delete'] as $assert_op) {
        $expected = (int) ($assert_op === $op);
        $this->assertSame($expected, $optimized["grant_{$assert_op}"]);
      }
    }
  }

  /**
   * Ensures grants for different operations are not removed.
   */
  public function testOptimizeKeepsGrantForDifferentOperation(): void {
    $authenticated_gid = content_access_get_role_gid(AccountInterface::AUTHENTICATED_ROLE);
    $editor_gid = content_access_get_role_gid('editor');

    foreach (['view', 'update', 'delete'] as $authenticated_op) {
      foreach (['view', 'update', 'delete'] as $editor_op) {
        if ($authenticated_op === $editor_op) {
          continue;
        }

        $grants = [
          content_access_process_grant(["grant_{$authenticated_op}" => 1], $authenticated_gid, $this->node),
          content_access_process_grant(["grant_{$editor_op}" => 1], $editor_gid, $this->node),
        ];

        content_access_optimize_grants($grants, $this->node);

        $this->assertCount(2, $grants, "Expected both grants when ops differ: {$authenticated_op}/{$editor_op}.");
        $grants_by_gid = [];
        foreach ($grants as $grant) {
          $grants_by_gid[$grant['gid']] = $grant;
        }

        $this->assertArrayHasKey($authenticated_gid, $grants_by_gid);
        $this->assertArrayHasKey($editor_gid, $grants_by_gid);
        $this->assertSame(1, $grants_by_gid[$authenticated_gid]["grant_{$authenticated_op}"]);
        $this->assertSame(1, $grants_by_gid[$editor_gid]["grant_{$editor_op}"]);
      }
    }
  }

}
