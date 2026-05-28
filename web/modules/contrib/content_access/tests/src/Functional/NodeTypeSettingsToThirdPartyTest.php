<?php

namespace Drupal\Tests\content_access\Functional;

use Drupal\FunctionalTests\Update\UpdatePathTestBase;
use Drupal\node\Entity\NodeType;

/**
 * Tests the content_access_update_9202() update hook.
 *
 * @group content_access
 */
class NodeTypeSettingsToThirdPartyTest extends UpdatePathTestBase {

  /**
   * {@inheritdoc}
   */
  protected $defaultTheme = 'stark';

  /**
   * {@inheritdoc}
   */
  protected static $modules = ['content_access'];

  /**
   * Config schema exclusions for this test.
   *
   * @var string[]
   */
  protected static $configSchemaCheckerExclusions = [
    'content_access.settings',
  ];

  /**
   * {@inheritdoc}
   */
  protected function setDatabaseDumpFiles(): void {
    $this->databaseDumpFiles = [
      DRUPAL_ROOT . '/core/modules/system/tests/fixtures/update/drupal-10.3.0.bare.standard.php.gz',
    ];
  }

  /**
   * Tests content_access_update_9202().
   */
  public function testUpdate9202(): void {
    // Workaround for D11 code running on D10 schema: add 'alias' column early.
    // @todo Remove this workaround once Drupal core ships fixtures for 11.x
    // (e.g. drupal-11.2.0.bare.standard.php.gz or similar) and switch
    // setDatabaseDumpFiles() to use one of them.
    $database = $this->container->get('database');
    $schema = $database->schema();
    include_once $this->root . '/core/modules/system/system.install';
    if (function_exists('system_update_11201') &&
      $schema->tableExists('router') &&
      !$schema->fieldExists('router', 'alias')) {
      system_update_11201();
    }
    // We need this to enable content_access.
    $this->installModulesFromClassProperty($this->container);
    // Set up test data in content_access.settings config.
    $config = $this->config('content_access.settings');
    $test_data = [
      'article' => serialize([
        'view_own' => ['anonymous', 'authenticated'],
        'view' => ['anonymous', 'authenticated'],
        'per_node' => 1,
        'priority' => '5',
      ]),
    ];

    $config->set('content_access_node_type', $test_data)->save();

    // Verify the data exists in config before update.
    $this->assertEquals($test_data, $config->get('content_access_node_type'));

    // Verify node types don't have third party settings yet.
    $article_type = NodeType::load('article');
    $this->assertEmpty($article_type->getThirdPartySettings('content_access'));

    // Set the module's schema version to be lower than 9202 so the update runs.
    $update_registry = \Drupal::service('update.update_hook_registry');
    $update_registry->setInstalledVersion('content_access', 9201);
    $this->runUpdates();

    // Reload config and node type after update.
    $config = $this->config('content_access.settings');
    $article_type = NodeType::load('article');

    // Verify the data was removed from config.
    $this->assertEmpty($config->get('content_access_node_type'));

    // Verify the data was moved to node type third party settings.
    $article_settings = $article_type->getThirdPartySettings('content_access');
    $this->assertNotEmpty($article_settings);
    $this->assertEquals(['anonymous', 'authenticated'], $article_settings['view_own']);
    $this->assertEquals(['anonymous', 'authenticated'], $article_settings['view']);
    $this->assertTrue($article_settings['per_node']);
    $this->assertEquals(5, $article_settings['priority']);
  }

}
