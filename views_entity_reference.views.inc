<?php

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Implements hook_views_data().
 */
function views_entity_reference_views_data() {
  $data = [];

  $entityTypeManager =  \Drupal::entityTypeManager();
  $enabled_entity_references = array_filter(\Drupal::config('views_entity_reference.settings')->get('enabled_entity_references'));
  foreach ($enabled_entity_references as $entity_type_id) {
    $entity_type = $entityTypeManager->getDefinition($entity_type_id);
    $view_name = 'entity_references_' . $entity_type_id;
    $data[$view_name]['table']['group'] = t('Referencing media entities');
    $data[$view_name]['target_id'] = [
      'title' => t('@entity_type_label ID', ['@entity_type_label' => $entity_type->getLabel()]),
      'help' => t('Entity ID of referenced @entity_type_label entity.', ['@entity_type_label' => $entity_type->getLabel()]),
      'field' => [
        'id' => 'standard',
      ],
    ];
    $data[$view_name]['entity_id'] = [
      'title' => t('Entity ID'),
      'help' => t('Entity ID of referencing entity.'),
      'field' => [
        'id' => 'standard',
      ]
    ];
    $data[$view_name]['entity_type'] = [
      'title' => t('Entity type'),
      'help' => t('Entity type of referencing entity.'),
      'field' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'views_entity_reference_entity_type',
      ],
    ];
    $data[$view_name]['bundle'] = [
      'title' => t('Entity bundle'),
      'help' => t('Entity bundle of referencing entity.'),
      'field' => [
        'id' => 'standard',
      ]
    ];

    $data[$view_name]['table']['join']['media_field_data'] = [
      'left_table' => 'media_field_data',
      'left_field' => 'mid',
      'field' => 'target_id',
    ];

    $data[$view_name]['entity_view_url'] = [
      'title' => t('Referencing entity URL'),
      'help' => t('Referencing entity URL'),
      'field' => [
        'id' => 'referencing_entity_url',
      ],
      'sort' => [
        'id' => 'standard',
      ],
      'filter' => [
        'id' => 'string',
      ],
      'argument' => [
        'id' => 'string',
      ],
    ];
  }

  return $data;
}
