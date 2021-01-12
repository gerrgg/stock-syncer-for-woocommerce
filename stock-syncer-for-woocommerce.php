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

require_once "vendor/autoload.php";

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();
