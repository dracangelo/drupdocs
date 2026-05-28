<?php

declare(strict_types=1);

namespace Drupal\permissions_by_entity\Cache;

use Drupal\Core\Access\AccessResult;
use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Entity\EntityInterface;

/**
 * Service class for cached PbE access results.
 */
class AccessResultCache {

  /**
   * The default cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $cache;

  /**
   * {@inheritdoc}
   */
  public function __construct(CacheBackendInterface $cache) {
    $this->cache = $cache;
  }

  /**
   * Cache the given access result for the given entity and account.
   *
   * @param int $accountId
   *   The ID of the account to cache the access result for.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to cache the access result for.
   * @param \Drupal\Core\Access\AccessResult $accessResult
   *   The access result to cache.
   */
  public function setAccessResultsCache(int $accountId, EntityInterface $entity, AccessResult $accessResult): void {
    $data = \serialize($accessResult);
    $cid = 'permissions_by_entity:access_result_cache:' . $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $accountId;

    $tags = [
      'permissions_by_entity:access_result_cache:' . $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $accountId,
      'permissions_by_entity:access_result_cache:' . $entity->getEntityTypeId() . ':' . $entity->id(),
      'permissions_by_entity:access_result_cache:' . $entity->getEntityTypeId(),
      'permissions_by_entity:access_result_cache',
    ];

    $tags = Cache::mergeTags($tags, [$cid]);

    $this->cache->set($cid, $data, Cache::PERMANENT, $tags);

    $staticCache = &drupal_static(__FUNCTION__ . $cid, NULL);
    $staticCache = $data;
  }

  /**
   * Get the cached access result for the given account and entity.
   *
   * @param int $accountId
   *   The ID of the account to get the access result for.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to get the access result for.
   *
   * @return \Drupal\Core\Access\AccessResult
   *   The cached access result.
   */
  public function getAccessResultsCache(int $accountId, EntityInterface $entity): AccessResult {
    $cid = 'permissions_by_entity:access_result_cache:' . $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $accountId;

    $staticCache = &drupal_static(__FUNCTION__ . $cid, NULL);

    if ($staticCache) {
      return \unserialize($staticCache);
    }

    $result = $this->cache->get($cid);

    $data = NULL;
    if (isset($result->data)) {
      $data = \unserialize($result->data);
    }

    if (!$data instanceof AccessResult) {
      throw new \Exception("Unexpected result from cache. Passed accountId: $accountId - passed entity: $entity->getEntityTypeId()/$entity->id()");
    }

    return $data;
  }

  /**
   * Check if an access result is cached for the given account and entity.
   *
   * @param int $accountId
   *   The ID of the account to check the cache for.
   * @param \Drupal\Core\Entity\EntityInterface $entity
   *   The entity to check the cache for.
   *
   * @return bool
   *   Whether there is a cached access result or now.
   */
  public function hasAccessResultsCache(int $accountId, EntityInterface $entity): bool {
    $cid = 'permissions_by_entity:access_result_cache:' . $entity->getEntityTypeId() . ':' . $entity->id() . ':' . $accountId;

    $staticCache = &drupal_static(__FUNCTION__ . $cid, NULL);

    if ($staticCache) {
      $data = \unserialize($staticCache);

      if (!$data instanceof AccessResult) {
        return FALSE;
      }

      return TRUE;
    }

    $result = $this->cache->get($cid);

    if (!isset($result->data)) {
      return FALSE;
    }

    $data = \unserialize($result->data);

    if (!$data instanceof AccessResult) {
      return FALSE;
    }

    return TRUE;
  }

}
