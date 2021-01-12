<?php

require_once "vendor/autoload.php";

class StockSyncer
{
  // get URL from params
  private $url;

  // setup config object
  private $config = [
    "token" => null,
  ];

  // spreadsheet recieved via curl and saved locally for access
  public $csv = null;

  private $csv_location = false;

  /**
   * @param string $url
   * @param array $config [token]
   */
  function __construct($url, $config = false)
  {
    $this->url = $url;

    if ($config) {
      $this->config = $config;
    }

    // sets where the datafile is we want to use based on environment variable
    $this->get_file_location();

    // set csv file
    $this->set_CSV();

    // loop/sync
  }

  /**
   * Gets spreadsheet from remote server and saves to local directory
   * @return PhpSpreadsheet
   */
  public function set_CSV()
  {
    if ($this->data_is_old()) {
      $file = $this->get_remote_file();
      $this->save_to_file($xlsx);
    }

    $csv = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->csv_location);

    $this->csv = $csv->getActiveSheet();
  }

  public function get_token()
  {
    return $this->config["token"];
  }

  public function get_file_location()
  {
    $this->csv_location =
      "local" === getenv("WP_ENVIRONMENT_TYPE")
        ? __DIR__ . "/../tests/test-data.xlsx"
        : __DIR__ . "/../data.xlsx";

    return $this->csv_location;
  }

  /**
   * Returns a cell's value by coordinates
   * @param int $x - the row number of the cell
   * @param int $y - the column number of the cell
   * @return string|int
   */
  public function get_cell($x, $y)
  {
    return $this->csv->getCellByColumnAndRow($y, $x)->getValue();
  }

  /**
   * Sets the column number of where SKUS are located in the spreadsheet
   * @param int $column
   */
  public function set_sku_column($column)
  {
    $this->sku_column = $column;
  }

  /**
   * Sets the column number of where STOCK is located in the spreadsheet
   * @param int $column
   */
  public function set_stock_column($column)
  {
    $this->stock_column = $column;
  }

  /**
   * Checks if the file located at $this->csv_location  is older than 1 day
   * @return bool
   */
  public function data_is_old()
  {
    return time() - filemtime($this->csv_location) > 60 * 60 * 24;
  }

  /**
   * Uses CURL to get remote file and pass cookie token
   */
  private function get_remote_file()
  {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_COOKIE, $this->token);

    $result = curl_exec($curl);
    curl_close($curl);

    return $result;
  }

  /**
   * Saves passed file to set file location
   * @param PhpSpreadsheet
   */
  private function save_to_file($file)
  {
    $fp = fopen($this->csv_location, "w");
    fwrite($fp, $file);
    fclose($fp);
  }

  /**
   * Returns total number of rows in spreadsheet
   * @return int
   */
  public function get_row_count()
  {
    return $this->csv->getHighestRow();
  }

  /**
   * Returns product data if sku is found in database
   * @param string $sku
   * @param int $product_id
   */

  public function get_product_id_and_stock_by_sku($sku = false)
  {
    if (!$sku) {
      return null;
    }

    global $wpdb;

    $product_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
        $sku
      )
    );

    if ($product_id) {
      $data = [
        "id" => $product_id,
        "stock" => get_post_meta($product_id, "_stock", true),
      ];

      return $data;
    }

    return false;
  }

  public function update_stock($id = false, $stock = false)
  {
    if (false === $id) {
      return;
    }

    // set to manage stock
    update_post_meta($id, "_manage_stock", "yes");

    // set stock level
    update_post_meta($id, "_stock", $stock);

    // set stock status
    update_post_meta(
      $id,
      "_stock_status",
      $stock > 0 ? "instock" : "outofstock"
    );
  }
}
