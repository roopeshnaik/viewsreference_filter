<?php

namespace Drupal\viewsreference_filter;

/**
 * The Views Reference Filter Utility Interface.
 */
interface ViewsRefFilterUtilityInterface {

  /**
   * Load the view.
   *
   * @param string $view_name
   *   The view id.
   * @param string $display_id
   *   The display id.
   *
   * @return mixed
   *   Return the view.
   */
  public function loadView($view_name, $display_id);

}
