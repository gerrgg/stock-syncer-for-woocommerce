<?php

/**
 * Class SampleTest
 *
 * @package Test_Plugin
 */

require_once __DIR__ . "/../src/stock-syncer.php";
require_once __DIR__ . "/test-helper.php";

require_once __DIR__ . "/../../woocommerce/woocommerce.php";

$GLOBALS["helper"] = new TestHelper();

$GLOBALS["token"] =
  "remember_user_token=" .
  $GLOBALS["helper"]->get_login_token(
    $_ENV["HH_LOGIN_URL"],
    sprintf(
      "user[username]=%s&user[password]=%s",
      $_ENV["HH_USERNAME"],
      $_ENV["HH_PASSWORD"]
    )
  );

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

    global $helper;
    global $token;

    // setup url
    $url = $_ENV["HH_API_URL"] . date("Y-m-d");

    $GLOBALS["sync"] = new StockSyncer($url, 1, 2, ["token" => $token]);

    $GLOBALS["product_id"] = $helper->create_product([
      "post_title" => "Test regex",
      "post_content" => "somestuff",
      "post_type" => "product",
      "meta_input" => ["_sku" => "70030_200-2XL", "_stock" => 50],
    ]);
  }

  public function test_sync_can_login_to_api()
  {
    global $helper;

    $token = $helper->get_login_token(
      $_ENV["HH_LOGIN_URL"],
      sprintf(
        "user[username]=%s&user[password]=%s",
        $_ENV["HH_USERNAME"],
        $_ENV["HH_PASSWORD"]
      )
    );

    $this->assertNotEmpty($token);
  }

  public function test_sync_helly_token_has_remember_user_key()
  {
    global $sync;
    global $token;

    $this->assertStringContainsString(
      "remember_user_token",
      $sync->get_token()
    );
  }

  public function test_sync_uses_test_data_during_tests()
  {
    global $sync;

    $file_location = $sync->get_file_location();

    $this->assertStringContainsString("tests/test-data.xlsx", $file_location);
  }

  public function test_sync_uses_real_data_during_production()
  {
    global $sync;

    putenv("WP_ENVIRONMENT_TYPE=production");

    $this->assertEquals("production", getenv("WP_ENVIRONMENT_TYPE"));

    $file_location = $sync->get_file_location();

    $this->assertStringContainsString("/data.xlsx", $file_location);
  }

  public function test_sync_log_file_is_created_in_test_mode()
  {
    global $token;

    // add todays date to endpoint
    $url = $_ENV["HH_API_URL"] . date("Y-m-d");

    // helly hansen requires api key and exports file as .xlsx
    $sync = new StockSyncer($url, 9, 13, [
      "token" => $token,
      "file_type" => "xlsx",
    ]);

    $this->assertTrue(file_exists(LOG_FILE_DIR));
  }

  public function test_sync_log_file_is_created_in_production_mode()
  {
    global $helper;

    putenv("WP_ENVIRONMENT_TYPE=production");

    global $token;

    $url = $_ENV["HH_API_URL"] . date("Y-m-d");

    $sync = new StockSyncer($url, 9, 13, [
      "token" => $token,
      "file_type" => "xlsx",
      "log" => true,
    ]);

    $path = $sync->get_log_file_path();

    $this->assertStringContainsString("production", $path);
  }

  public function test_sync_works_with_helly_hansen_in_production()
  {
    global $sync;
    global $token;

    putenv("WP_ENVIRONMENT_TYPE=production");

    $url = $_ENV["HH_API_URL"] . date("Y-m-d");

    $sync = new StockSyncer($url, 9, 13, [
      "token" => $token,
      "file_type" => "xlsx",
    ]);

    $csv_location = $sync->get_file_location();

    $fp = fopen($csv_location, "r");
    $data = fread($fp, filesize($csv_location));

    $this->assertFalse($data === "ActionController::UnknownFormat");
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

  public function test_sync_works_with_portwest_in_production()
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
