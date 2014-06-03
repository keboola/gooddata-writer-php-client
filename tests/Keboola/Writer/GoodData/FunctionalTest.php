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
	protected $client;

	public function setUp()
	{
		$this->client = \Keboola\Writer\GoodData\Client::factory(array(
			'url' => FUNCTIONAL_GOODDATA_WRITER_API_URL,
			'token' => FUNCTIONAL_STORAGE_API_TOKEN
		));

		foreach ($this->client->getWriters() as $writer) {
			if ($writer['id'] != FUNCTIONAL_WRITER_ID) {
				$this->client->deleteWriter($writer['id']);
			}
		}
	}

	public function testUpload()
	{
		$result = $this->client->uploadProject(FUNCTIONAL_WRITER_ID);
		$this->assertArrayHasKey('status', $result, "Result of API call 'upload-project' should contain 'status' key");
		$this->assertEquals('success', $result['status'], "Result of API call 'upload-project' should contain 'status' key with value 'success'");

		$result = $this->client->uploadTable(FUNCTIONAL_WRITER_ID, 'out.c-main.categories');
		$this->assertArrayHasKey('status', $result, "Result of API call 'upload-table' should contain 'status' key");
		$this->assertEquals('success', $result['status'], "Result of API call 'upload-table' should contain 'status' key with value 'success'");

		$result = $this->client->updateModel(FUNCTIONAL_WRITER_ID, 'out.c-main.categories');
		$this->assertArrayHasKey('status', $result, "Result of API call 'update-model' should contain 'status' key");
		$this->assertEquals('success', $result['status'], "Result of API call 'update-model' should contain 'status' key with value 'success'");

		$result = $this->client->loadData(FUNCTIONAL_WRITER_ID, array('out.c-main.categories', 'out.c-main.products'));
		$this->assertArrayHasKey('status', $result, "Result of API call 'load-data' should contain 'status' key");
		$this->assertEquals('success', $result['status'], "Result of API call 'load-data' should contain 'status' key with value 'success'");
	}

	public function testCreateUserAndProject()
	{
		$writerId = 'test' . uniqid();
		$this->client->createWriter($writerId);

		$email = uniqid() . '@' . uniqid() . '.com';
		$result = $this->client->createUser($writerId, $email, uniqid(), 'functional', 'test user');
		$this->assertArrayHasKey('uid', $result, "Result of createUser request should return uid of created user");

		$result = $this->client->createProject($writerId, '[Test] functional ' . uniqid());
		$this->assertArrayHasKey('pid', $result, "Result of createProject request should return pid of created project");
		$pid = $result['pid'];

		$result = $this->client->addUserToProject($writerId, $pid, $email);
		$this->assertArrayHasKey('status', $result, "Result of addUserToProject request should return status");
		$this->assertEquals('success', $result['status'], "Result of addUserToProject request should contain 'status' key with value 'success'");

		$result = $this->client->getSsoLink($writerId, $pid, $email);
		$this->assertNotEmpty($result, "Result of getSsoLink request should contain link");
	}

}
