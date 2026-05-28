<?php

declare(strict_types=1);

namespace Drupal\permissions_by_entity\Service;

use Drupal\Core\Config\ConfigFactoryInterface;
use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityFieldManagerInterface;
use Drupal\Core\Entity\FieldableEntityInterface;
use Drupal\Core\Language\LanguageManagerInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\permissions_by_entity\Event\EntityFieldValueAccessDeniedEvent;
use Drupal\permissions_by_entity\Event\PermissionsByEntityEvents;
use Drupal\permissions_by_term\Service\AccessCheck;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * Contains functions to check access permissions on a given entity.
 *
 * @package Drupal\permissions_by_entity\Service
 */
class AccessChecker extends AccessCheck implements AccessCheckerInterface {

  /**
   * The event dispatcher.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  private $eventDispatcher;

  /**
   * The cache for checked entities.
   *
   * @var \Drupal\permissions_by_entity\Service\CheckedEntityCache
   */
  private $checkedEntityCache;

  /**
   * The config factory service.
   *
   * @var \Drupal\Core\Config\ConfigFactoryInterface
   */
  private ConfigFactoryInterface $configFactory;

  /**
   * The entity field value access denied event.
   *
   * @var \Drupal\permissions_by_entity\Event\EntityFieldValueAccessDeniedEvent
   */
  private $event;

  /**
   * Constructs the AccessChecker.
   */
  public function __construct(
    EventDispatcherInterface $event_dispatcher,
    CheckedEntityCache $checked_entity_cache,
    EntityFieldManagerInterface $entityFieldManager,
    Connection $database,
    ConfigFactoryInterface $configFactory,
    LanguageManagerInterface $languageManager,
    AccountProxyInterface $currentUser,
  ) {
    parent::__construct($database, $event_dispatcher, $entityFieldManager, $languageManager, $currentUser, $configFactory);
    $this->eventDispatcher = $event_dispatcher;
    $this->checkedEntityCache = $checked_entity_cache;
    $this->configFactory = $configFactory;

    $this->event = new EntityFieldValueAccessDeniedEvent();
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessAllowed(FieldableEntityInterface $entity, $uid = FALSE): bool {
    $permissions_by_term_settings = $this->configFactory->get('permissions_by_term.settings');
    $config_permission_mode = $permissions_by_term_settings->get('permission_mode');
    $config_require_all_terms_granted = $permissions_by_term_settings->get('require_all_terms_granted');

    $access_allowed = FALSE;
    if (!$config_permission_mode && !$config_require_all_terms_granted) {
      $access_allowed = TRUE;
    }

    // Iterate over the fields the entity contains.
    foreach ($entity->getFields() as $field) {

      // We only need to check for entity reference fields
      // which references to a taxonomy term.
      if (
        $field->getFieldDefinition()->getType() == 'entity_reference' &&
        $field->getFieldDefinition()->getSetting('target_type') == 'taxonomy_term'
      ) {

        // Iterate over each referenced taxonomy term.
        /** @var \Drupal\Core\Field\FieldItemInterface $item */
        foreach ($field->getValue() as $item) {
          $access_allowed = !empty($item['target_id']) &&
            $this->isAccessAllowedByDatabase($item['target_id'], $uid, $entity->language()->getId());

          if (!$access_allowed && $config_require_all_terms_granted) {
            return $access_allowed;
          }
          if ($access_allowed && !$config_require_all_terms_granted) {
            return $access_allowed;
          }
        }
      }

      // Check if the field contains another fieldable entity,
      // that we need to check.
      if (isset($field->entity) && $field->entity instanceof FieldableEntityInterface) {

        // We need to iterate over the entities.
        $num_values = $field->count();
        if ($num_values > 0) {

          // Iterate over the field values.
          for ($i = 0; $i < $num_values; $i++) {

            // Get the value of the current field index.
            $field_value = $field->get($i);

            // If the value is null or empty we continue with the next index of
            // the loop.
            if (!$field_value) {
              continue;
            }

            // If the field entity is null we also continue with the next index
            // of the loop.
            if (!isset($field_value->entity)) {
              continue;
            }

            // Get the field entity.
            $field_entity = $field_value->entity;

            // It is possible, that the referenced field entity creates a
            // circular dependency to the current entity. This will cause
            // memory limit exhausted errors because there is no way out for
            // the script. To avoid this, we need to be able to imagine if we
            // already checked this field entity before. If so, we ignore this
            // field entity, if not we can securely do a recursive call.
            //
            // Using own method to avoid "max nesting level error" trying to
            // check if the field entity is stored in the entitiesChecked array.
            if ($this->checkedEntityCache->isChecked($field_entity)) {
              continue;
            }
            else {
              // Add the current entity to the list of checked entities.
              $this->checkedEntityCache->add($field_entity);
            }

            // Do a recursive call to check if the user is allowed to access
            // this entity.
            if (!$this->isAccessAllowed($field_entity, $uid)) {

              // Dispatch an event to allow subscribers
              // to do something in this case.
              $this->event->setIndex($i);
              $this->event->setField($field);
              $this->event->setEntity($field_entity);
              $this->event->setUid($uid);

              $this->eventDispatcher
                ->dispatch(
                  $this->event,
                  PermissionsByEntityEvents::ENTITY_FIELD_VALUE_ACCESS_DENIED_EVENT
                );
              $i = $this->event->getIndex();
            }
          }
        }
      }
    }

    return $access_allowed;
  }

  /**
   * {@inheritdoc}
   */
  public function isAccessControlled(FieldableEntityInterface $entity, bool $clearCache = TRUE): bool {
    $permissions_by_term_settings = $this->configFactory->get('permissions_by_term.settings');

    if ($clearCache) {
      $this->checkedEntityCache->clear();
    }

    if ($entity->getEntityTypeId() == 'node') {
      // Make sure to leave nodes to the permissions_by_term module.
      return FALSE;
    }

    // Iterate over the fields the entity contains.
    foreach ($entity->getFields() as $field) {
      // We only need to check for entity reference fields
      // which references to a taxonomy term.
      $field_definition = $field->getFieldDefinition();
      if (
        $field_definition->getType() === 'entity_reference' &&
        $field_definition->getSetting('target_type') === 'taxonomy_term'
      ) {
        $target_bundles = $permissions_by_term_settings->get('target_bundles');
        $config_permission_mode = $permissions_by_term_settings->get('permission_mode');
        $field_target_bundles = $field_definition->getSetting('handler_settings')['target_bundles'] ?? [];

        if (
          is_countable($target_bundles) &&
          count($target_bundles) > 0 &&
          count(array_intersect($field_target_bundles, $target_bundles)) > 0
        ) {
          // Permission mode is turned on, so any taxonomy field
          // that is valid to be targeted is automatically controlled.
          if ($config_permission_mode) {
            return TRUE;
          }

          // Iterate over each referenced taxonomy term.
          /** @var \Drupal\Core\Field\FieldItemInterface $item */
          foreach ($field->getValue() as $item) {
            if (
              !empty($item['target_id']) &&
              $this->isAnyPermissionSetForTerm($item['target_id'], $entity->language()->getId())
            ) {
              return TRUE;
            }
          }
        }
      }

      // Check if the field contains another fieldable entity,
      // that we need to check.
      if (isset($field->entity) && $field->entity instanceof FieldableEntityInterface) {

        // We need to iterate over the entities.
        $num_values = $field->count();
        if ($num_values > 0) {

          // Iterate over the field values.
          for ($i = 0; $i < $num_values; $i++) {

            // Get the value of the current field index.
            $field_value = $field->get($i);

            // If the value is null or empty we continue with the next index of
            // the loop.
            if (!$field_value) {
              continue;
            }

            // If the field entity is null we also continue with the next index
            // of the loop.
            if (!isset($field_value->entity)) {
              continue;
            }

            // Get the field entity.
            $field_entity = $field_value->entity;

            // It is possible, that the referenced field entity creates a
            // circular dependency to the current entity. This will cause
            // memory limit exhausted errors because there is no way out for
            // the script. To avoid this, we need to be able to imagine if we
            // already checked this field entity before. If so, we ignore this
            // field entity, if not we can securely do a recursive call.
            //
            // Using own method to avoid "max nesting level error" trying to
            // check if the field entity is stored in the entitiesChecked array.
            if ($this->checkedEntityCache->isChecked($field_entity)) {
              continue;
            }
            else {
              // Add the current entity to the list of checked entities.
              $this->checkedEntityCache->add($field_entity);
            }

            // Do a recursive call to check if the user is allowed to access
            // this entity.
            if ($this->isAccessControlled($field_entity, FALSE)) {
              return TRUE;
            }
          }
        }
      }
    }

    return FALSE;
  }

}
