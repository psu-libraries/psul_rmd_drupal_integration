<?php

/**
 * @file
 * Hooks provided by the RMD Integration module.
 */

use Drupal\node\NodeInterface;

/**
 * @addtogroup hooks
 * @{
 */

/**
 * Allow prevent RMD data from being added to a node.
 *
 * RMD data is only available for Faculty.  If we already know which nodes are
 * not faculty profiles then we can skip the RMD data fetch.
 *
 * @param bool $skip_node
 *   Boolean to indicate if node should be skipped.
 * @param \Drupal\node\NodeInterface $node
 *   The node being processed.
 */
function hook_psul_rmd_drupal_integration_preprocess_node_alter(&$skip_node, NodeInterface $node) {
  if (!$node->hasField('field_employee_type')) {
    return;
  }

  $field_employee_type = $node->get('field_employee_type')->value;
  if (!str_contains($field_employee_type, 'Faculty')) {
    $skip_node = TRUE;
  }
}
