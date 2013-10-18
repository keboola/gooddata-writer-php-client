<?php
/**
 * @user Jakub Matejka <jakub@keboola.com>
 * @date 2012-11-26
 *
 */

namespace Keboola\Writer\GoodData;

use Keboola\StorageApi\Client as StorageApiClient,
	Keboola\StorageApi\Table as StorageApiTable;

require_once 'config.php';

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var \Keboola\Writer\GoodData\Client
	 */
	protected $_client;

	public function setUp()
	{
		$this->_client = \Keboola\Writer\GoodData\Client::factory(array(
			'url' => FUNCTIONAL_GOODDATA_WRITER_API_URL,
			'token' => FUNCTIONAL_STORAGE_API_TOKEN
		));
	}

	public function testUploadProject()
	{
		$result = $this->_client->uploadProject(FUNCTIONAL_WRITER_ID);
		$this->assertArrayHasKey('status', $result, "Result of API call 'upload-project' should contain 'status' key");
		$this->assertEquals('ok', $result['status'], "Result of API call 'upload-project' should contain 'status' key with value 'ok'");
		$this->assertArrayHasKey('batch', $result, "Result of API call 'upload-project' should contain 'batch' key");
		$batchId = $result['batch'];

		$jobsFinished = false;
		$i = 1;
		do {
			$jobsInfo = $this->_client->batch(FUNCTIONAL_WRITER_ID, $batchId);
			$this->assertArrayHasKey('batch', $jobsInfo, "Result of API call 'batch' should contain 'batch' key");
			$this->assertArrayHasKey('status', $jobsInfo['batch'], "Result of API call 'batch' should contain 'batch.status' key");
			if (isset($jobsInfo['batch']['status']) && !in_array($jobsInfo['batch']['status'], array('waiting', 'processing'))) {
				$jobsFinished = true;
			}
			if (!$jobsFinished) sleep($i * 10);
			$i++;
		} while(!$jobsFinished);

		$this->assertEquals('success', $jobsInfo['batch']['status'], "Result of 'upload-project' call should be success");
	}


}
