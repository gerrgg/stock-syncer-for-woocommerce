<?php

/**
 * Test helper
 */
class TestHelper
{
  public function create_product($props)
  {
    $post_id = wp_insert_post($props);
    wp_set_object_terms($post_id, "variation", "product_type");

    return $post_id;
  }
}
