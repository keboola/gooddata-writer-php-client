<?php
/**
 * Created by PhpStorm.
 * User: JakubM
 * Date: 17.02.14
 * Time: 13:21
 */

namespace Keboola\Writer\GoodData;

require_once 'config.php';

class WaitTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var \Keboola\Writer\GoodData\Client
	 */
	protected $client;

	public function setUp()
	{
		$this->client = new \Guzzle\Http\Client(WAIT_GOODDATA_WRITER_API_URL, array(
			'request.options' => array(
				'headers' => array(
					'X-StorageApi-Token' => WAIT_STORAGE_API_TOKEN
				)
			)
		));
	}

	public function testUploadTable()
	{
		$start = time();
		$request = $this->client->post('/gooddata-writer/upload-table', array(), json_encode(array(
			'writerId' => WAIT_WRITER_ID,
			'tableId' => WAIT_TABLE_ID,
			'wait' => 1
		)));
		try {
			$request->send();
		} catch (\Exception $e) {
			$this->assertTrue(false, sprintf('Request execution failed with error: %s. Response was: %s', $e->getMessage(), $request->getResponse()->getBody(true)));

		}

		$this->assertLessThan(WAIT_UPLOAD_TABLE_MAX_LENGTH, time() - $start, 'Waiting for upload-table request has been too long.');
	}

	public function testUploadProject()
	{
		$start = time();
		$request = $this->client->post('/gooddata-writer/upload-project', array(), json_encode(array(
			'writerId' => WAIT_WRITER_ID,
			'wait' => 1
		)));
		try {
			$request->send();
		} catch (\Exception $e) {
			$this->assertTrue(false, sprintf('Request execution failed with error: %s. Response was: %s', $e->getMessage(), $request->getResponse()->getBody(true)));

		}

		$this->assertLessThan(WAIT_UPLOAD_PROJECT_MAX_LENGTH, time() - $start, 'Waiting for upload-project request has been too long.');
	}

	public function testExecuteReports()
	{
		$start = time();
		$request = $this->client->post('/gooddata-writer/execute-reports', array(), json_encode(array(
			'writerId' => WAIT_WRITER_ID,
			'pid' => WAIT_PROJECT_ID,
			'wait' => 1
		)));
		try {
			$request->send();
		} catch (\Exception $e) {
			$this->assertTrue(false, sprintf('Request execution failed with error: %s. Response was: %s', $e->getMessage(), $request->getResponse()->getBody(true)));

		}

		$this->assertLessThan(WAIT_EXECUTE_REPORTS_MAX_LENGTH, time() - $start, 'Waiting for execute-reports request has been too long.');
	}
} 