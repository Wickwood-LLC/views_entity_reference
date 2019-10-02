<?php

namespace Drupal\views_entity_reference\Plugin\views\filter;

use Drupal\Core\Entity\EntityManagerInterface;
use Drupal\Core\Entity\EntityTypeBundleInfoInterface;
use Drupal\views\ViewExecutable;
use Drupal\views\Plugin\views\display\DisplayPluginBase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\views\Plugin\views\filter\InOperator;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Entity\ContentEntityInterface;

/**
 * Filter class which allows filtering by entity bundles.
 *
 * @ingroup views_filter_handlers
 *
 * @ViewsFilter("views_entity_reference_entity_type")
 */
class EntityType extends InOperator {

  /**
   * The entity type manager service.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * Constructs a Bundle object.
   *
   * @param array $configuration
   *   A configuration array containing information about the plugin instance.
   * @param string $plugin_id
   *   The plugin_id for the plugin instance.
   * @param mixed $plugin_definition
   *   The plugin implementation definition.
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager service.
   */
  public function __construct(array $configuration, $plugin_id, $plugin_definition, EntityTypeManagerInterface $entity_type_manager) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);

    $this->entityTypeManager = $entity_type_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function init(ViewExecutable $view, DisplayPluginBase $display, array &$options = NULL) {
    parent::init($view, $display, $options);
  }

  /**
   * {@inheritdoc}
   */
  public function getValueOptions() {
    if (!isset($this->valueOptions)) {
      $entity_definitions =  $this->entityTypeManager->getDefinitions();
      foreach ($entity_definitions as $entity_type_id => $entity_type) {
        if (!$entity_type->entityClassImplements(ContentEntityInterface::class) || !$entity_type->getBaseTable()) {
          continue;
        }

        $options[$entity_type_id] = $entity_type->getLabel();
      }

      array_multisort($options, SORT_ASC, SORT_REGULAR, array_keys($options));
      $this->valueOptions = $options;
    }

    return $this->valueOptions;
  }

  /**
   * {@inheritdoc}
   */
  public function query() {
    // Make sure that the entity base table is in the query.
    $this->ensureMyTable();
    parent::query();
  }
}
