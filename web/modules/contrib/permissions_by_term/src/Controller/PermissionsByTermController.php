<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Controller;

use Drupal\Component\Utility\Tags;
use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Default controller for the permissions_by_term module.
 */
final class PermissionsByTermController extends ControllerBase {

  public function __construct(
    private readonly RequestStack $requestStack,
  ) {
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): self {
    return new static(
      $container->get('request_stack')
    );
  }

  /**
   * Returns JSON response for user's autocomplete field in permissions form.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response as JSON.
   */
  public function autoCompleteMultiple(): JsonResponse {
    $request = $this->requestStack->getCurrentRequest();
    $query = $request->query->get('q');

    // The user enters a comma-separated list of users.
    // We only autocomplete the last user.
    $array = Tags::explode($query);

    // Fetch last user.
    $lastString = trim(array_pop($array));

    $matches = [];

    $userIds = $this->entityTypeManager->getStorage('user')->getQuery()
      ->condition('name', $lastString, 'CONTAINS')
      ->accessCheck(FALSE)
      ->execute();

    $prefix = count($array) ? implode(', ', $array) . ', ' : '';

    foreach ($userIds as $userId) {
      $user = $this->entityTypeManager->getStorage('user')->load($userId);
      if ($user) {
        $matches[$prefix . $user->getDisplayName()] = $user->getDisplayName();
      }
    }

    return new JsonResponse($matches);
  }

}
