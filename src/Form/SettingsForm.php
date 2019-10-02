<?php

namespace Drupal\views_entity_reference\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Entity\ContentEntityInterface;
use Drupal\Core\Field\BaseFieldDefinition;

/**
 * Defines a form to configure maintenance settings for this site.
 */
class SettingsForm extends ConfigFormBase {

  /** 
   * Config settings.
   *
   * @var string
   */
  const SETTINGS = 'views_entity_reference.settings';

  /**
   * {@inheritdoc}
   */
  public function getFormID() {
    return 'view_entity_reference_settings';
  }

  /** 
   * {@inheritdoc}
   */
  protected function getEditableConfigNames() {
    return [
      static::SETTINGS,
    ];
  }

  public static function getEntityTypeOptions() {
    $options = [];
    $entity_definitions =  \Drupal::entityTypeManager()->getDefinitions();
    foreach ($entity_definitions as $entity_type_id => $entity_type) {
      if (!$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
        continue;
      }

      $options[$entity_type_id] = $entity_type->getLabel();
    }

    $entity_types_having_referenced_fields = [];

    $entity_reference_field_map = \Drupal::service('entity_field.manager')->getFieldMapByFieldType('entity_reference');
    $union_query = NULL;
    foreach ($entity_reference_field_map as $entity_type_id => $field_list) {
      $field_storage_definitions_for_entity_type = \Drupal::service('entity_field.manager')->getFieldStorageDefinitions($entity_type_id);
      foreach ($field_list as $field_name => $field_info) {
        $sd = $field_storage_definitions_for_entity_type[$field_name];
        if ($sd && !($sd instanceof BaseFieldDefinition)) {
          $settings = $sd->getSettings();
          if (isset($settings['target_type'])) {
            $entity_types_having_referenced_fields[] = $settings['target_type'];
          }
        }
      }
    }
    $entity_types_having_referenced_fields = array_unique($entity_types_having_referenced_fields);

    return array_intersect_key($options, array_flip($entity_types_having_referenced_fields));
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $config = $this->config(static::SETTINGS);
    $form['enabled_entity_references'] = array(
      '#type' => 'checkboxes',
      '#title' => t('Enabled entity references'),
      '#default_value' => $config->get('enabled_entity_references'),
      '#options' => static::getEntityTypeOptions(),
      '#description' => t('Select entity to which you would like to get relationship available in views'),
    );

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $this->configFactory->getEditable(static::SETTINGS)
      ->set('enabled_entity_references', $form_state->getValue('enabled_entity_references'))
      ->save();

    $view_manager = \Drupal::service('views_entity_reference.sql_view_manager');

    // $selected_entity_types = array_filter($form_state->getValue('enabled_entity_references'));

    foreach ($form_state->getValue('enabled_entity_references') as $entity_type => $selected) {
      if ($selected && !$view_manager->viewExists($entity_type)) {
        $created = $view_manager->createView($entity_type);
        if ($created) {
          \Drupal::messenger()->addMessage(t('Entity reference SQL view for @entity_type has been created.', ['@entity_type' => $entity_type]));
        }
        else {
          \Drupal::messenger()->addMessage(t('Entity reference SQL view for @entity_type was not created.', ['@entity_type' => $entity_type]));
        }
      }
      else if (!$selected && $view_manager->viewExists($entity_type)) {
        $view_manager->deleteView($entity_type);
        \Drupal::messenger()->addMessage(t('Entity reference SQL view for @entity_type has been deleted.', ['@entity_type' => $entity_type]));
      }
    }

    parent::submitForm($form, $form_state);
  }

}
