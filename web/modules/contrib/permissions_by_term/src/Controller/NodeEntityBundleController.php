<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Entity\EntityFieldManager;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Url;
use Drupal\node\Entity\Node;
use Drupal\path_alias\AliasManagerInterface;
use Drupal\permissions_by_term\Service\NodeEntityBundleInfo;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Controller with endpoints for AJAX requests regarding entity bundles.
 *
 * @package Drupal\permissions_by_term\Controller
 */
final class NodeEntityBundleController extends ControllerBase {

  /**
   * The entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  private EntityFieldManagerInterface $entityFieldManager;

  /**
   * The node entity bundle info.
   *
   * @var \Drupal\permissions_by_term\Service\NodeEntityBundleInfo
   */
  private NodeEntityBundleInfo $nodeEntityBundleInfo;

  /**
   * Path alias manager.
   *
   * @var \Drupal\path_alias\AliasManagerInterface
   */
  protected AliasManagerInterface $pathAliasManager;

  /**
   * The current request stack.
   *
   * @var \Symfony\Component\HttpFoundation\RequestStack
   */
  protected $requestStack;

  /**
   * {@inheritdoc}
   */
  public function __construct(EntityFieldManager $entityFieldManager, NodeEntityBundleInfo $nodeEntityBundleInfo, AliasManagerInterface $path_alias_manager, RequestStack $requestStack) {
    $this->entityFieldManager = $entityFieldManager;
    $this->nodeEntityBundleInfo = $nodeEntityBundleInfo;
    $this->pathAliasManager = $path_alias_manager;
    $this->requestStack = $requestStack;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_field.manager'),
      $container->get('permissions_by_term.node_entity_bundle_info'),
      $container->get('path_alias.manager'),
      $container->get('request_stack')
    );
  }

  /**
   * Gets PbT-controlled fields and permissions for a content type.
   *
   * @param string $nodeType
   *   The node type to check for.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response, JSON-formatted.
   */
  public function getFormInfoByContentType($nodeType) {
    $fields = $this->entityFieldManager->getFieldDefinitions('node', $nodeType);

    $fieldNames = [];
    foreach ($fields as $field) {
      $fieldDefinitionSettings = $field->getSettings();
      if (!empty($fieldDefinitionSettings['target_type']) && $fieldDefinitionSettings['target_type'] == 'taxonomy_term') {
        $fieldNames[] = $field->getFieldStorageDefinition()->getName();
      }
    }

    return new JsonResponse(
      [
        'taxonomyRelationFieldNames' => $fieldNames,
        'permissions'                => $this->nodeEntityBundleInfo->getPermissions(),
      ]
    );
  }

  /**
   * Gets PbT-controlled fields and permissions for the current URL.
   *
   * @return \Symfony\Component\HttpFoundation\JsonResponse
   *   The response, JSON-formatted.
   */
  public function getFormInfoByUrl() {
    $contentType = $this->getContentType($this->requestStack->getCurrentRequest()->query->get('url'));

    if ($contentType === NULL) {
      return new JsonResponse([]);
    }

    $fields = $this->entityFieldManager->getFieldDefinitions('node', $contentType);

    $fieldNames = [];
    foreach ($fields as $field) {
      $fieldDefinitionSettings = $field->getSettings();
      if (!empty($fieldDefinitionSettings['target_type']) && $fieldDefinitionSettings['target_type'] == 'taxonomy_term') {
        $fieldNames[] = $field->getFieldStorageDefinition()->getName();
      }
    }

    return new JsonResponse(
      [
        'taxonomyRelationFieldNames' => $fieldNames,
        'permissions'                => $this->nodeEntityBundleInfo->getPermissions(),
      ]
    );
  }

  /**
   * Gets the content type based on the given path.
   */
  private function getContentType($nodeEditPath) {
    $alias = $this->pathAliasManager->getPathByAlias($nodeEditPath);
    $params = Url::fromUri("internal:" . $alias)->getRouteParameters();
    $entity_type = key($params);
    $node = $this->entityTypeManager()->getStorage($entity_type)->load($params[$entity_type]);

    if ($node instanceof Node) {
      return $node->getType();
    }

    return NULL;
  }

}
