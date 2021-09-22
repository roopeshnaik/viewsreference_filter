<?php

namespace Drupal\viewsreference_filter;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\views\ViewExecutableFactory;
use Psr\Log\LoggerInterface;

/**
 * The Views Reference Filter Utility Class.
 */
class ViewsRefFilterUtility implements ViewsRefFilterUtilityInterface {

  /**
   * A LoggerChannelInterface viewsreference_filter.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected $logger;

  /**
   * The entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected $entityTypeManager;

  /**
   * The views executable factory.
   *
   * @var \Drupal\views\ViewExecutableFactory
   */
  protected $viewsExecutableFactory;

  /**
   * Constructs a new ViewsRefFilterUtility object.
   *
   * @param \Psr\Log\LoggerInterface $logger
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   * @param \Drupal\views\ViewExecutableFactory $viewsExecutableFactory
   */
  public function __construct(LoggerInterface $logger, EntityTypeManagerInterface $entity_type_manager, ViewExecutableFactory $viewsExecutableFactory) {
    $this->logger = $logger;
    $this->entityTypeManager = $entity_type_manager;
    $this->viewsExecutableFactory = $viewsExecutableFactory;
  }

  /**
   * {@inheritdoc}
   */
  public function loadView($view_name, $display_id) {
    static $view;

    if (!isset($view)) {
      if (!empty($view_name) && !empty($display_id)) {
        try {
          $view = $this->entityTypeManager
            ->getStorage('view')
            ->load($view_name);
          $view = $this->viewsExecutableFactory->get($view);
          $view->setDisplay($display_id);
          $view->initHandlers();
        }
        catch (\Exception $e) {
          $message = "Exception:" . $e;
        }
      }
      else {
        $message = "Either the Views Name: '" . $view_name . "' ";
        $message .= "or Dispay Id: '" . $display_id . "' were not set.";
      }
    }

    // Log error $message if isset.
    if (isset($message)) {
      $this->logger->notice($message);
    }

    return $view;
  }

}
