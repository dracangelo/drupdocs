<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Factory;

use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\permissions_by_term\Model\NodeAccessRecordModel;

/**
 * Factory class to generate new node access record entries.
 */
class NodeAccessRecordFactory {

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected LanguageManagerInterface $languageManager;

  /**
   * Constructs a new NodeAccessRecordFactory.
   */
  public function __construct(LanguageManagerInterface $languageManager) {
    $this->languageManager = $languageManager;
  }

  /**
   * Creates a new node access record.
   */
  public function create($realm, $gid, $nid, $langcode = '', $grantUpdate = 0, $grantDelete = 0) {
    $langcode = ($langcode === '') ? $this->languageManager->getCurrentLanguage()->getId() : $langcode;

    $nodeAccessRecord = new NodeAccessRecordModel();
    $nodeAccessRecord->setNid($nid);
    $nodeAccessRecord->setFallback(1);
    $nodeAccessRecord->setGid($gid);
    $nodeAccessRecord->setGrantDelete($grantDelete);
    $nodeAccessRecord->setGrantUpdate($grantUpdate);
    $nodeAccessRecord->setGrantView(1);
    $nodeAccessRecord->setLangcode($langcode);
    $nodeAccessRecord->setRealm($realm);

    return $nodeAccessRecord;
  }

}
