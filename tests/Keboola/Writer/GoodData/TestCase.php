<?php
/**
 * @user Jakub Matejka <jakub@keboola.com>
 * @date 2012-11-26
 *
 */

namespace Keboola\Writer\GoodData;


class TestCase extends \PHPUnit_Framework_TestCase
{
	/**
	 * @var \Keboola\Writer\GoodData\Client
	 */
	protected $_client;
	/**
	 * @var \Keboola\StorageApi\Client
	 */
	protected $_storageApiClient;

	protected $_writerId;

	protected $_batchId;


	public function setUp()
	{
		$this->_storageApiClient = new \Keboola\StorageApi\Client(STORAGE_API_TOKEN, 'http://connection-rc-awsdevel.keboola.com');

		$this->_client = \Keboola\Writer\GoodData\Client::factory(array(
			'url' => GOODDATA_WRITER_API_URL,
			'token' => STORAGE_API_TOKEN
		));

		$this->_writerId = 'writer1';

	}

	public function testApiInit()
	{
		$this->assertEquals(GOODDATA_WRITER_API_URL, $this->_client->getBaseUrl());
	}

	public function testUploadProject()
	{
		$uploadResult = $this->_client->uploadProject($this->_writerId);

		$this->assertEquals('ok', $uploadResult['status']);
		$this->assertNotEmpty($uploadResult['batch']);
		$this->assertInternalType('array', $uploadResult['jobs']);

		$this->_batchId = $uploadResult['batch'];
	}

	public function testGetBatch()
	{
		$batchResult = $this->_client->batch($this->_batchId, $this->_writerId);

		$this->assertEquals('ok', $batchResult['status']);
		$this->assertInternalType('array', $batchResult['jobs']);
		$this->assertNotEmpty($batchResult['createdTime']);
	}

	public function testGetXml()
	{
		$checkXml = file_get_contents(__DIR__ . '/_data/out.c-main.users.xml');
		$xml = $this->_client->xml('out.c-main.users', $this->_writerId);
		$this->assertXmlStringEqualsXmlString($checkXml, $xml->asXml());
	}

}
