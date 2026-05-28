<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Cache;

use Drupal\Core\Cache\CacheTagsInvalidator;

/**
 * Service to invalidate certain cache tags.
 */
class CacheInvalidator {

  /**
   * The cache tags invalidator.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidator
   */
  private $cacheTagsInvalidator;

  /**
   * Constructs a new CacheInvalidator.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidator $cacheTagsInvalidator
   *   The cache tags invalidator.
   */
  public function __construct(CacheTagsInvalidator $cacheTagsInvalidator) {
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
  }

  /**
   * Invalidates a predefined list of cache tags.
   */
  public function invalidate(): void {
    $this->cacheTagsInvalidator->invalidateTags([
      'search_index:node_search',
      'permissions_by_term:access_result_cache',
      'permissions_by_term:key_value_cache',
    ]);
  }

}
