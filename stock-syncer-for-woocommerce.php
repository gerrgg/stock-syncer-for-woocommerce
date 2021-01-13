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
  add_action("ssfwchelly_hansent_sync_hook", "ssfwc_helly_hansen_sync_exec");

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
  // add todays date to csv
  $url = $_ENV["API_URL"] . date("Y-m-d");

  // helly hansen requires api key and exports file as .xlsx
  $config = ["token" => $_ENV["API_KEY"], "file_type" => "xlsx"];

  $sync = new StockSyncer($url, 9, 13, $config);

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
