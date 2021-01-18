<?php

/**
 * Plugin Name:       Stock Syncer for Woocommerce
 * Plugin URI:        https://example.com/plugins/stock-syncer-for-woocommerce/
 * Description:       A simple dashboard widget for updating product stock on mass with nothing but a SKU and a STOCK.
 * Version:           0.1
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            gregbast1994
 * Author URI:        https://gregbastianelli.com/
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       stock-syncer-for-woocommerce
 * Domain Path:       /languages
 **/

// Load Dependancies
require_once __DIR__ . "/vendor/autoload.php";

// Load Environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// If site uses woocommerce
if (
  in_array(
    "woocommerce/woocommerce.php",
    apply_filters("active_plugins", get_option("active_plugins"))
  )
) {
  // get the stock syncer
  require_once __DIR__ . "/src/stock-syncer.php";

  // register deactivation hook
  register_deactivation_hook(__FILE__, "ssfwc_clear_cron_jobs");

  /**
   * Schedual portwest and helly hansen sync twice daily.
   */
  add_action("ssfwc_portwest_sync_hook", "ssfwc_portwest_sync_exec");
  add_action("ssfwc_helly_hansen_sync_hook", "ssfwc_helly_hansen_sync_exec");

  if (!wp_next_scheduled("ssfwc_portwest_sync_hook")) {
    wp_schedule_event(time(), "twicedaily", "ssfwc_portwest_sync_hook");
  }

  if (!wp_next_scheduled("ssfwc_helly_hansen_sync_hook")) {
    wp_schedule_event(time(), "twicedaily", "ssfwc_helly_hansen_sync_hook");
  }
}

/**
 * Sync with portwest stock twice daily
 */
function ssfwc_portwest_sync_exec()
{
  $sync = new StockSyncer($_ENV["PORTWEST_URL"], 2, 9, ["file_type" => "csv"]);

  $sync->start_sync();
}

/**
 * Sync with Helly Hansen stock twice daily
 */
function ssfwc_helly_hansen_sync_exec()
{
  // login to remote API, send the raw POST request body as string
  $token = ssfwc_get_login_token(
    $_ENV["HH_LOGIN_URL"],
    sprintf(
      "user[username]=%s&user[password]=%s",
      $_ENV["HH_USERNAME"],
      $_ENV["HH_PASSWORD"]
    )
  );

  // add todays date to endpoint
  $url = $_ENV["HH_API_URL"] . date("Y-m-d");

  // helly hansen requires api key and exports file as .xlsx
  $sync = new StockSyncer($url, 9, 13, [
    "token" => $token,
    "file_type" => "xlsx",
  ]);

  $sync->start_sync();
}

/**
 * Cleans up cron jobs on deactivation
 */
function ssfwc_clear_cron_jobs()
{
  $timestamp = wp_next_scheduled("ssfwc_portwest_sync_hook");
  wp_unschedule_event($timestamp, "ssfwc_portwest_sync_hook");

  $timestamp = wp_next_scheduled("ssfwc_helly_hansen_sync_hook");
  wp_unschedule_event($timestamp, "ssfwc_helly_hansen_sync_hook");
}

/**
 * Uses CURL to login programically to api with a url and form string
 */
function ssfwc_get_login_token($url, $post_string)
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
