<?php

namespace Drupal\views_entity_reference\Plugin\views\field;

use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Drupal\views\ResultRow;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\field\Standard;
use Drupal\Core\Url;
use Drupal\Core\Form\FormStateInterface;

/**
 * Default implementation of the base field plugin.
 *
 * @ingroup views_field_handlers
 *
 * @ViewsField("referencing_entity_url")
 */
class ReferencingEntityURL extends Standard {
  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);

    $this->additional_fields['entity_id'] = 'entity_id';
    $this->additional_fields['entity_type'] = 'entity_type';
  }

  /**
   * {@inheritdoc}
   */
  protected function defineOptions() {
    $options = parent::defineOptions();
    $options['display_as'] = ['default' => 'link'];

    return $options;
  }

  /**
   * {@inheritdoc}
   */
  public function buildOptionsForm(&$form, FormStateInterface $form_state) {
    parent::buildOptionsForm($form, $form_state);

    $display_as = $this->options['display_as'];
    $form['display_as'] = [
      '#type' => 'radios',
      '#title' => $this->t('Create a label'),
      '#options' => [
        'link' => $this->t('Link'),
      ],
      '#default_value' => $display_as,
      '#weight' => -200,
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    $this->ensureMyTable();
    $this->addAdditionalFields();
    //$this->field_alias = $this->table . '_' . $this->field;
  }

  public function render(ResultRow $values) {
    
    $entity_id = $this->getValue($values, 'entity_id');
    $entity_type = $this->getValue($values, 'entity_type');
    if ($entity_type && $entity_type) {
      $url = Url::fromUri("entity:{$entity_type}/{$entity_id}");

      return array(
        '#type' => 'link',
        '#title' => t('View'),
        '#url' => $url,
      );
    }
  }
}
