<?php

/**
 * Class SampleTest
 *
 * @package Test_Plugin
 */

require_once __DIR__ . '/../stock-syncer.php';

/**
 * Sample test case.
 */
class SampleTest extends WP_UnitTestCase {

	public function setUp() {
		parent::setUp();
		
		$sync = new StockSyncer();
  }
 

	public function test_get_CSV_returns_() {
		global $sync;

		echo wp_get_environment_type();

		$this->assertEquals(1, 1);
  }
}
