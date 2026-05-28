<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Template\TwigEnvironment;
use Drupal\user\Entity\Role;
use Drupal\user\Entity\User;

/**
 * Class Info.
 *
 * @package Drupal\permissions_by_term\Service
 */
class NodeEntityBundleInfo {

  /**
   * @var AccessStorage
   */
  private $accessStorage;

  /**
   * @var TermHandler
   */
  private $term;

  /**
   * @var \Drupal\Core\Template\TwigEnvironment
   */
  private $twig;

  /**
   * @var \Drupal\Core\Database\Connection
   */
  private $database;

  /**
   * Info constructor.
   *
   * @param AccessStorage $accessStorage
   * @param TermHandler $term
   * @param \Drupal\Core\Template\TwigEnvironment $twig
   * @param \Drupal\Core\Database\Connection $database
   */
  public function __construct(
    AccessStorage $accessStorage,
    TermHandler $term,
    TwigEnvironment $twig,
    Connection $database,
  ) {
    $this->accessStorage = $accessStorage;
    $this->term = $term;
    $this->twig = $twig;
    $this->database = $database;
  }

  /**
   * @param string $langcode
   *   Language code.
   * @param int|false $nid
   *   The node ID.
   *
   * @return array
   * @throws \Twig_Error_Loader
   * @throws \Twig_Error_Runtime
   * @throws \Twig_Error_Syntax
   */
  public function prepareNodeDetails($langcode, $nid = FALSE) {
    $roles = NULL;
    $users = NULL;
    $rids = NULL;
    $uids = NULL;

    if ($nid !== NULL) {
      $tids = $this->term->getTidsByNid($nid);
      if (!empty($tids)) {
        $uids = $this->accessStorage->getUserTermPermissionsByTids($tids, $langcode);
        $rids = $this->accessStorage->getRoleTermPermissionsByTids($tids, $langcode);
      }
    }

    if ($rids !== NULL) {
      $roles = Role::loadMultiple($rids);
    }

    if ($uids !== NULL) {
      $users = User::loadMultiple($uids);
    }

    return ['roles' => $roles, 'users' => $users];
  }

  /**
   * @return array
   */
  public function getPermissions() {
    $returnArray = NULL;

    $permittedUsers = $this->database->select('permissions_by_term_user', 'pu')
      ->fields('pu', ['uid', 'tid'])
      ->execute()
      ->fetchAll();

    $permittedRoles = $this->database->select('permissions_by_term_role', 'pr')
      ->fields('pr', ['rid', 'tid'])
      ->execute()
      ->fetchAll();

    if (!empty($permittedRoles)) {
      $returnArray['roleLabels'] = [];
      foreach ($permittedRoles as $permittedRole) {
        $role = Role::load($permittedRole->rid);
        if (!empty($role)) {
          $returnArray['roleLabels'][$permittedRole->tid][] = $role->label();
        }
      }
    }

    if (!empty($permittedUsers)) {
      $returnArray['userDisplayNames'] = [];
      foreach ($permittedUsers as $permittedUser) {
        $user = User::load($permittedUser->uid);
        if (!empty($user)) {
          $returnArray['userDisplayNames'][$permittedUser->tid][] = $user->getDisplayName();
        }
      }
    }

    return $returnArray;
  }

}
