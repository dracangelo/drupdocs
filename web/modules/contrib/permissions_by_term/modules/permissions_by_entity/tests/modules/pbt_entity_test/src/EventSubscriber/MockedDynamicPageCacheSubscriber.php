<?php

declare(strict_types=1);

namespace Drupal\pbt_entity_test\EventSubscriber;

use Drupal\Core\Cache\CacheableMetadata;
use Drupal\dynamic_page_cache\EventSubscriber\DynamicPageCacheSubscriber;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;

/**
 * Mocked dynamic page cache subscriber.
 *
 * Ensures the requests are cached.
 */
class MockedDynamicPageCacheSubscriber extends DynamicPageCacheSubscriber {

  /**
   * {@inheritdoc}
   */
  public function onRequest(RequestEvent $event): void {
    // Sets the response for the current route, if cached.
    $cached = $this->cache->get(['response'], (new CacheableMetadata())->setCacheContexts($this->cacheContexts));
    if ($cached) {
      $response = $cached->data;
      $response->headers->set(self::HEADER, 'HIT');
      $event->setResponse($response);
    }
  }

  /**
   * {@inheritdoc}
   */
  public function onResponse(ResponseEvent $event): void {
    /** @var \Drupal\Core\Cache\CacheableResponseInterface $response */
    $response = $event->getResponse();

    // Embed the response object in a render array so that RenderCache is able
    // to cache it, handling cache redirection for us.
    $cacheableMetadata = CacheableMetadata::createFromObject($response->getCacheableMetadata());
    $this->cache->set(
      ['response'],
      $response,
      $cacheableMetadata->addCacheContexts($this->cacheContexts),
      (new CacheableMetadata())->setCacheContexts($this->cacheContexts)
    );

    // The response was generated, mark the response as a cache miss. The next
    // time, it will be a cache hit.
    $response->headers->set(self::HEADER, 'MISS');
  }

}
