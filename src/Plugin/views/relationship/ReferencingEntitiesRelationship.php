<?php

namespace Drupal\views_entity_reference\Plugin\views\relationship;

use Drupal\views\Plugin\views\relationship\RelationshipPluginBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\Plugin\views\HandlerBase;
use Drupal\views\Views;

/**
 * Relationship handler to bring relationship to a UNION of all entity reference fields.
 *
 * @ingroup views_relationship_handlers
 *
 * @ViewsRelationship("referencing_entities")
 */
class ReferencingEntitiesRelationship extends RelationshipPluginBase {

  /**
   * Overrides \Drupal\views\Plugin\views\HandlerBase::init().
   *
   * Init handler to let relationships live on tables other than
   * the table they operate on.
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Figure out what base table this relationship brings to the party.
    $table_data = Views::viewsData()->get($this->definition['base']);
    //$base_field = empty($this->definition['base field']) ? $table_data['table']['base']['field'] : $this->definition['base field'];

    $this->ensureMyTable();

    $def = $this->definition;
    $def['entity_type'] = $this->definition['entity_type'];
    $def['adjusted'] = TRUE;
    $def['table'] = $this->table;
    $def['field'] = $this->field;
    if (!empty($this->options['required'])) {
      $def['type'] = 'INNER';
    }

    if (!empty($this->definition['extra'])) {
      $def['extra'] = $this->definition['extra'];
    }

    $join = Views::pluginManager('join')->createInstance('referencing_entities', $def);

    // use a short alias for this:
    $alias = 'referencing_' . $this->definition['entity_type'];

    $this->alias = $this->query->addRelationship($alias, $join, $this->table, $this->relationship);

    // Add access tags if the base table provide it.
    if (empty($this->query->options['disable_sql_rewrite']) && isset($table_data['table']['base']['access query tag'])) {
      $access_tag = $table_data['table']['base']['access query tag'];
      $this->query->addTag($access_tag);
    }
  }

}
