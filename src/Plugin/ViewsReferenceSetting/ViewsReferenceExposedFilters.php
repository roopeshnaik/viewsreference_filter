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

/**
 * The views reference setting plugin for exposed filters, for editors.
 *
 * @ViewsReferenceSetting(
 *   id = "exposed_filters",
 *   label = @Translation("Exposed Filters - editor view"),
 *   default_value = "",
 * )
 */
class ViewsReferenceExposedFilters extends PluginBase implements ContainerFactoryPluginInterface {

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
    $exposed = FALSE;

    // Set the filterset.
    $form_field['filter_options'] = [
      '#type' => 'details',
      '#title' => t("Filter Options"),
      '#open' => TRUE,
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
      foreach ($view->$type as $id => $handler) {
        if ($handler->canExpose() && $handler->isExposed()) {
          $exposed = TRUE;
          $form_field['filter_options']['header_text_' . $id] = [
            '#type' => 'item',
            '#markup' => ($handler->options['expose']['label']) ? $handler->options['expose']['label'] : $handler->adminLabel(),
            '#prefix' => '<h3>',
            '#suffix' => '</h3>',
          ];
          $form_field['filter_options']['show_on_page_' . $id] = [
            '#type' => 'checkbox',
            '#title' => $this->t('Expose this filter to visitors, to allow them to change it'),
            '#default_value' => (isset($current_values['filter_options']['show_on_page_' . $id]) && $current_values['filter_options']['show_on_page_' . $id]),
          ];

          $handler->buildExposedForm($form_field['filter_options'], $form_state);

          if ($info = $handler->exposedInfo()) {
            if (isset($form_field['filter_options'][$info['value']])) {
              // Method buildExposedForm() gets rid of element titles, unless
              // type is 'checkbox'. So restore it if missing.
              if (empty($form_field['filter_options'][$info['value']]['#title'])) {
                $form_field['filter_options'][$info['value']]['#title'] = $this->t('@label', ['@label' => $info['label']]);
              }
              // Manually change the input type for datetime.
              if ($handler->pluginId == 'datetime') {
                $form_field['filter_options'][$info['value']]['#type'] = 'date';
              }

              // Manually set default values, until we don't handle these
              // properly from form_state.
              // @todo: use (Sub)FormState to handle default_value.
              if (isset($current_values['filter_options'][$info['value']])) {
                if ($form_field['filter_options'][$info['value']]['#type'] == 'entity_autocomplete') {
                  // Set values for taxonomy autocomplete field.
                  $taxonomy_auto_default = $this->viewsUtility->buildAutocompleteTerms($current_values['filter_options'][$info['value']]);
                  $form_field['filter_options'][$info['value']]['#default_value'] = $taxonomy_auto_default;
                }
                else {
                  $form_field['filter_options'][$info['value']]['#default_value'] = $current_values['filter_options'][$info['value']];
                }
                $form_field['filter_options'][$info['value']]['#description'] = $handler->options['expose']['description'];
              }
            }
          }
        }
      }
    }
    // Hide fieldset if no exposed filters.
    $form_field['filter_options']['#access'] = $exposed;
  }

  /**
   * {@inheritdoc}
   */
  public function alterView(ViewExecutable $view, $values) {
    // Get exposed filter visibility, and remove configuration.
    if (!empty($values) && is_array($values)) {
      $filter_options = $values['filter_options'];
      unset($values['filter_options']);
      if (!empty($filter_options) && is_array($filter_options)) {
        // @todo: Handle without a for loop.
        // Separate out the show on page values.
        $show_filters_values = [];
        foreach ($filter_options as $index => $value) {
          if ((strpos($index, 'show_on_page') === 0)) {
            $show_filters_values[$index] = $value;
          }
          elseif ((strpos($index, 'header_text') === FALSE)) {
            $values[$index] = $value;
          }
        }
        // Get view filters.
        $set_filter = FALSE;
        $view_filters = $view->display_handler->getOption('filters');
        foreach ($show_filters_values as $index => $show_filter) {
          // Get the filter name.
          $index = str_replace('show_on_page_', '', $index);
          // Set exposed filter.
          $view_filters[$index]['exposed'] = $show_filter;

          // Set values of the filter.
          // @todo: Refactor switch case.
          if (isset($view_filters[$index]['plugin_id'])) {
            switch ($view_filters[$index]['plugin_id']) {
              case 'taxonomy_index_tid':
                // Set the filter values for taxonomy autocomplete.
                if (!empty($values[$index])) {
                  if ($view_filters[$index]['type'] == 'textfield') {
                    // Set the filter values for taxonomy autocomplete.
                    $view_filters[$index]['value'] = array_column($values[$index], 'target_id');
                  }
                  else {
                    $view_filters[$index]['value'] = $values[$index];
                  }
                }
                break;

              case 'numeric':
              case 'datetime':
                // Set for numeric and date values.
                if (!empty($values[$index])) {
                  $view_filters[$index]['value']['value'] = $values[$index];
                }
                else {
                  if (!$show_filter) {
                    unset($view_filters[$index]);
                  }
                }
                break;

              case 'boolean':
                // Handle the boolean values.
                // @todo: Handling boolean field 'All' values for view filters.
                $view_filters[$index]['value'] = $values[$index];
                if ($view_filters[$index]['value'] == 'All' && !$show_filter) {
                  unset($view_filters[$index]);
                }
                break;

              default:
                // Set default.
                if (!empty($values[$index])) {
                  $view_filters[$index]['value'] = $values[$index];
                }
                break;
            }
          }
          $set_filter = TRUE;
        }
        // Set views filters with new values.
        if ($set_filter) {
          $view->display_handler->setOption('filters', $view_filters);
        }
        else {
          // Force exposed filters form to not display when rendering the view.
          $view->display_handler->setOption('exposed_block', TRUE);
        }
      }
    }
  }

}
