<?php

/**
 * Class SampleTest
 *
 * @package Test_Plugin
 */

require_once __DIR__ . "/../src/stock-syncer.php";
require_once __DIR__ . "/test-helper.php";

require_once __DIR__ . "/../../woocommerce/woocommerce.php";

/**
 * Sample test case.
 */
class StockSyncerTest extends WP_UnitTestCase
{
  public function setUp()
  {
    parent::setUp();

    // for testing
    putenv("WP_ENVIRONMENT_TYPE=local");

    // setup url
    $url = $_ENV["API_URL"] . date("Y-m-d");
    $config = ["token" => $_ENV["API_KEY"], "file_type" => "xlsx"];

    // set globals
    $GLOBALS["sync"] = new StockSyncer($url, 1, 2, $config);
    $GLOBALS["helper"] = new TestHelper();

    global $helper;

    $GLOBALS["product_id"] = $helper->create_product([
      "post_title" => "Test regex",
      "post_content" => "somestuff",
      "post_type" => "product",
      "meta_input" => ["_sku" => "70030_200-2XL", "_stock" => 50],
    ]);
  }

  public function test_sync_get_token()
  {
    global $sync;

    $this->assertStringContainsString($_ENV["API_KEY"], $sync->get_token());
  }

  public function test_sync_uses_test_data_during_tests()
  {
    global $sync;

    $file_location = $sync->get_file_location();

    $this->assertStringContainsString("tests/test-data.xlsx", $file_location);
  }

  public function test_sync_uses_test_data_during_production()
  {
    global $sync;

    putenv("WP_ENVIRONMENT_TYPE=production");

    $this->assertEquals("production", getenv("WP_ENVIRONMENT_TYPE"));

    $file_location = $sync->get_file_location();

    $this->assertStringContainsString("/data.xlsx", $file_location);
  }

  public function test_sync_can_get_remote_file_with_token()
  {
    global $sync;

    $this->assertNotEmpty($sync->csv);
  }

  public function test_sync_returns_product_data_by_sku()
  {
    global $sync;

    global $product_id;

    $productStock = get_post_meta($product_id, "_stock", true);

    $this->assertEquals($productStock, 50);
  }

  public function test_sync_updates_stock()
  {
    global $sync;

    global $product_id;

    $productStockBefore = get_post_meta($product_id, "_stock", true);

    $sync->update_stock($product_id, $productStockBefore + 1);

    $productStockAfter = get_post_meta($product_id, "_stock", true);

    $this->assertNotEquals($productStockBefore, $productStockAfter);

    $this->assertEquals($productStockAfter, $productStockBefore + 1);
  }

  public function test_sync_updates_stock_in_loop_using_csv()
  {
    global $sync;

    global $product_id;

    $productStockBefore = get_post_meta($product_id, "_stock", true);

    $sync->start_sync();

    $productStockAfter = get_post_meta($product_id, "_stock", true);

    $this->assertNotEquals($productStockBefore, $productStockAfter);
  }

  public function test_sync_works_with_portwest_as_well()
  {
    putenv("WP_ENVIRONMENT_TYPE=production");

    global $helper;

    $product_id = $helper->create_product([
      "post_title" => "Portwest product",
      "post_content" => "somestuff",
      "post_type" => "product",
      "meta_input" => [
        "_sku" => "2886CGR30",
        "_stock" => 900,
      ],
    ]);

    $url = "http://asm.portwest.us/downloads/sohUS.csv";

    $sync = new StockSyncer($url, 2, 9, ["file_type" => "csv"]);

    // get stock from csv file
    $data = $sync->get_sku_and_stock_from_csv(2);

    $sync->start_sync();

    $stockAfterSync = get_post_meta($product_id, "_stock", true);

    $this->assertEquals($data["stock"], $stockAfterSync);
  }
}
