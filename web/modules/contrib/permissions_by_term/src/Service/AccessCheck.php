<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Service;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\node\Entity\Node;
use Drupal\permissions_by_term\Event\PermissionsByTermDeniedEvent;
use Drupal\taxonomy\Entity\Term;
use Drupal\user\Entity\User;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Provides term-based access check functions.
 */
class AccessCheck {

  /**
   * The database connection.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManager
   */
  private $entityFieldManager;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  private LanguageManagerInterface $languageManager;

  /**
   * The current user.
   *
   * @var \Drupal\Core\Session\AccountProxyInterface
   */
  private AccountProxyInterface $currentUser;

  /**
   * The config factory.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * Constructs AccessCheck object.
   *
   * @param \Drupal\Core\Database\Connection $database
   *   The database connection.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher.
   * @param \Drupal\Core\Entity\EntityFieldManager $entityFieldManager
   *   The entity field manager.
   * @param \Drupal\Core\Language\LanguageManagerInterface $languageManager
   *   The language manager.
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user.
   * @param \Drupal\Core\Config\ConfigFactoryInterface $configFactory
   *   The config factory.
   */
  public function __construct(Connection $database, EventDispatcherInterface $eventDispatcher, EntityFieldManager $entityFieldManager, LanguageManagerInterface $languageManager, AccountProxyInterface $currentUser, ConfigFactoryInterface $configFactory) {
    $this->database = $database;
    $this->eventDispatcher = $eventDispatcher;
    $this->entityFieldManager = $entityFieldManager;
    $this->languageManager = $languageManager;
    $this->currentUser = $currentUser;
    $this->configFactory = $configFactory;
  }

  /**
   * Checks whether the given user can access the given node.
   */
  public function canUserAccessByNode(Node $node, $uid = FALSE, $langcode = ''): bool {
    if (!$this->isAnyTaxonomyTermFieldDefinedInNodeType($node->getType())) {
      return TRUE;
    }

    $langcode = ($langcode === '') ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;

    if (empty($uid)) {
      $uid = $this->currentUser->id();
    }

    $user = User::load($uid);

    if ($user instanceof AccountInterface) {
      if ($user->hasPermission('view own unpublished content') &&
        (int) $node->getOwnerId() === (int) $uid &&
        !$node->isPublished()
      ) {
        return TRUE;
      }

      if ($user->hasPermission('view any unpublished content') &&
        !$node->isPublished()
      ) {
        return TRUE;
      }

      if ($user->hasPermission('bypass node access')) {
        return TRUE;
      }

      if ($user->id() === '0' && !$user->hasPermission('view any unpublished content') &&
        !$node->isPublished()
      ) {
        return FALSE;
      }

      if ((int) $user->id() !== (int) $node->getOwnerId() && !$node->isPublished()) {
        return FALSE;
      }
    }

    $configPermissionMode = $this->configFactory->get('permissions_by_term.settings')->get('permission_mode');
    $requireAllTermsGranted = $this->configFactory->get('permissions_by_term.settings')->get('require_all_terms_granted');

    if (!$configPermissionMode && !$requireAllTermsGranted) {
      $access_allowed = TRUE;
    }
    else {
      $access_allowed = FALSE;
    }

    $terms = $this->database
      ->select('taxonomy_index', 'ti')
      ->fields('ti', ['tid'])
      ->condition('nid', $node->id());

    // Query should get only selected target bundles.
    $target_bundles = $this->configFactory->get('permissions_by_term.settings')->get('target_bundles');
    if (!empty($target_bundles)) {
      $terms->innerJoin('taxonomy_term_data', 'ttd', 'ti.tid=ttd.tid');
      $terms->condition('vid', $target_bundles, 'IN');
    }

    $terms = $terms->execute()->fetchAll();

    if (empty($terms) && !$configPermissionMode) {
      return TRUE;
    }

    foreach ($terms as $term) {
      $termInfo = Term::load($term->tid);

      if ($termInfo instanceof Term && $termInfo->get('langcode')->getLangcode() == $langcode) {
        if (!$this->isAnyPermissionSetForTerm($term->tid, $termInfo->get('langcode')->getLangcode())) {
          continue;
        }
        $access_allowed = $this->isAccessAllowedByDatabase($term->tid, $uid, $termInfo->get('langcode')->getLangcode());
        if (!$access_allowed && $requireAllTermsGranted) {
          return $access_allowed;
        }

        if ($access_allowed && !$requireAllTermsGranted) {
          return $access_allowed;
        }
      }

    }

    return $access_allowed;
  }

  /**
   * Checks whether the given user is allowed access to the given term.
   */
  public function isAccessAllowedByDatabase($tid, $uid = FALSE, $langcode = '') {
    $langcode = ($langcode === '') ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;

    if (is_numeric($uid) && $uid >= 0) {
      $user = User::load($uid);
    }
    else {
      $user = $this->currentUser;
    }

    $tid = (int) $tid;

    if (!$this->isAnyPermissionSetForTerm($tid, $langcode) && !$this->configFactory->get('permissions_by_term.settings')->get('permission_mode')) {
      return TRUE;
    }

    /* At this point permissions are enabled, check to see if this user or one
     * of their roles is allowed.
     */
    if ($user instanceof AccountInterface) {
      foreach ($user->getRoles() as $sUserRole) {
        if ($this->isTermAllowedByUserRole($tid, $sUserRole, $langcode)) {
          return TRUE;
        }
      }

      if ($this->isTermAllowedByUserId($tid, $user->id(), $langcode)) {
        return TRUE;
      }
    }

    return FALSE;
  }

  /**
   * Checks whether access to a given term is permitted for a given user.
   */
  private function isTermAllowedByUserId($tid, $iUid, $langcode) {
    $query_result = $this->database->query("SELECT uid FROM {permissions_by_term_user} WHERE tid = :tid AND uid = :uid AND langcode = :langcode",
      [':tid' => $tid, ':uid' => $iUid, ':langcode' => $langcode])->fetchField();

    if (!empty($query_result)) {
      return TRUE;
    }
    else {
      return FALSE;
    }
  }

  /**
   * Checks whether access to a given term is permitted by a given role.
   */
  public function isTermAllowedByUserRole($tid, $sUserRole, $langcode): bool {
    $query_result = $this->database->query("SELECT rid FROM {permissions_by_term_role} WHERE tid = :tid AND rid IN (:user_roles) AND langcode = :langcode",
      [':tid' => $tid, ':user_roles' => $sUserRole, ':langcode' => $langcode])->fetchField();

    if (!empty($query_result)) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks where there is access control associated with a given term.
   */
  public function isAnyPermissionSetForTerm($tid, $langcode = ''): bool {
    $langcode = ($langcode === '') ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;

    $iUserTableResults = (int) $this->database->query("SELECT COUNT(1) FROM {permissions_by_term_user} WHERE tid = :tid AND langcode = :langcode",
      [':tid' => $tid, ':langcode' => $langcode])->fetchField();

    $iRoleTableResults = (int) $this->database->query("SELECT COUNT(1) FROM {permissions_by_term_role} WHERE tid = :tid AND langcode = :langcode",
      [':tid' => $tid, ':langcode' => $langcode])->fetchField();

    if ($iUserTableResults > 0 ||
      $iRoleTableResults > 0) {
      return TRUE;
    }

    return FALSE;
  }

  /**
   * Checks the access for a given node.
   */
  public function handleNode(Node $node, string $langcode): AccessResult {
    $result = AccessResult::neutral();

    if (!$this->canUserAccessByNode($node, FALSE, $langcode)) {
      $this->dispatchDeniedEvent($node->id());

      $result = AccessResult::forbidden();
    }

    return $result;
  }

  /**
   * Dispatches an access denied event if the user cannot access the given node.
   */
  public function dispatchDeniedEventOnRestricedAccess(Node $node, string $langcode) {
    if (!$this->canUserAccessByNode($node, FALSE, $langcode)) {
      return $this->dispatchDeniedEvent($node->id());
    }
    return AccessResult::neutral();
  }

  /**
   * Dispatches a custom access denied event for a given node.
   */
  private function dispatchDeniedEvent($nodeId) {
    $accessDeniedEvent = new PermissionsByTermDeniedEvent($nodeId);
    $this->eventDispatcher->dispatch($accessDeniedEvent, PermissionsByTermDeniedEvent::NAME);
    return AccessResult::forbidden();
  }

  /**
   * Checks whether there are taxonomy fields defined in a given node type.
   */
  public function isAnyTaxonomyTermFieldDefinedInNodeType(string $nodeType): bool {
    $fieldDefinitons = $this->entityFieldManager->getFieldDefinitions('node', $nodeType);
    foreach ($fieldDefinitons as $fieldDefiniton) {
      if ($fieldDefiniton->getType() === 'entity_reference' && is_numeric(strpos($fieldDefiniton->getSetting('handler'), 'taxonomy_term'))) {
        return TRUE;
      }
    }

    return FALSE;
  }

}
