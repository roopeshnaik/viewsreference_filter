<?php

namespace Drupal\viewsreference_filter\Plugin\ViewsReferenceSetting;

use Drupal\Component\Plugin\PluginBase;
use Drupal\Core\Annotation\Translation;
use Drupal\Core\Form\FormState;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\views\ViewExecutable;
use Drupal\viewsreference\Annotation\ViewsReferenceSetting;
use Drupal\viewsreference_filter\ViewsRefFilterUtilityInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\viewsreference\Plugin\ViewsReferenceSettingInterface;

/**
 * The views reference setting plugin for exposed filters, for editors.
 *
 * @ViewsReferenceSetting(
 *   id = "exposed_filters",
 *   label = @Translation("Exposed Filters - editor view"),
 *   default_value = "",
 * )
 */
class ViewsReferenceExposedFilters extends PluginBase implements ViewsReferenceSettingInterface, ContainerFactoryPluginInterface {

  use StringTranslationTrait;

  /**
   * The factory to load a view executable with.
   *
   */
  protected $viewsUtility;

  /**
   * TaxonomyLookup constructor.
   *
   * @param array $configuration
   *   The configuration.
   * @param string $pluginId
   *   The plugin_id for the plugin instance.
   * @param mixed $pluginDefinition
   *   The plugin implementation definition.
   * @param \Drupal\viewsreference_filter\ViewsRefFilterUtilityInterface $viewsUtility
   *   The views reference filter utility.
   */
  public function __construct(
    array $configuration,
    $pluginId,
    $pluginDefinition,
    ViewsRefFilterUtilityInterface $viewsUtility
  ) {
    parent::__construct($configuration, $pluginId, $pluginDefinition);
    $this->viewsUtility = $viewsUtility;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $pluginId, $pluginDefinition) {
    return new static(
      $configuration,
      $pluginId,
      $pluginDefinition,
      $container->get('viewsreference_filter.views_utility')
    );
  }

  /**
   * {@inheritdoc}
   */
  public function alterFormField(&$form_field) {

    $view = $this->viewsUtility->loadView($this->configuration['view_name'],
      $this->configuration['display_id']);
    if (!$view) {
      $form_field = [];
      return;
    }

    $current_values = $form_field['#default_value'];
    unset($form_field['#default_value']);
    $form_field['#type'] = 'container';
    $form_field['#tree'] = TRUE;
    $form_field['vr_exposed_filters_visible'] = [
      '#type' => 'checkbox',
      '#title' => $this->t('Show Filters on Page'),
      '#default_value' => (isset($current_values['vr_exposed_filters_visible']) && $current_values['vr_exposed_filters_visible']),
    ];

    // Some plugin may look into current exposed input to change some behaviour,
    // i.e. setting a default value (see SHS for an example). So set current
    // values as exposed input.
    $view->setExposedInput($current_values);

    $form_state = (new FormState())
      ->setStorage([
        'view' => $view,
        'display' => $view->display_handler->display,
      ]);

    // Let form plugins know this is for exposed widgets.
    // @see ViewExposedForm::buildForm()
    $form_state->set('exposed', TRUE);
    // Go through each handler and let it generate its exposed widget.
    // @see ViewExposedForm::buildForm()
    foreach ($view->display_handler->handlers as $type => $value) {
      /** @var \Drupal\views\Plugin\views\HandlerBase $handler */
      foreach ($view->$type as $handler) {
        if ($handler->canExpose() && $handler->isExposed()) {
          $handler->buildExposedForm($form_field, $form_state);

          if ($info = $handler->exposedInfo()) {
            if (isset($form_field[$info['value']])) {
              // Method buildExposedForm() gets rid of element titles, unless
              // type is 'checkbox'. So restore it if missing.
              if (empty($form_field[$info['value']]['#title'])) {
                $form_field[$info['value']]['#title'] = $this->t('@label', ['@label' => $info['label']]);
              }

              if (empty($form_field[$info['value']]['#description']) && !empty($info['description'])) {
                $form_field[$info['value']]['#description'] = $info['description'];
              }

              // Manually set default values, until we don't handle these
              // properly from form_state.
              // @todo: use (Sub)FormState to handle default_value.
              if (isset($current_values[$info['value']])) {
                $form_field[$info['value']]['#default_value'] = $current_values[$info['value']];
              }
            }
          }
        }
      }
    }
  }

  /**
   * {@inheritdoc}
   */
  public function alterView(ViewExecutable $view, $values)
  {
    // Get exposed filter visibility, and remove configuration.
    $vrExposedFiltersVisible = FALSE;
    if (isset($values['vr_exposed_filters_visible'])) {
      $vrExposedFiltersVisible = $values['vr_exposed_filters_visible'];
      unset($values['vr_exposed_filters_visible']);
    }

    if (!empty($values) && is_array($values)) {
      $view_filters = $view->display_handler->getOption('filters');
      $filters = [];
      $filters_by_id = [];
      foreach ($view_filters as $key => $filter) {
        if (isset($filter['expose']['identifier'])) {
          $filters_by_id[$filter['expose']['identifier']] = $key;
        }
      }
      foreach ($values as $index => $value) {
        if (!empty($value) && isset($filters_by_id[$index])) {
          $filters[$index] = $value;
        }
      }
      if ($filters) {
        $view->setExposedInput($filters);
      }
    }

    if (!$vrExposedFiltersVisible) {
      // Force exposed filters form to not display when rendering the view.
      $view->display_handler->setOption('exposed_block', TRUE);
    }
    else {
      $view->display_handler->setOption('exposed_block', FALSE);
    }
  }

}
