<?php

namespace Drupal\content_access\Access;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Extension\ModuleHandlerInterface;
use Drupal\Core\Routing\Access\AccessInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;

/**
 * Class ContentAccessNodePageAccessCheck.
 *
 * Determines access to routes based on permissions defined via
 * $module.permissions.yml files.
 */
class ContentAccessNodePageAccessCheck implements AccessInterface {

  /**
   * Constructs ContentAccessNodePageAccessCheck object.
   */
  public function __construct(
    private readonly ModuleHandlerInterface $moduleHandler,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public function access(AccountInterface $account, RouteMatchInterface $route_match): AccessResultInterface {
    $node = $route_match->getParameter('node');
    $all_nodes_access = $account->hasPermission('grant content access');
    $own_node_access = $account->hasPermission('grant own content access') && ($account->id() === $node->getOwnerId());
    $per_node = content_access_get_settings('per_node', $node->getType());
    $default_result = AccessResult::allowedIf($per_node && ($all_nodes_access || $own_node_access));
    $results = array_merge($this->moduleHandler->invokeAll('content_access_node_page', [
      $account,
      $route_match,
      [
        'per_node' => $per_node,
        'all_nodes_access' => $all_nodes_access,
        'own_node_access' => $own_node_access,
      ],
      $default_result,
    ]), [$default_result]);

    // Process results.
    $result = array_shift($results);
    foreach ($results as $other) {
      $result = $result->orIf($other);
    }
    return $result;
  }

}
