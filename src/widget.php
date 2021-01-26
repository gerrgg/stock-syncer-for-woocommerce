<?php

// Add to WP dashboard - May require changing screen options (top right on dashboard)
add_action("wp_dashboard_setup", "ssfwc_add_dashboard_widget");
function ssfwc_add_dashboard_widget()
{
  /**
   * Setup dashboard widget
   */
  wp_add_dashboard_widget(
    "ssfwc_widget",
    "Stock Syncer for Woocommerce",
    "ssfwc_widget_callback"
  );

  global $wp_meta_boxes;
  $normal_dash = $wp_meta_boxes["dashboard"]["normal"]["core"];
  $custom_dash = [
    "ssfwc_widget" => $normal_dash["ssfwc_widget"],
  ];
  unset($normal_dash["ssfwc_widget"]);
  $sorted_dash = array_merge($custom_dash, $normal_dash);
  $wp_meta_boxes["dashboard"]["normal"]["core"] = $sorted_dash;
}

function ssfwc_widget_callback()
{
  printf(
    '<p><a href="/wp-admin/admin-post.php?action=ssfwc_run_portwest">Run Portwest</a></p>'
  );
  printf(
    '<p><a href="/wp-admin/admin-post.php?action=ssfwc_run_helly_hansen">Run Helly Hansen</a></p>'
  );
}
