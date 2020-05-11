<?php

namespace Drupal\viewsreference_filter;

/**
 * Interface ViewsRefFilterUtilityInterface.
 */
interface ViewsRefFilterUtilityInterface {

  /**
   * @param $view_name
   * @param $display_id
   *
   * @return mixed
   */
  public function loadView($view_name, $display_id);

}
