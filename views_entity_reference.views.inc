<?php

use Drupal\Core\Entity\ContentEntityType;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Implements hook_views_data().
 */
function views_entity_reference_views_data() {
  $data = [];
  $data['referencing_entities']['table']['group'] = t('Referencing entities');
  $data['referencing_entities']['entity_id'] = [
    'title' => t('Entity ID'),
    'help' => t('Entity ID of referencing entity.'),
    'field' => [
      'id' => 'standard',
    ]
  ];
  return $data;
}

/**
 * Implements hook_views_data_alter().
 */
function views_entity_reference_views_data_alter(&$data) {
  $entity_definitions =  \Drupal::entityTypeManager()->getDefinitions();
  foreach ($entity_definitions as $entity_type_id => $entity_type) {
    if (!$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
      continue;
    }

    $base_table = $entity_type->getDataTable() ?: $entity_type->getBaseTable();
    $args = ['@entity_type' => $entity_type_id, '@entity_type_label' => $entity_type->getLabel()];

    $data[$base_table]['table']['join']['referencing_entities'] = [
      
    ];

    $data[$base_table]['referencing_entities'] = [
      'title' => t('Entities referencing @entity_type', $args),
      'help' => t('Entities referencing @entity_type through one of its entity references fields.', $args),
      'relationship' => [
        'group' => t('Entity reference', $args),
        'label' => t('Entities referencing @entity_type', $args),
        'relationship field' => $entity_type->getKey('id'),
        'id' => 'referencing_entities',
      ],
    ];
  }
}