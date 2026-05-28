<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Service;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\node\Entity\Node;
use Drupal\permissions_by_term\Cache\KeyValueCache;
use Drupal\user\Entity\Role;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * A storage helper class for access info.
 *
 * @package Drupal\permissions_by_term
 */
class AccessStorage {

  use StringTranslationTrait;

  public const NODE_ACCESS_REALM = 'permissions_by_term';

  /**
   * Constructs a new AccessStorage.
   */
  public function __construct(
    private readonly Connection $database,
    private readonly EntityTypeManagerInterface $entityTypeManager,
    private readonly LanguageManagerInterface $languageManager,
    private readonly ConfigFactoryInterface $configFactory,
    private readonly TermHandler $term,
    private readonly AccessCheck $accessCheck,
    private readonly KeyValueCache $keyValueCache,
    private readonly RequestStack $requestStack,
    private array $grantsCache = [],
  ) {
  }

  /**
   * Gets submitted roles with granted access from form.
   *
   * @return array
   *   An array with chosen roles.
   */
  public function getSubmittedRolesGrantedAccess(FormStateInterface $form_state) {
    $aRoles       = $form_state->getValue('access')['role'];
    $aChosenRoles = [];
    foreach ($aRoles as $sRole) {
      if ($sRole !== 0) {
        $aChosenRoles[] = $sRole;
      }
    }
    return $aChosenRoles;
  }

  /**
   * Get all term permissions by user id.
   *
   * @return array
   *   The list of terms for this user.
   */
  public function getAllTermPermissionsByUserId($uid) {
    $query = $this->database->select('permissions_by_term_user', 'pu')
      ->condition('uid', $uid);
    $query->addExpression("CONCAT(tid, ' ', uid, ' ', langcode)");
    return $query->orderBy('tid')
      ->orderBy('uid')
      ->orderBy('langcode')
      ->execute()
      ->fetchCol(0);
  }

  /**
   * @param int $term_id
   *
   * @return array
   */
  public function getUserTermPermissionsByTid($term_id, $langcode) {
    return $this->database->select('permissions_by_term_user', 'pu')
      ->condition('tid', $term_id)
      ->condition('langcode', $langcode)
      ->fields('pu', ['uid'])
      ->execute()
      ->fetchCol();
  }

  /**
   * @param int $uid
   * @param array $rids
   *
   * @return array
   */
  public function getPermittedTids($uid, $rids) {
    $permittedTids = $this->database->select('permissions_by_term_user', 'pu')
      ->condition('uid', $uid)
      ->fields('pu', ['tid'])
      ->execute()
      ->fetchCol();

    foreach ($rids as $rid) {
      $permittedTidsByRid = $this->database->select('permissions_by_term_role', 'pr')
        ->condition('rid', $rid)
        ->fields('pr', ['tid'])
        ->execute()
        ->fetchCol();

      $permittedTids = array_merge($permittedTidsByRid, $permittedTids);
    }

    return array_unique($permittedTids);
  }

  /**
   * @param array $tids
   * @param string $langcode
   * @return array
   */
  public function getUserTermPermissionsByTids($tids, $langcode) {
    $uids = [];

    foreach ($tids as $tid) {
      if (!empty($tmpUids = $this->getUserTermPermissionsByTid($tid, $langcode))) {
        foreach ($tmpUids as $tmpUid) {
          $uids[] = $tmpUid;
        }
      }
    }

    return $uids;
  }

  /**
   * @param int $term_id
   * @param string $langcode
   *
   * @return array
   */
  public function getRoleTermPermissionsByTid($term_id, $langcode) {
    return $this->database->select('permissions_by_term_role', 'pr')
      ->condition('tid', $term_id)
      ->condition('langcode', $langcode)
      ->fields('pr', ['rid'])
      ->execute()
      ->fetchCol();
  }

  /**
   * @param array $tids
   * @param string $langcode
   * @return array
   */
  public function getRoleTermPermissionsByTids($tids, $langcode) {
    $rids = [];

    foreach ($tids as $tid) {
      $tmpRids = $this->getRoleTermPermissionsByTid($tid, $langcode);
      if (!empty($tmpRids)) {
        foreach ($tmpRids as $tmpRid) {
          $rids[] = $tmpRid;
        }
      }
    }

    return $rids;
  }

  /**
   * @param string $sUsername
   *
   * @return int
   */
  public function getUserIdByName($sUsername) {
    return $this->database->select('users_field_data', 'ufd')
      ->condition('name', $sUsername)
      ->fields('ufd', ['uid'])
      ->execute()
      ->fetchAssoc();
  }

  /**
   * @param array $aUserNames
   *
   * @return array
   */
  public function getUserIdsByNames($aUserNames) {
    $aUserIds = [];
    foreach ($aUserNames as $userName) {
      $iUserId    = $this->getUserIdByName($userName)['uid'];
      $aUserIds[] = $iUserId['uid'];
    }
    return $aUserIds;
  }

  /**
   * @param int $term_id
   *
   * @return array
   */
  public function getAllowedUserIds($term_id, $langcode) {
    $query = $this->database->select('permissions_by_term_user', 'p')
      ->fields('p', ['uid'])
      ->condition('p.tid', $term_id)
      ->condition('p.langcode', $langcode);

    // fetchCol() returns all results, fetchAssoc() only "one" result.
    return $query->execute()
      ->fetchCol();
  }

  /**
   * @param array $aUserIdsAccessRemove
   * @param int $term_id
   */
  public function deleteTermPermissionsByUserIds($aUserIdsAccessRemove, $term_id, $langcode) {
    foreach ($aUserIdsAccessRemove as $iUserId) {
      $this->database->delete('permissions_by_term_user')
        ->condition('uid', $iUserId, '=')
        ->condition('tid', $term_id, '=')
        ->condition('langcode', $langcode, '=')
        ->execute();
    }
  }

  /**
   * @param array $aRoleIdsAccessRemove
   * @param int $term_id
   */
  public function deleteTermPermissionsByRoleIds($aRoleIdsAccessRemove, $term_id, $langcode) {
    foreach ($aRoleIdsAccessRemove as $sRoleId) {
      $this->database->delete('permissions_by_term_role')
        ->condition('rid', $sRoleId, '=')
        ->condition('tid', $term_id, '=')
        ->condition('langcode', $langcode, '=')
        ->execute();
    }
  }

  /**
   * @param int $userId
   */
  public function deleteAllTermPermissionsByUserId($userId) {
    $this->database->delete('permissions_by_term_user')
      ->condition('uid', $userId, '=')
      ->execute();
  }

  /**
   * Delete access storage when a term is removed.
   *
   * @param int $term_id
   *   The term ID being deleted.
   */
  public function deleteAllTermPermissionsByTid($term_id) {
    $this->database->delete('permissions_by_term_user')
      ->condition('tid', $term_id, '=')
      ->execute();

    $this->database->delete('permissions_by_term_role')
      ->condition('tid', $term_id, '=')
      ->execute();
  }

  /**
   * Deletes all term permissions associated with a given taxonomy term bundle.
   *
   * @param string $bundle
   *   The machine name of the term bundle.
   */
  public function deleteAllTermPermissionsByTermBundle(string $bundle): void {
    $term_query = $this->database->select('taxonomy_term_data', 'term');
    $term_query->addField('term', 'tid');
    $term_query->condition('vid', $bundle);

    $term_ids = $term_query->execute()->fetchCol();

    if ($term_ids) {
      $this->database->delete('permissions_by_term_user')
        ->condition('tid', $term_ids, 'IN')
        ->execute();

      $this->database->delete('permissions_by_term_role')
        ->condition('tid', $term_ids, 'IN')
        ->execute();
    }
  }

  /**
   *
   */
  public function addTermPermissionsByUserIds(array $aUserIdsGrantedAccess, string $termId, string $langcode = ''): void {
    $langcode = ($langcode === '') ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;

    foreach ($aUserIdsGrantedAccess as $iUserIdGrantedAccess) {
      $queryResult = $this->database->query("SELECT uid FROM {permissions_by_term_user} WHERE tid = :tid AND uid = :uid AND langcode = :langcode",
        [':tid' => $termId, ':uid' => $iUserIdGrantedAccess, ':langcode' => $langcode])->fetchField();
      if (empty($queryResult)) {
        $this->database->insert('permissions_by_term_user')
          ->fields(['tid', 'uid', 'langcode'], [
            $termId,
            $iUserIdGrantedAccess,
            $langcode,
          ])
          ->execute();
      }
    }
  }

  /**
   * @param array $aRoleIdsGrantedAccess
   * @param int $term_id
   * @param string $langcode
   *
   * @throws \Exception
   */
  public function addTermPermissionsByRoleIds($aRoleIdsGrantedAccess, $term_id, $langcode = '') {
    $langcode = ($langcode === '') ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;

    $roles = Role::loadMultiple();
    foreach ($roles as $roleObj) {
      if ($roleObj->hasPermission('bypass node access')) {
        $aRoleIdsGrantedAccess[] = $roleObj->id();
      }
    }

    $aRoleIdsGrantedAccess = array_unique($aRoleIdsGrantedAccess);

    foreach ($aRoleIdsGrantedAccess as $sRoleIdGrantedAccess) {
      $queryResult = $this->database->query("SELECT rid FROM {permissions_by_term_role} WHERE tid = :tid AND rid = :rid AND langcode = :langcode",
        [':tid' => $term_id, ':rid' => $sRoleIdGrantedAccess, ':langcode' => $langcode])->fetchField();
      if (empty($queryResult)) {
        $this->database->insert('permissions_by_term_role')
          ->fields(['tid', 'rid', 'langcode'], [$term_id, $sRoleIdGrantedAccess, $langcode])
          ->execute();
      }
    }
  }

  /**
   * Gets the user ids which have been submitted by form.
   *
   * Users which will gain granted access to taxonomy terms.
   *
   * @return array
   *   The user ids which have been submitted.
   */
  public function getSubmittedUserIds($formState) {
    $sRawUsers = $this->requestStack->getCurrentRequest()->request->all()['access']['user'] ?? [];

    if (empty($sRawUsers)) {
      return [];
    }

    $aRawUsers = Tags::explode($sRawUsers);

    $aUserIds = [];
    if (!empty($aRawUsers)) {
      foreach ($aRawUsers as $sRawUser) {
        if (preg_match("/.+\s\(([^\)]+)\)/", $sRawUser, $matches)) {
          $aUserIds[] = $matches[1];
        }
      }
    }

    return $aUserIds;
  }

  /**
   * @param \Drupal\Core\Form\FormState $formState
   * @param string $term_id
   *
   * @return array
   * @throws \Exception
   */
  public function saveTermPermissions(FormStateInterface $formState, string $term_id) {
    $langcode = $this->languageManager->getCurrentLanguage()->getId();
    if (!empty($formState->getValue('langcode'))) {
      $langcode = $formState->getValue('langcode')['0']['value'];
    }

    $aExistingUserPermissions       = $this->getUserTermPermissionsByTid($term_id, $langcode);
    $aSubmittedUserIdsGrantedAccess = $this->getSubmittedUserIds($formState);

    $aExistingRoleIdsGrantedAccess = $this->getRoleTermPermissionsByTid($term_id, $langcode);
    $aSubmittedRolesGrantedAccess  = $this->getSubmittedRolesGrantedAccess($formState);

    $aRet = $this->getPreparedDataForDatabaseQueries($aExistingUserPermissions,
      $aSubmittedUserIdsGrantedAccess, $aExistingRoleIdsGrantedAccess,
      $aSubmittedRolesGrantedAccess);

    $this->deleteTermPermissionsByUserIds($aRet['UserIdPermissionsToRemove'], $term_id, $langcode);
    $this->addTermPermissionsByUserIds($aRet['UserIdPermissionsToAdd'], $term_id, $langcode);

    $this->deleteTermPermissionsByRoleIds($aRet['UserRolePermissionsToRemove'], $term_id, $langcode);
    if (!empty($aRet['aRoleIdPermissionsToAdd'])) {
      $this->addTermPermissionsByRoleIds($aRet['aRoleIdPermissionsToAdd'], $term_id, $langcode);
    }

    return $aRet;
  }

  /**
   * Get array items to remove.
   *
   * The array items which aren't in the new items array, but are in old items
   * array, will be returned.
   *
   * @param array $aExistingItems
   *   The existing array items.
   * @param array|bool $aNewItems
   *   Either false if there're no new items or an array with items.
   *
   * @return array
   *   The array items to remove.
   */
  private function getArrayItemsToRemove($aExistingItems, $aNewItems) {
    $aRet = [];

    foreach ($aExistingItems as $existingItem) {
      if (!in_array($existingItem, $aNewItems)) {
        $aRet[] = $existingItem;
      }
    }

    return $aRet;
  }

  /**
   * Get the array items to add.
   *
   * The items in the new items array, which aren't in the existing items array,
   * will be returned.
   *
   * @param array $aNewItems
   *   The new array items.
   * @param array $aExistingItems
   *   The existing array items.
   *
   * @return array
   *   The items which needs to be added.
   */
  private function getArrayItemsToAdd($aNewItems, $aExistingItems) {
    $aRet = [];

    foreach ($aNewItems as $newItem) {
      if (!in_array($newItem, $aExistingItems)) {
        $aRet[] = $newItem;
      }
    }

    return $aRet;
  }

  /**
   * Prepares the data which has to be applied to the database.
   *
   * @param array $aExistingUserPermissions
   *   The permissions for existing user.
   * @param array $aSubmittedUserIdsGrantedAccess
   *   The user ids which get access.
   * @param array $aExistingRoleIdsGrantedAccess
   *   The existing role ids.
   * @param array $aSubmittedRolesGrantedAccess
   *   The user roles which get access.
   *
   * @return array
   *   User ID and role data.
   */
  public function getPreparedDataForDatabaseQueries(
    $aExistingUserPermissions,
    $aSubmittedUserIdsGrantedAccess,
    $aExistingRoleIdsGrantedAccess,
    $aSubmittedRolesGrantedAccess,
  ) {
    // Fill array with user ids to remove permission.
    $aRet['UserIdPermissionsToRemove'] =
      $this->getArrayItemsToRemove($aExistingUserPermissions,
        $aSubmittedUserIdsGrantedAccess);

    // Fill array with user ids to add permission.
    $aRet['UserIdPermissionsToAdd'] =
      $this->getArrayItemsToAdd($aSubmittedUserIdsGrantedAccess,
        $aExistingUserPermissions);

    // Fill array with user roles to remove permission.
    $aRet['UserRolePermissionsToRemove'] =
      $this->getArrayItemsToRemove($aExistingRoleIdsGrantedAccess,
        $aSubmittedRolesGrantedAccess);

    // Fill array with user roles to add permission.
    $aRet['aRoleIdPermissionsToAdd'] =
      $this->getArrayItemsToAdd($aSubmittedRolesGrantedAccess,
        $aExistingRoleIdsGrantedAccess);

    return $aRet;
  }

  /**
   * The form value for allowed users as string to be shown to the user.
   *
   * @param \Drupal\user\Entity\User[] $aAllowedUsers
   *   An array with the allowed users.
   *
   * @return null|string
   *   Either null or the user name.
   */
  public function getUserFormValue($aAllowedUsers) {

    $sUserInfos = '';

    if (!empty($aAllowedUsers)) {

      foreach ($aAllowedUsers as $oUser) {
        $iUid = (int) $oUser->id();
        if ($iUid !== 0) {
          $sUsername = $oUser->getDisplayName();
        }
        else {
          $sUsername = $this->t('Anonymous User');
        }

        $sUserInfos .= "$sUsername ($iUid), ";
      }

      // Remove space and comma at the end of the string.
      $sUserInfos = substr($sUserInfos, 0, -2);
    }

    return $sUserInfos;
  }

  /**
   * Returns an array of term ids attached to the passed node id.
   *
   * @param $nid
   *   Node id.
   *
   * @return array
   *   Array of term ids
   */
  public function getTidsByNid($nid): array {
    $nidsToTidsPairs = [];

    if ($this->keyValueCache->has()) {
      $nidsToTidsPairs = $this->keyValueCache->get();
      if (!empty($nidsToTidsPairs[$nid])) {
        return $nidsToTidsPairs[$nid];
      }
    }

    $tidsForNid = $this->database->select('taxonomy_index')
      ->fields('taxonomy_index', ['tid'])
      ->condition('nid', $nid)
      ->execute()
      ->fetchCol();

    if (!empty($tidsForNid)) {
      $nidsToTidsPairs[$nid] = $tidsForNid;
      $this->keyValueCache->set($nidsToTidsPairs);
      return $tidsForNid;
    }

    return [];
  }

  /**
   * @return array
   */
  public function getAllUids() {
    $nodes = $this->entityTypeManager->getStorage('user')->getQuery()
      ->accessCheck(FALSE)
      ->execute();

    return array_values($nodes);
  }

  /**
   * @param $nid
   *
   * @return array
   */
  public function getNodeType($nid) {
    $query = $this->database->select('node', 'n')
      ->fields('n', ['type'])
      ->condition('n.nid', $nid);

    return $query->execute()
      ->fetchAssoc()['type'];
  }

  /**
   * @param $nid
   *
   * @return array
   */
  public function getLangCode($nid) {
    $query = $this->database->select('node', 'n')
      ->fields('n', ['langcode'])
      ->condition('n.nid', $nid);

    return $query->execute()
      ->fetchAssoc()['langcode'];
  }

  /**
   * @param \Drupal\Core\Session\AccountInterface $user
   *
   * @return array
   */
  public function getGids(AccountInterface $user) {
    $grants = NULL;

    if (!empty($this->grantsCache[$user->id()])) {
      return $this->grantsCache[$user->id()];
    }

    if (!empty($permittedNids = $this->computePermittedTids($user))) {
      $query = $this->database->select('node_access', 'na')
        ->fields('na', ['gid'])
        ->condition('na.nid', $permittedNids, 'IN')
        ->condition('na.realm', self::NODE_ACCESS_REALM);

      $gids = $query->execute()->fetchCol();

      foreach ($gids as $gid) {
        $grants[self::NODE_ACCESS_REALM][] = $gid;
      }
    }

    $this->grantsCache[$user->id()] = $grants;

    return $grants;
  }

  /**
   *
   */
  private function computePermittedTids(AccountInterface $user) {
    $nidsWithNoTidRestriction = $this->getUnrestrictedNids();
    $nidsByTids = $this->term->getNidsByTidsForPublishedNodes($this->getPermittedTids($user->id(), $user->getRoles()));

    if ($this->configFactory->get('permissions_by_term.settings')->get('require_all_terms_granted')) {
      $permittedNids = [];
      foreach ($nidsByTids as $nid) {
        if ($this->accessCheck->canUserAccessByNode(Node::load($nid), $user->id(), $this->getLangCode($nid))) {
          $permittedNids[] = $nid;
        }
      }
      $nidsByTids = $permittedNids;
    }

    if (!empty($nidsByTids)) {
      return array_merge(
        $this->getUnrestrictedNids(),
        $nidsByTids
      );
    }

    return $nidsWithNoTidRestriction;
  }

  /**
   *
   */
  private function getUnrestrictedNids() {
    $tidsRestrictedUserQuery = $this->database->select('permissions_by_term_user', 'u')
      ->fields('u', ['tid']);

    $restrictedTids = $this->database->select('permissions_by_term_role', 'r')
      ->fields('r', ['tid'])
      ->union($tidsRestrictedUserQuery)
      ->execute()
      ->fetchCol();

    if (empty($restrictedTids)) {
      return $this->getAllNids();
    }

    $restrictedNids = $this->database->select('taxonomy_index', 't')
      ->fields('t', ['nid'])
      ->condition('t.tid', $restrictedTids, 'IN')
      ->distinct(TRUE)
      ->execute()
      ->fetchCol();

    if (empty($restrictedNids)) {
      return $this->getAllNids();
    }

    $unrestrictedNids = $this->database->select('taxonomy_index', 't')
      ->fields('t', ['nid'])
      ->condition('t.nid', $restrictedNids, 'NOT IN')
      ->distinct(TRUE)
      ->execute()
      ->fetchCol();

    return $unrestrictedNids;
  }

  /**
   * @return array
   */
  public function getAllNids() {
    return $this->database->select('node', 'n')
      ->fields('n', ['nid'])
      ->execute()
      ->fetchCol();
  }

  /**
   * @param $uid
   *
   * @return array
   */
  public function getAllNidsUserCanAccess($uid) {
    $query = $this->database->select('node_access', 'na')
      ->fields('na', ['nid'])
      ->condition('na.realm', 'permissions_by_term__uid_' . $uid);

    return $query->execute()
      ->fetchCol();
  }

  /**
   * @param $tid
   *
   * @return array
   */
  public function getNidsByTid($tid) {
    $query = $this->database->select('taxonomy_index', 'ti')
      ->fields('ti', ['nid'])
      ->condition('ti.tid', $tid);

    return $query->execute()->fetchCol();
  }

}
