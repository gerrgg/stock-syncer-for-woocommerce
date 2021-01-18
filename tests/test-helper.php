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

  /**
   * Uses CURL to login programically to api with a url and form string
   */
  public function get_login_token($url, $post_string)
  {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_POST, 1);
    curl_setopt($curl, CURLOPT_POSTFIELDS, $post_string);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    $result = curl_exec($curl);

    if (empty($result)) {
      return false;
    }

    curl_close($curl);

    return json_decode($result)->token;
  }
}
