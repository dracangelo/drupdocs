<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Model;

class NodeAccessRecordModel {

  /**
   * @var int
   */
  public $nid;

  /**
   * @var string
   */
  public $langcode;

  /**
   * @var int
   */
  public $fallback;

  /**
   * @var int
   */
  public $gid;

  /**
   * @var string
   */
  public $realm;

  /**
   * @var int
   */
  public $grantView;

  /**
   * @var int
   */
  public $grantUpdate;

  /**
   * @var int
   */
  public $grantDelete;

  /**
   * @return int
   */
  public function getNid() {
    return $this->nid;
  }

  /**
   * @param int $nid
   */
  public function setNid($nid) {
    $this->nid = $nid;
  }

  /**
   * @return string
   */
  public function getLangcode() {
    return $this->langcode;
  }

  /**
   * @param string $langcode
   */
  public function setLangcode($langcode) {
    $this->langcode = $langcode;
  }

  /**
   * @return int
   */
  public function getFallback() {
    return $this->fallback;
  }

  /**
   * @param int $fallback
   */
  public function setFallback($fallback) {
    $this->fallback = $fallback;
  }

  /**
   * @return int
   */
  public function getGid() {
    return $this->gid;
  }

  /**
   * @param int $gid
   */
  public function setGid($gid) {
    $this->gid = $gid;
  }

  /**
   * @return string
   */
  public function getRealm() {
    return $this->realm;
  }

  /**
   * @param string $realm
   */
  public function setRealm($realm) {
    $this->realm = $realm;
  }

  /**
   * @return int
   */
  public function getGrantView() {
    return $this->grantView;
  }

  /**
   * @param int $grantView
   */
  public function setGrantView($grantView) {
    $this->grantView = $grantView;
  }

  /**
   * @return int
   */
  public function getGrantUpdate() {
    return $this->grantUpdate;
  }

  /**
   * @param int $grantUpdate
   */
  public function setGrantUpdate($grantUpdate) {
    $this->grantUpdate = $grantUpdate;
  }

  /**
   * @return int
   */
  public function getGrantDelete() {
    return $this->grantDelete;
  }

  /**
   * @param int $grantDelete
   */
  public function setGrantDelete($grantDelete) {
    $this->grantDelete = $grantDelete;
  }

}
