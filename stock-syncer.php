<?php

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

class StockSyncer
{
  private $url = "";
  private $token = "";

  public $spreadsheet = null;

  function __construct()
  {
    $this->url = $_ENV["API_URL"];
    $this->token = $_ENV["API_KEY"];
  }

  /**
   * Gets spreadsheet from remote server and saves to local directory
   * @return PhpSpreadsheet
   */
  public function get_CSV()
  {
    
    if ($this->data_is_old()) {
      $xlsx = $this->get_remote_file();
      $this->save_to_file($xlsx);
    } else {
      $this->set_CSV($this->file_location);
    }

    return $this->spreadsheet;
  }

  public function get_todays_date(){
    return date('Y-m-d');
  }

  /**
   * Returns a cell's value by coordinates
   * @param int $x - the row number of the cell
   * @param int $y - the column number of the cell
   * @return string|int
   */
  public function get_cell($x, $y)
  {
    return $this->spreadsheet->getCellByColumnAndRow($y, $x)->getValue();
  }

  /**
   * Sets the spreadsheet file location and returns spreadsheet
   * @param string $location - the path to the spreadsheet file
   * @return PhpSpreadsheet
   */
  public function set_CSV($location)
  {
    $this->file_location = $location;

    $spreadsheet = \PhpOffice\PhpSpreadsheet\IOFactory::load(
      $this->file_location
    );

    $this->spreadsheet = $spreadsheet->getActiveSheet();

    return $this->spreadsheet;
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
   * Checks if the file located at $this->file_location  is older than 1 day
   * @return bool
   */
  public function data_is_old()
  {
    return time() - filemtime($this->file_location) > 60 * 60 * 24;
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
    $fp = fopen($this->file_location, "w");
    fwrite($fp, $file);
    fclose($fp);
  }

  public function get_path_to_wp_config()
  {
    $base = implode("/", array_slice(explode("/", __DIR__), 0, 7));
    return $base + "/wp-config.php";
  }

  /**
   * Extracts the SKU and stock from row number
   * @param int $row
   * @return array [string, string]
   */
  public function get_remote_product_from_row($row)
  {
    return [
      "sku" => $this->get_cell($row, $this->sku_column),
      "stock" => $this->get_cell($row, $this->stock_column),
    ];
  }

  /**
   * Returns total number of rows in spreadsheet
   * @return int
   */
  public function get_row_count()
  {
    return $this->spreadsheet->getHighestRow();
  }

  /**
   * Returns product ID if sku is found in database
   * @param string $sku
   * @param int $product_id
   */

  public function get_product_id_by_sku($sku = false)
  {
    if (!$sku) {
      return null;
    }

    $wpdb = new mysqli(DB_HOST, DB_USER, DB_PASSWORD, DB_NAME);

    $product_id = $wpdb->get_var(
      $wpdb->prepare(
        "SELECT post_id FROM $wpdb->postmeta WHERE meta_key='_sku' AND meta_value='%s' LIMIT 1",
        $sku
      )
    );
    return $product_id;
  }
}
