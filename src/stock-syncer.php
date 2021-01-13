<?php

require_once "vendor/autoload.php";

class StockSyncer
{
  // get URL from params
  private $url;
  private $sku_column;
  private $stock_column;

  // setup config object
  private $config = [
    "token" => null,
    "file_type" => "xlsx",
  ];

  // spreadsheet recieved via curl and saved locally for access
  public $csv = null;

  private $csv_location = false;

  /**
   * @param string $url
   * @param int $sku_column
   * @param int $stock_column
   * @param array $config [token]
   */
  function __construct($url, $sku_column, $stock_column, $config = false)
  {
    $this->url = $url;
    $this->sku_column = $sku_column;
    $this->stock_column = $stock_column;

    if ($config) {
      $this->config = $config;
    }

    // sets where the datafile is we want to use based on environment variable
    $this->get_file_location();

    // set csv file
    $this->set_CSV();

    // start_sync
  }

  public function start_sync()
  {
    $row_count = $this->csv->getHighestRow();

    for ($i = 2; $i <= $row_count; $i++) {
      $data = $this->get_sku_and_stock_from_csv($i);

      if ($data["sku"]) {
        $id = $this->get_product_id_from_sku($data["sku"]);

        if (false !== $data) {
          $this->update_stock($id, $data["stock"]);
        }
      }
    }
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
        ? __DIR__ . "/../tests/test-data." . $this->config["file_type"]
        : __DIR__ . "/../data." . $this->config["file_type"];

    return $this->csv_location;
  }

  /**
   * Returns a cell's value by coordinates
   * @param int $x - the row number of the cell
   * @param int $y - the column number of the cell
   * @return string|int
   */
  public function get_sku_and_stock_from_csv($row)
  {
    return [
      "sku" => $this->csv
        ->getCellByColumnAndRow($this->sku_column, $row)
        ->getValue(),

      "stock" => $this->csv
        ->getCellByColumnAndRow($this->stock_column, $row)
        ->getValue(),
    ];
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
   * Returns product data if sku is found in database
   * @param string $sku
   * @param int $product_id
   */

  public function get_product_id_from_sku($sku = false)
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

    return $product_id ? $product_id : false;
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
