<?php

namespace Drupal\views_entity_reference;

use Drupal\Core\Database\Connection;
use Drupal\Core\Field\BaseFieldDefinition;

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
    return $schema = $this->database->schema()
      ->tableExists("entity_references_{$entity_type}");
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
      foreach ($field_list as $field_name => $field_info) {
        $sd = $field_storage_definitions_for_entity_type[$field_name];
        if ($sd && !($sd instanceof BaseFieldDefinition)) {
          $settings = $sd->getSettings();
          if (isset($settings['target_type']) && $settings['target_type'] == $entity_type) {
            $table_name = $entity_type_id . '__' . $field_name;
            $table_query = "SELECT bundle, deleted, entity_id, revision_id, langcode, delta, {$field_name}_target_id AS target_id, ('$entity_type_id' COLLATE utf8mb4_unicode_ci ) AS entity_type
            FROM {" . $table_name . "}";
            if (!$union_query) {
              $union_query = $table_query;
            }
            else {
              $union_query .= " UNION " . $table_query;
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
  
}