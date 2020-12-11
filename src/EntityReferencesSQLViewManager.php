<?php

namespace Drupal\views_entity_reference;

use Drupal\Core\Database\Connection;
use Drupal\Core\Field\BaseFieldDefinition;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\field\Entity\FieldStorageConfig;
use Drupal\Core\Entity\Sql\SqlContentEntityStorageException;

class EntityReferencesSQLViewManager {
  /**
   * The database service.
   *
   * @var \Drupal\Core\Database\Connection
   */
  protected $database;

  public function __construct(Connection $database) {
    $this->database = $database;
  }

  /**
   * Check if SQL view exists for given entity type.
   * @param string $entity_type
   * @return boolean
   */
  public function viewExists($entity_type) {
    $exists = $this->database->schema()
      ->tableExists("entity_references_{$entity_type}");
    return $exists;
  }

  /**
   * Create SQL view for entity references for give entity type.
   * @param string $entity_type
   */
  public function createView($entity_type) {
    $entity_reference_field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    $union_query = NULL;
    foreach ($entity_reference_field_map as $entity_type_id => $field_list) {
      $field_storage_definitions_for_entity_type = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      foreach ($field_storage_definitions_for_entity_type as $field_name => $field_info) {
        $sd = $field_storage_definitions_for_entity_type[$field_name];
        if ($sd && !($sd instanceof BaseFieldDefinition) && $sd->get('type') == 'entity_reference') {
          $settings = $sd->getSettings();
          if (isset($settings['target_type']) && $settings['target_type'] == $entity_type) {
            try {
              // $table_name = $entity_type_id . '__' . $field_name;
              $table_mapping = \Drupal::entityTypeManager()->getStorage($entity_type_id)->getTableMapping();
              $table_name = $table_mapping->getFieldTableName($field_name);

              $table_query = "SELECT bundle, deleted, entity_id, revision_id, langcode, delta, {$field_name}_target_id AS target_id, ('$entity_type_id' COLLATE utf8mb4_unicode_ci ) AS entity_type
              FROM {" . $table_name . "}";
              if (!$union_query) {
                $union_query = $table_query;
              }
              else {
                $union_query .= " UNION " . $table_query;
              }
            }
            catch (SqlContentEntityStorageException $e) {
              // TODO: Do something to fined correct table name?
            }
          }
        }
      }
    }
    if ($union_query) {
      $view_name = "entity_references_" . $entity_type;
      $view_query = "CREATE VIEW $view_name AS " . $union_query;

      $this->database->query($view_query);
      return TRUE;
    }
    return FALSE;
  }

  /**
   * Delete if SQL view exists for given entity type.
   * @param string $entity_type
   */
  public function deleteView($entity_type) {
    // Follow code suppose to be standard way, but it did not work.
    // return $schema = $this->database->schema()
    //   ->dropTable("entity_references_{$entity_type}");

    // May be it is  MySQL specific?
    return $this->database->query("DROP VIEW  IF EXISTS {entity_references_" . $entity_type . "}");
  }

  public static function getContentEntityTypes() {
    $entity_types =& drupal_static(__FUNCTION__);
    if (!$entity_types) {
      $entity_types = [];
      $entity_definitions =  \Drupal::entityTypeManager()->getDefinitions();
      foreach ($entity_definitions as $entity_type_id => $entity_type) {
        if (!$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
          continue;
        }

        $entity_types[] = $entity_type_id;
      }
    }

    return $entity_types;
  }

  public function getCurrentEntityReferenceFieldMap($reset = FALSE) {
    $current_entity_reference_fields =& drupal_static(__FUNCTION__);
    if (!$current_entity_reference_fields || $reset) {
      $entity_types = static::getContentEntityTypes();

      $entity_field_manager = \Drupal::service('entity_field.manager');

      if ($reset) {
        $entity_field_manager->clearCachedFieldDefinitions();
      }

      $entity_reference_field_map = $entity_field_manager->getFieldMapByFieldType('entity_reference');
      $current_entity_reference_fields = array_flip($entity_types);

      array_walk($current_entity_reference_fields, function(&$item){
        $item = [];
      });

      foreach ($entity_reference_field_map as $entity_type_id => $field_list) {
        $field_storage_definitions_for_entity_type = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
        foreach ($field_list as $field_name => $field_info) {
          $sd = $field_storage_definitions_for_entity_type[$field_name];
          if ($sd && !($sd instanceof BaseFieldDefinition)) {
            $settings = $sd->getSettings();
            if (isset($settings['target_type'])) {
              $current_entity_reference_fields[$settings['target_type']][] = $field_name;
            }
          }
        }
      }
    }
    return $current_entity_reference_fields;
  }

  /**
   * Get list of entity types tat need its SQL VIEWs to be refreshed.
   * @return array
   */
  public function getRefreshList() {

    $entity_types = static::getContentEntityTypes();
    $current_entity_reference_fields = $this->getCurrentEntityReferenceFieldMap(TRUE);

    $saved_entity_reference_fields = \Drupal::config('views_entity_reference.settings')->get('entity_reference_fields');
    if (empty($saved_entity_reference_fields)) {
      $saved_entity_reference_fields = array_flip($entity_types);
      array_walk($saved_entity_reference_fields, function(&$item){
        $item = [];
      });
    }

    $entity_type_needs_refresh = [];

    foreach ($current_entity_reference_fields as $entity_type_id => $fields) {
      if (!empty(array_diff($current_entity_reference_fields[$entity_type_id], $saved_entity_reference_fields[$entity_type_id])) ||
          !empty(array_diff($saved_entity_reference_fields[$entity_type_id], $current_entity_reference_fields[$entity_type_id]))
      ) {
        $entity_type_needs_refresh[] = $entity_type_id;
      }
    }

    /**
      * Re-create SQL VIEWs for the entity references fields as necessary.
      */
    return $entity_type_needs_refresh;
  }

  public function refresh(FieldStorageConfig $new_field_storage_config = NULL) {
    $entity_type_needs_refresh = $this->getRefreshList();
    if ($new_field_storage_config) {
      $settings = $new_field_storage_config->get('settings');
      if (isset($settings['target_type']) && !in_array($settings['target_type'], $entity_type_needs_refresh))
      $entity_type_needs_refresh[] = $settings['target_type'];
    }
    if (!empty($entity_type_needs_refresh)) {
      foreach ($entity_type_needs_refresh as $entity_type_id) {
        $this->deleteView($entity_type_id);
        $this->createView($entity_type_id);
      }
      $current_entity_reference_fields = $this->getCurrentEntityReferenceFieldMap(TRUE);

      \Drupal::service('config.factory')
        ->getEditable('views_entity_reference.settings')
        ->set('entity_reference_fields', $current_entity_reference_fields)
        ->save();
    }
  }
}
