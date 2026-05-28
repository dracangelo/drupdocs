<?php

declare(strict_types=1);

namespace Drupal\permissions_by_term\Plugin\migrate\destination;

use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\migrate\Plugin\migrate\destination\DestinationBase;
use Drupal\migrate\Plugin\MigrationInterface;
use Drupal\migrate\Row;
use Drupal\permissions_by_term\Service\AccessStorage;
use Drupal\user\UserStorageInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Provides Permissions By Term User destination plugin.
 *
 * Examples:
 *
 * @code
 * process:
 *   uid: uid
 *   tids:
 *    -
 *     plugin: explode
 *     delimiter: ','
 *     source: tids
 *    -
 *     plugin: entity_generate
 *     entity_type: taxonomy_term
 *     bundle_key: vid
 *     bundle: vocabulary
 *     value_key: name
 * destination:
 *   plugin: permissions_by_term_user
 * @endcode
 *
 * @MigrateDestination(
 *   id = "permissions_by_term_user",
 *   requirements_met = true
 * )
 */
final class PermissionsByTermUser extends DestinationBase implements ContainerFactoryPluginInterface {

  /**
   * User storage manager.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * Access storage for permissions.
   *
   * @var \Drupal\permissions_by_term\Service\AccessStorage
   */
  protected $accessStorage;

  /**
   * Entity field manager.
   *
   * @var \Drupal\Core\Entity\EntityFieldManagerInterface
   */
  protected $entityFieldManager;

  /**
   * Constructs a content entity.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin ID for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\migrate\Plugin\MigrationInterface $migration
   *   The migration entity.
   * @param \Drupal\user\UserStorageInterface $user_storage
   *   User storage manager.
   * @param \Drupal\permissions_by_term\Service\AccessStorage $access_storage
   *   The storage for this permission.
   * @param \Drupal\Core\Entity\EntityFieldManagerInterface $entity_field_manager
   *   The entity field manager.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, MigrationInterface $migration, UserStorageInterface $user_storage, AccessStorage $access_storage, EntityFieldManagerInterface $entity_field_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition, $migration);
    $this->userStorage = $user_storage;
    $this->accessStorage = $access_storage;
    $this->entityFieldManager = $entity_field_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition, ?MigrationInterface $migration = NULL) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $migration,
      $container->get('entity_type.manager')->getStorage('user'),
      $container->get('permissions_by_term.access_storage'),
      $container->get('entity_field.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getIds() {
    $ids = [];

    $id_key = $this->userStorage->getEntityType()->getKey('id');
    $entity_type_id = $this->userStorage->getEntityTypeId();
    /** @var \Drupal\Core\Field\FieldStorageDefinitionInterface[] $definitions */
    $definitions = $this->entityFieldManager->getBaseFieldDefinitions($entity_type_id);
    $field_definition = $definitions[$id_key];

    $ids[$id_key] = [
      'type' => $field_definition->getType(),
    ] + $field_definition->getSettings();

    return $ids;
  }

  /**
   * {@inheritdoc}
   */
  public function fields(?MigrationInterface $migration = NULL) {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function import(Row $row, array $old_destination_id_values = []) {
    $destination_values = $row->getDestination();
    if (isset($destination_values['uid']) && isset($destination_values['tids'])) {
      $user = $this->userStorage->load($destination_values['uid']);
      if (isset($user)) {
        // First, we delete existing values from the db.
        $this->accessStorage->deleteAllTermPermissionsByUserId($user->id());

        // For term permissions use user preferred language.
        $langcode = $user->getPreferredLangcode();

        // Second, we insert updated values.
        foreach ($destination_values['tids'] as $tid) {
          if (!empty($tid)) {
            $this->accessStorage->addTermPermissionsByUserIds([$user->id()], $tid, $langcode);
          }
        }

        return [$user->id()];
      }
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function rollback(array $destination_identifier) {
    // Delete the specified entity from Drupal if it exists.
    $user = $this->userStorage->load(reset($destination_identifier));
    if (isset($user)) {
      $this->accessStorage->deleteAllTermPermissionsByUserId($user->id());
    }
  }

}
