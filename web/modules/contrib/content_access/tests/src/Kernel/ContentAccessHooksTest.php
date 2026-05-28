<?php

namespace Drupal\Tests\content_access\Kernel;

use Drupal\content_access\Access\ContentAccessNodePageAccessCheck;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\KernelTests\KernelTestBase;
use Drupal\node\Entity\Node;
use Drupal\node\Entity\NodeType;
use Drupal\node\NodeInterface;
use Drupal\Tests\user\Traits\UserCreationTrait;
use Drupal\user\UserInterface;

/**
 * Tests that content access hooks are triggered and working as expected.
 *
 * @group content_access
 */
class ContentAccessHooksTest extends KernelTestBase {

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
    'content_access_hooks_test',
  ];

  /**
   * {@inheritdoc}
   */
  protected $strictConfigSchema = FALSE;

  /**
   * The content access node page access check service.
   *
   * @var \Drupal\content_access\Access\ContentAccessNodePageAccessCheck
   */
  protected ContentAccessNodePageAccessCheck $accessCheck;

  /**
   * Test user with content access permissions.
   *
   * @var \Drupal\user\UserInterface
   */
  protected UserInterface $testUser;

  /**
   * Test node.
   *
   * @var \Drupal\node\NodeInterface
   */
  protected NodeInterface $testNode;

  /**
   * {@inheritdoc}
   *
   * @throws \Drupal\Core\Entity\EntityStorageException
   */
  protected function setUp(): void {
    parent::setUp();

    $this->installEntitySchema('user');
    $this->installEntitySchema('node');
    $this->installConfig(['field']);
    $this->installSchema('node', ['node_access']);
    $this->installSchema('content_access', ['content_access']);
    $this->installConfig(['system', 'user', 'node', 'content_access']);

    $this->accessCheck = $this->container->get('access_check.content_access_node_page_access');

    $node_type = NodeType::create([
      'type' => 'test_content',
      'name' => 'Test Content',
    ]);
    // Enable per-node access for the test content type.
    $node_type->setThirdPartySetting('content_access', 'per_node', 1);
    $node_type->save();

    $this->testUser = $this->createUser([
      'grant content access',
      'access content',
    ]);

    $this->testNode = Node::create([
      'type' => 'test_content',
      'title' => 'Test Node',
      'uid' => $this->testUser->id(),
      'status' => 1,
    ]);
    $this->testNode->save();
  }

  /**
   * Tests that hook_content_access_node_page is invoked correctly.
   *
   * @dataProvider hookContentAccessNodePageOptions
   */
  public function testHookContentAccessNodePageInvocation($hook_return, $expected_result): void {
    // Reset the test state.
    $state = $this->container->get('state');
    $state->set('content_access_test_hook_invoked', FALSE);

    // Create a mock route match to set the created node as param.
    $route_match = $this->createMock(RouteMatchInterface::class);
    $route_match->method('getParameter')
      ->with('node')
      ->willReturn($this->testNode);
    $state->set('content_access_test_hook_return', $hook_return);
    $result = $this->accessCheck->access($this->testUser, $route_match);
    // Verify the hook was invoked correctly.
    $this->assertTrue($state->get('content_access_test_hook_invoked'));
    $this->assertEquals($result, AccessResult::$expected_result());
  }

  /**
   * The data provider for potential hook returns.
   *
   * @see hook_content_access_node_page()
   *
   * @return array
   *   An array of dialog positions.
   */
  public static function hookContentAccessNodePageOptions(): array {
    return [
      ['allowed', 'allowed'],
      ['forbidden', 'forbidden'],
      // Allowed because the test user has permissions.
      ['neutral', 'allowed'],
    ];
  }

}
