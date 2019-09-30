<?php

namespace Drupal\views_entity_reference\Plugin\views\row;

use Drupal\views\Views;
use Drupal\Core\Form\FormStateInterface;
use Drupal\views\Plugin\views\row\RowPluginBase;
use Drupal\views_entity_reference\Plugin\views\relationship\ReferencingEntitiesRelationship;

/**
 * EntityReference row plugin.
 *
 * @ingroup views_row_plugins
 *
 * @ViewsRow(
 *   id = "referencing_entity",
 *   title = @Translation("Referencing entity"),
 *   help = @Translation("Displays the details of referencing entity."),
 *   theme = "views_view_fields",
 *   register_theme = FALSE,
 *   display_types = {"normal"}
 * )
 */
class ReferencingEntity extends RowPluginBase {

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['separator'] = ['default' => '-'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);
    $relationships = $this->view->getDisplay()->getOption('relationships');

    foreach ($relationships as $relationship) {
      $relationship_handler = Views::handlerManager('relationship')->getHandler($relationship);
      if ($relationship_handler instanceof ReferencingEntitiesRelationship) {
        // If this relationship is valid for this type, add it to the list.
        //$data = Views::viewsData()->get($relationship['table']);
        //$base = $data[$relationship['field']]['relationship']['base'];
        //if ($base == $this->base_table) {
          $relationship_handler->init($this->view, $this->displayHandler);
          $relationship_options[$relationship['id']] = $relationship_handler->adminLabel();
        //}
      }
    }

    $form['referencing_entity_relationship'] = [
      '#title' => $this->t('Referencing Entity Relationship'),
      '#type' => 'radios',
      '#options' => $relationship_options,
      '#default_value' => $this->options['referencing_entity_relationship'],
    ];

    // Expand the description of the 'Inline field' checkboxes.
    $form['inline']['#description'] .= '<br />' . $this->t("<strong>Note:</strong> In 'Entity Reference' displays, all fields will be displayed inline unless an explicit selection of inline fields is made here.");
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    if (isset($this->options['referencing_entity_relationship']) && isset($this->view->relationship[$this->options['referencing_entity_relationship']])) {
      $relationship = $this->view->relationship[$this->options['referencing_entity_relationship']];
      //$this->field_alias = $this->view->query->addField($relationship->alias, $this->base_field);
      $this->field_entity_id = $this->view->query->addField($relationship->alias, 'entity_id');
      $this->field_entity_type = $this->view->query->addField($relationship->alias, 'entity_type');
      $this->field_entity_bundle = $this->view->query->addField($relationship->alias, 'bundle');
    }
    // else {
    //   $this->field_alias = $this->view->query->addField($this->base_table, $this->base_field);
    // }
  }

  /**
   * {@inheritdoc}
   */
  public function preRender($result) {
    return parent::preRender($result);
  }

  /**
   * {@inheritdoc}
   */
  public function render($row) {
    $a = $this->themeFunctions();
    return [
      '#theme' => 'views_entity_reference',
      '#referenced_entity_id' => $row->_entity->id(),
      '#referencing_entity_id' => $row->{$this->field_entity_id},
      '#referencing_entity_type' => $row->{$this->field_entity_type},
      '#referencing_entity_bundle' => $row->{$this->field_entity_bundle},
    ];
  }

}
