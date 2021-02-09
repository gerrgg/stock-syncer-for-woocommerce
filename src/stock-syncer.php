<?php

define("LOG_FILE_DIR", dirname(__DIR__) . "/logs");

class StockSyncer
{
  // get URL from params
  private $url;
  private $sku_column;
  private $stock_column;

  public $log = "";

  // Set defaults
  private $defaults = [
    "token" => null,
    "file_type" => "xlsx",
    "log" => false,
    "test_mode" => false,
  ];

  // Populate config with passed config array and fill in missing details with defaults
  private $config = [];

  // spreadsheet recieved via curl and saved locally for access
  public $csv = null;

  // the file location of the spreadsheet used for creating/reading and writing files
  private $csv_location = false;

  /**
   * @param string $url
   * @param int $sku_column
   * @param int $stock_column
   * @param array $config [token]
   */
  function __construct($url, $sku_column, $stock_column, $config = [])
  {
    $this->url = $url;
    $this->sku_column = $sku_column;
    $this->stock_column = $stock_column;

    // configure options and fill in missing details with defaults
    $this->configure($config);

    // sets where the datafile is we want to use based on environment variable
    $this->get_file_location();

    // set csv file
    $this->set_CSV();
  }

  /**
   * Setups the default configuration so every detail doesnt need to be defined.
   * @param array $config
   */
  private function configure($config)
  {
    foreach ($this->defaults as $k => $v) {
      $this->config[$k] = isset($config[$k])
        ? $config[$k]
        : $this->defaults[$k];
    }
  }

  public function start_sync()
  {
    $log = "";
    $row_count = $this->csv->getHighestRow();

    for ($i = 2; $i <= $row_count; $i++) {
      $data = $this->get_sku_and_stock_from_csv($i);

      $sku =
        gettype($data["sku"]) !== "string"
          ? $data["sku"]->getPlainText()
          : $data["sku"];

      if ($sku) {
        $id = $this->get_product_id_from_sku($sku);

        if (false !== $data && is_int($data["stock"])) {
          $log .= $this->update_stock($id, $data["stock"], $sku);
        }
      }
    }

    if ($this->config["log"]) {
      $this->log = $log;
    }
  }

  /**
   * Gets spreadsheet from remote server and saves to local directory
   * @return PhpSpreadsheet
   */
  public function set_CSV()
  {
    // this gets a fresh csv file if not in testing mode
    $this->get_CSV();

    $csv = \PhpOffice\PhpSpreadsheet\IOFactory::load($this->csv_location);

    $this->csv = $csv->getActiveSheet();
  }

  private function get_CSV()
  {
    // if not local (testing) then replace csv with new file.
    if ("local" !== getenv("WP_ENVIRONMENT_TYPE")) {
      if (file_exists($this->csv_location)) {
        unlink($this->csv_location);
      }

      $file = $this->get_remote_file();
      $this->save_to_file($file);
    }
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
    // Prevents test data from being overwritten.
    if ("local" === getenv("WP_ENVIRONMENT_TYPE")) {
      return false;
    }

    if (file_exists($this->csv_location)) {
      return time() - filemtime($this->csv_location) > 60 * 60 * 24;
    }

    // if doesnt exist, return true
    return true;
  }

  /**
   * Uses CURL to get remote file and pass cookie token
   */
  private function get_remote_file()
  {
    $curl = curl_init();

    curl_setopt($curl, CURLOPT_URL, $this->url);
    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);

    if (isset($this->config["token"])) {
      curl_setopt($curl, CURLOPT_COOKIE, $this->config["token"]);
    }

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

  public function update_stock($id = false, $stock, $sku = "")
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

    return sprintf(" - %s stock updated to %s\n", $sku, $stock);
  }

  public function get_log_file_path()
  {
    return LOG_FILE_DIR .
      "/" .
      date("Ymd") .
      "-" .
      getenv("WP_ENVIRONMENT_TYPE") .
      ".txt";
  }

  public function write_to_log_file($log)
  {
    $path = $this->get_log_file_path();

    // make dir if not exists
    if (!is_dir(LOG_FILE_DIR)) {
      mkdir(LOG_FILE_DIR);
    }

    if (!file_exists($path)) {
      // make file
      touch($path);

      // open to write
      $fp = fopen($path, "w");
    } else {
      // open to append
      $fp = fopen($path, "a");
    }

    if (!empty($log)) {
      fwrite($fp, time() . "\n" . $log . "\n");
    }

    fclose($fp);
  }
}
