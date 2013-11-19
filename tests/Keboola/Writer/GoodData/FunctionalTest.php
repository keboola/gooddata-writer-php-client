<?php
/**
 * @user Jakub Matejka <jakub@keboola.com>
 * @date 2012-11-26
 *
 */

namespace Keboola\Writer\GoodData;


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

		foreach ($this->_client->getWriters() as $writer) {
			if ($writer['id'] != FUNCTIONAL_WRITER_ID) {
				$this->_client->deleteWriter($writer['id']);
			}
		}
	}

	public function testUploadProject()
	{
		$result = $this->_client->uploadProject(FUNCTIONAL_WRITER_ID);
		$this->assertArrayHasKey('batch', $result, "Result of API call 'upload-project' should contain 'batch' key");
		$this->assertArrayHasKey('status', $result['batch'], "Result of API call 'upload-project' should contain 'batch.status' key");
		$this->assertEquals('success', $result['batch']['status'], "Result of API call 'upload-project' should contain 'batch.status' key with value 'success'");
	}

	public function testCreateUserAndProject()
	{
		$writerId = 'test' . uniqid();
		$this->_client->createWriter($writerId);

		$email = uniqid() . '@' . uniqid() . '.com';
		$result = $this->_client->createUser($writerId, $email, uniqid(), 'functional', 'test user');
		$this->assertArrayHasKey('uid', $result, "Result of createUser request should return uid of created user");

		$result = $this->_client->createProject($writerId, 'Test ' . uniqid());
		$this->assertArrayHasKey('pid', $result, "Result of createProject request should return pid of created project");
		$pid = $result['pid'];

		$result = $this->_client->addUserToProject($writerId, $pid, $email);
		$this->assertArrayHasKey('status', $result, "Result of addUserToProject request should return status");
		$this->assertEquals('ok', $result['status'], "Result of addUserToProject request should contain 'status' key with value 'success'");

		$result = $this->_client->getSsoLink($writerId, $pid, $email);
		$this->assertArrayHasKey('status', $result, "Result of getSsoLink request should return status");
		$this->assertEquals('ok', $result['status'], "Result of getSsoLink request should contain 'status' key with value 'success'");
		$this->assertArrayHasKey('ssoLink', $result, "Result of getSsoLink request should return key 'ssoLink'");
	}

}
