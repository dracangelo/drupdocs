<?php

namespace Drupal\permissions_by_term\Cache;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\CacheBackendInterface;

/**
 *
 */
class AccessResultCache {

  /**
   * The default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  /**
   *
   */
  public function setAccessResultsCache(int $accountId, $entityId, AccessResult $accessResult): void {
    $data = \serialize($accessResult);
    $cid = 'permissions_by_term:access_result_cache:' . $entityId . ':' . $accountId;

    $this->cache->set($cid, $data);
  }

  /**
   *
   */
  public function getAccessResultsCache(int $accountId, $entityId): AccessResult {
    $cid = 'permissions_by_term:access_result_cache:' . $entityId . ':' . $accountId;

    $staticCache = $this->cache->get($cid);

    if (is_string($staticCache)) {
      return \unserialize($staticCache, ['allowed_classes' => [AccessResult::class]]);
    }

    $data = NULL;
    if (isset($staticCache->data)) {
      $data = \unserialize($staticCache, ['allowed_classes' => [AccessResult::class]]);
    }

    if (!$data instanceof AccessResult) {
      throw new \Exception("Unexpected result from cache. Passed accountId: $accountId - passed entityId: $entityId");
    }

    return $data;
  }

  /**
   *
   */
  public function hasAccessResultsCache(int $accountId, $entityId): bool {
    $cid = 'permissions_by_term:access_result_cache:' . $entityId . ':' . $accountId;

    $staticCache = $this->cache->get($cid);

    if (is_string($staticCache)) {
      $data = \unserialize($staticCache, ['allowed_classes' => [AccessResult::class]]);
      return $data instanceof AccessResult;
    }

    $result = $this->cache->get($cid);

    if (!isset($result->data)) {
      return FALSE;
    }

    $data = \unserialize($result->data, ['allowed_classes' => [AccessResult::class]]);
    return $data instanceof AccessResult;
  }

}
