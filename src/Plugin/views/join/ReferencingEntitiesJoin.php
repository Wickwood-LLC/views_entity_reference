<?php

namespace Drupal\views_entity_reference\Plugin\views\join;

use Drupal\Core\Database\Query\SelectInterface;
use Drupal\Core\Plugin\PluginBase;
use Drupal\views\Plugin\views\join\JoinPluginBase;

/**
 * Join handler to build UNION of multiple entity reference fields to join with an entity table.
 *
 * @ingroup views_join_handlers
 *
 * @ViewsJoin("referencing_entities")
 */
class ReferencingEntitiesJoin extends JoinPluginBase {

  /**
   * Constructs a Drupal\views\Plugin\views\join\JoinPluginBase object.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition) {
    PluginBase::__construct($configuration, $plugin_id, $plugin_definition);

    $configuration += [
      'type' => 'LEFT',
    ];

    $this->configuration = $configuration;

    $this->entity_type = $configuration['entity_type'];
    $this->type = $configuration['type'];
    $this->entity_table = $configuration['entity_table'];
    $this->entity_id_field = $configuration['entity_id_field'];
  }

  /**
   * {@inheritdoc}
   */
  public function buildJoin($select_query, $table, $view_query) {
    $entity_reference_field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');

    $connection = \Drupal::database();
    $union_query = NULL;
    foreach ($entity_reference_field_map as $entity_type_id => $field_list) {
      $field_storage_definitions_for_entity_type = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      foreach ($field_list as $field_name => $field_info) {
        $sd = $field_storage_definitions_for_entity_type[$field_name];
        if ($sd && !($sd instanceof BaseFieldDefinition)) {
          $settings = $sd->getSettings();
          if (isset($settings['target_type']) && $settings['target_type'] == $this->entity_type) {
            $table_name = $entity_type_id . '__' . $field_name;
            $query = $connection->select($table_name, $table_name)
              ->fields($table_name, ['bundle', 'deleted', 'entity_id', 'revision_id', 'langcode', 'delta']);
            $query->addField($table_name, $field_name . '_target_id', 'target_id');
            $query->addExpression(':entity_type', 'entity_type', array(':entity_type' => $entity_type_id));
            if (!$union_query) {
              $union_query = $query;
            }
            else {
              $union_query->union($query);
            }
          }
        }
      }
    }

    $left_table = $view_query->getTableInfo($this->entity_table);
    $left_field = "$left_table[alias].$this->entity_id_field";

    $condition = "{$left_field} = {$table['alias']}.target_id";

    //$select_query->fields($table['alias'], ['entity_id', 'entity_type', 'bundle']);
    $view_query->addField($table['alias'], 'entity_id');
    $view_query->addField($table['alias'], 'entity_type');
    $view_query->addField($table['alias'], 'bundle');
    $select_query->addJoin($this->type, $union_query, $table['alias'], $condition);
  }

}
