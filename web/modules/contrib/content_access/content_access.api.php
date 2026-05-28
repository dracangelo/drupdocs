<?php

/**
 * @file
 * API documentation for Content Access module.
 */

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\node\NodeInterface;

/**
 * Implement this hook to alter access to node page settings.
 *
 * For example, if you use ACL module, you could want to allow access to this
 * page to people you allowed to edit.
 *
 * @param Drupal\Core\Session\AccountInterface $account
 *   The user object on which the permissions are checked.
 * @param Drupal\Core\Routing\RouteMatchInterface $route_match
 *   The route match on which the permissions are checked.
 * @param \Drupal\Core\Access\AccessResultInterface $access
 *   Access result before processing hook implementations.
 */
function hook_content_access_node_page(AccountInterface $account, RouteMatchInterface $route_match, array $access_parameters, AccessResultInterface $access): AccessResultInterface {
  return AccessResult::forbidden();
}

/**
 * Respond to the insert/update of an ACL.
 *
 * @deprecated in content_access:2.1.0 and is removed from content_access:3.0.0.
 * Use hook_content_access_user_acl instead.
 *
 * @see https://www.drupal.org/node/3586991
 * /
 */
function hook_user_acl(array $view, array $view_own, array $update, array $update_own, array $delete, array $delete_own) : void {
  // Your code.
}

/**
 * Respond to saving per-node content access settings.
 *
 * @deprecated in content_access:2.1.0 and is removed from content_access:3.0.0.
 * Use hook_content_access_per_node instead.
 *
 * @see https://www.drupal.org/node/3586991
 */
function hook_per_node(array $settings, NodeInterface $node) : void {
  // Your code.
}

/**
 * Respond to the insert/update of an ACL.
 *
 * This hook is invoked after ACL module saves the information to the database.
 *
 * @param array $view
 *   An array of role IDs that are allowed to view the node.
 * @param array $view_own
 *   An array of role IDs that are allowed to view their own nodes.
 * @param array $update
 *   An array of role IDs that are allowed to update the node.
 * @param array $update_own
 *   An array of role IDs that are allowed to update their own nodes.
 * @param array $delete
 *   An array of role IDs that are allowed to delete the node.
 * @param array $delete_own
 *   An array of role IDs that are allowed to delete their own nodes.
 */
function hook_content_access_user_acl(array $view, array $view_own, array $update, array $update_own, array $delete, array $delete_own) : void {
  // Your code.
}

/**
 * Respond to saving per-node content access settings.
 *
 * @param array $settings
 *   An associative array of content access settings, the keys are:
 *   - view
 *   - update
 *   - delete
 *   - view_own
 *   - update_own
 *   - delete_own
 *   The values are an array of roles that are allowed to perform that operation
 *   on the node.
 * @param \Drupal\node\NodeInterface $node
 *   The node to which the settings apply.
 */
function hook_content_access_per_node(array $settings, NodeInterface $node) : void {
  // Your code.
}
