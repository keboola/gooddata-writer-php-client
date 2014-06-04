<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2012-11-26
 */

namespace Keboola\Writer\GoodData;

use Guzzle\Common\Collection;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Plugin\Backoff\BackoffPlugin;

class ServerException extends \Exception
{

}

class ClientException extends \Exception
{

}

class Client extends GuzzleClient
{
	/**
	 * Factory method to create a new Client
	 *
	 * The following array keys and values are available options:
	 * - url: Base URL of web service
	 * - token: Storage API token
	 *
	 * @param array|Collection $config Configuration data
	 *
	 * @return self
	 */
	public static function factory($config = array())
	{
		$default = array(
			'url' => 'https://syrup.keboola.com/gooddata-writer'
		);
		$required = array('token');
		$config = Collection::fromConfig($config, $default, $required);
		$config['request.options'] = array(
			'headers' => array(
				'X-StorageApi-Token' => $config->get('token')
			),
			'config' => array(
				'curl' => array(
					CURLOPT_SSLVERSION => 3,
					CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0
				)

			)
		);

		$client = new self($config->get('url'), $config);

		// Attach a service description to the client
		$description = ServiceDescription::factory(__DIR__ . '/service.json');
		$client->setDescription($description);

		$client->setBaseUrl($config->get('url'));

		// Setup exponential backoff
		$backoffPlugin = BackoffPlugin::getExponentialBackoff();
		$client->addSubscriber($backoffPlugin);

		return $client;
	}

	/**
	 * Get list of writers
	 * @return mixed
	 */
	public function getWriters()
	{
		$result = $this->getCommand('GetWriters')->execute();
		return $result['writers'];
	}

	/**
	 * Create writer
	 */
	public function createWriterAsync($writerId, $users = array(), $accessToken = null)
	{
		$params = array(
			'writerId' => $writerId,
		);

		if (!empty($users)) {
			$params['users'] = implode(',', $users);
		}
		if (!empty($accessToken)) {
			$params['accessToken'] = $accessToken;
		}
		return $this->getCommand('CreateWriter', $params)->execute();
	}

	/**
	 * Create writer and wait for finish
	 */
	public function createWriter($writerId, $users = array(), $accessToken = null)
	{
		$isBatch = (bool) count($users);
		$key = $isBatch ? 'batch' : 'job';

		if ($users && !is_array($users)) {
			throw new ClientException("Parameter 'users' must be array");
		}
		$job = $this->createWriterAsync($writerId, $users, $accessToken);
		if (!isset($job[$key])) {
			throw new ServerException('Create writer job returned unexpected result');
		}

		return $this->waitForJob($writerId, $job[$key], $isBatch);
	}

	/**
	 * Create writer with existing GoodData project
	 */
	public function createWriterWithProjectAsync($writerId, $pid, $username, $password, $users = array())
	{
		$params = array(
			'writerId' => $writerId,
			'pid' => $pid,
			'username' => $username,
			'password' => $password
		);

		if (!empty($users)) {
			$params['users'] = implode(',', $users);
		}
		return $this->getCommand('CreateWriterWithProject', $params)->execute();
	}

	/**
	 * Create writer with existing GoodData project and wait for finish
	 */
	public function createWriterWithProject($writerId, $pid, $username, $password, $users = array())
	{
		$isBatch = (bool) count($users);
		$key = $isBatch ? 'batch' : 'job';

		if ($users && !is_array($users)) {
			throw new ClientException("Parameter 'users' must be array");
		}
		$job = $this->createWriterWithProjectAsync($writerId, $pid, $username, $password, $users);
		if (!isset($job[$key])) {
			throw new ServerException('Create writer job returned unexpected result');
		}

		return $this->waitForJob($writerId, $job[$key], $isBatch);
	}


	/**
	 * Delete writer
	 * @param $writerId
	 * @return mixed
	 */
	public function deleteWriter($writerId)
	{
		return $this->getCommand('DeleteWriter', array(
			'writerId' => $writerId
		))->execute();
	}


	/**
	 * Get list of users
	 * @param $writerId
	 * @return mixed
	 */
	public function getUsers($writerId)
	{
		$result = $this->getCommand('GetUsers', array(
			'writerId' => $writerId
		))->execute();
		return $result['users'];
	}

	/**
	 * Create user and don't wait for end of the job
	 * @param $writerId
	 * @param $email
	 * @param $password
	 * @param $firstName
	 * @param $lastName
	 * @param string $queue primary|secondary
	 * @return mixed
	 */
	public function createUserAsync($writerId, $email, $password, $firstName, $lastName, $queue = 'primary')
	{
		return $this->getCommand('CreateUser', array(
			'writerId' => $writerId,
			'email' => $email,
			'password' => $password,
			'firstName' => $firstName,
			'lastName' => $lastName,
			'queue' => $queue
		))->execute();
	}

	/**
	 * Create user and wait for finish, returns user's uid
	 * @param $writerId
	 * @param $email
	 * @param $password
	 * @param $firstName
	 * @param $lastName
	 * @param string $queue primary|secondary
	 * @throws ServerException
	 * @return array
	 */
	public function createUser($writerId, $email, $password, $firstName, $lastName, $queue = 'primary')
	{
		$job = $this->createUserAsync($writerId, $email, $password, $firstName, $lastName, $queue);
		if (!isset($job['job'])) {
			throw new ServerException('Create user job returned unexpected result');
		}

		$result = $this->waitForJob($writerId, $job['job']);
		if (!isset($result['result']['uid'])) {
			throw new ServerException('Job info for create user returned unexpected result');
		}

		return array('uid' => $result['result']['uid']);
	}



	/**
	 * Get list of projects
	 * @param $writerId
	 * @return mixed
	 */
	public function getProjects($writerId)
	{
		$result = $this->getCommand('getProjects', array(
			'writerId' => $writerId
		))->execute();
		return $result['projects'];
	}

	/**
	 * Create project
	 * @param $writerId
	 * @param null $name
	 * @param null $accessToken
	 * @param string $queue primary|secondary
	 * @return mixed
	 */
	public function createProjectAsync($writerId, $name = null, $accessToken = null, $queue = 'primary')
	{
		$params = array(
			'writerId' => $writerId,
			'queue' => $queue
		);
		if ($name) $params['name'] = $name;
		if ($accessToken) $params['accessToken'] = $accessToken;
		return $this->getCommand('CreateProject', $params)->execute();
	}

	/**
	 * Create project and wait for finish, return project's pid
	 * @param $writerId
	 * @param $name
	 * @param $accessToken
	 * @param string $queue primary|secondary
	 * @throws ServerException
	 * @return array
	 */
	public function createProject($writerId, $name = null, $accessToken = null, $queue = 'primary')
	{
		$job = $this->createProjectAsync($writerId, $name, $accessToken, $queue);
		if (!isset($job['job'])) {
			throw new ServerException('Create project job returned unexpected result');
		}

		$result = $this->waitForJob($writerId, $job['job']);
		if (!isset($result['result']['pid'])) {
			throw new ServerException('Job info for create project returned unexpected result');
		}

		return array('pid' => $result['result']['pid']);
	}



	/**
	 * Get list of projects users
	 * @param $writerId
	 * @param $pid
	 * @return mixed
	 */
	public function getProjectUsers($writerId, $pid)
	{
		$result = $this->getCommand('getProjectUsers', array(
			'writerId' => $writerId,
			'pid' => $pid
		))->execute();
		return $result['users'];
	}

	/**
	 * Add user to project
	 * @param $writerId
	 * @param $pid
	 * @param $email
	 * @param string $role admin|editor|readOnly|dashboardOnly
	 * @param string $queue primary|secondary
	 * @return mixed
	 */
	public function addUserToProjectAsync($writerId, $pid, $email, $role = 'editor', $queue = 'primary')
	{
		return $this->getCommand('AddUserToProject', array(
			'writerId' => $writerId,
			'pid' => $pid,
			'email' => $email,
			'role' => $role,
			'queue' => $queue
		))->execute();
	}

	/**
	 * Add user to project and wait for finish
	 * @param $writerId
	 * @param $pid
	 * @param $email
	 * @param string $role admin|editor|readOnly|dashboardOnly
	 * @param string $queue primary|secondary
	 * @throws ServerException
	 * @return mixed
	 */
	public function addUserToProject($writerId, $pid, $email, $role = 'editor', $queue = 'primary')
	{
		$job = $this->addUserToProjectAsync($writerId, $pid, $email, $role, $queue);
		if (!isset($job['job'])) {
			throw new ServerException('Create project job returned unexpected result');
		}

		return $this->waitForJob($writerId, $job['job']);
	}

	/**
	 * Generate SSO link for configured project and user
	 * @param $writerId
	 * @param $pid
	 * @param $email
	 * @throws ClientException
	 * @return string
	 */
	public function getSsoLink($writerId, $pid, $email)
	{
		$result = $this->getCommand('GetSSOLink', array(
			'writerId' => $writerId,
			'pid' => $pid,
			'email' => $email
		))->execute();
		if (!isset($result['ssoLink'])) {
			throw new ClientException('Getting SSO link failed. ' . (isset($result['error']) ? $result['error'] : ''));
		}
		return $result['ssoLink'];
	}


	/**
	 * Upload project to GoodData
	 * @param $writerId
	 * @param null $incrementalLoad
	 * @param string $queue
	 * @return mixed
	 */
	public function uploadProjectAsync($writerId, $incrementalLoad = null, $queue = 'primary')
	{
		return $this->getCommand('UploadProject', array(
			'writerId' => $writerId,
			'incrementalLoad' => $incrementalLoad,
			'queue' => $queue
		))->execute();
	}

	/**
	 * Upload project to GoodData and wait for result
	 * @param $writerId
	 * @param null $incrementalLoad
	 * @param string $queue
	 * @throws ServerException
	 * @return mixed
	 */
	public function uploadProject($writerId, $incrementalLoad = null, $queue = 'primary')
	{
		$batch = $this->uploadProjectAsync($writerId, $incrementalLoad, $queue);
		if (!isset($batch['batch'])) {
			throw new ServerException('Upload project batch returned unexpected result');
		}

		return $this->waitForJob($writerId, $batch['batch'], true);
	}

	/**
	 * Upload table to GoodData
	 * @param $writerId
	 * @param $tableId
	 * @param null $incrementalLoad
	 * @param string $queue
	 * @return mixed
	 */
	public function uploadTableAsync($writerId, $tableId, $incrementalLoad = null, $queue = 'primary')
	{
		return $this->getCommand('UploadTable', array(
			'writerId' => $writerId,
			'tableId' => $tableId,
			'incrementalLoad' => $incrementalLoad,
			'queue' => $queue
		))->execute();
	}

	/**
	 * Upload table to GoodData and wait for result
	 * @param $writerId
	 * @param $tableId
	 * @param null $incrementalLoad
	 * @param string $queue
	 * @throws ServerException
	 * @return mixed
	 */
	public function uploadTable($writerId, $tableId, $incrementalLoad = null, $queue = 'primary')
	{
		$batch = $this->uploadTableAsync($writerId, $tableId, $incrementalLoad, $queue);
		if (!isset($batch['batch'])) {
			throw new ServerException('Upload table job returned unexpected result');
		}

		return $this->waitForJob($writerId, $batch['batch'], true);
	}

	/**
	 * Update model of table in GoodData
	 * @param $writerId
	 * @param $tableId
	 * @param string $queue
	 * @return mixed
	 */
	public function updateModelAsync($writerId, $tableId, $queue = 'primary')
	{
		return $this->getCommand('UpdateModel', array(
			'writerId' => $writerId,
			'tableId' => $tableId,
			'queue' => $queue
		))->execute();
	}

	/**
	 * Update model of table in GoodData and wait for result
	 * @param $writerId
	 * @param $tableId
	 * @param string $queue
	 * @throws ServerException
	 * @return mixed
	 */
	public function updateModel($writerId, $tableId, $queue = 'primary')
	{
		$job = $this->updateModelAsync($writerId, $tableId, $queue);
		if (!isset($job['job'])) {
			throw new ServerException('Update model job returned unexpected result');
		}

		return $this->waitForJob($writerId, $job['job']);
	}

	/**
	 * Load data to table in GoodData
	 * @param $writerId
	 * @param array $tables
	 * @param null $incrementalLoad
	 * @param string $queue
	 * @return mixed
	 */
	public function loadDataAsync($writerId, array $tables, $incrementalLoad = null, $queue = 'primary')
	{
		return $this->getCommand('LoadData', array(
			'writerId' => $writerId,
			'tables' => $tables,
			'incrementalLoad' => $incrementalLoad,
			'queue' => $queue
		))->execute();
	}

	/**
	 * Load data to table in GoodData and wait for result
	 * @param $writerId
	 * @param array $tables
	 * @param null $incrementalLoad
	 * @param string $queue
	 * @throws ServerException
	 * @return mixed
	 */
	public function loadData($writerId, array $tables, $incrementalLoad = null, $queue = 'primary')
	{
		$job = $this->loadDataAsync($writerId, $tables, $incrementalLoad, $queue);
		if (!isset($job['batch'])) {
			throw new ServerException('Load data job returned unexpected result');
		}

		return $this->waitForJob($writerId, $job['batch'], true);
	}
	

	/**
	 * Return list of jobs for given writerId
	 * @param $writerId
	 * @return mixed
	 */
	public function getJobs($writerId)
	{
		return $this->getCommand('JobsList', array(
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return list of jobs for given writerId
	 * @param $writerId
	 * @return mixed
	 * @deprecated
	 */
	public function jobs($writerId)
	{
		return $this->getJobs($writerId);
	}

	/**
	 * Return detail of given batch
	 * @param $writerId
	 * @param $batchId
	 * @return mixed
	 */
	public function getBatch($writerId, $batchId)
	{
		return $this->getCommand('BatchStatus', array(
			'batchId' => (int)$batchId,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return detail of given batch
	 * @param $batchId
	 * @param $writerId
	 * @return mixed
	 * @deprecated
	 */
	public function batch($writerId, $batchId)
	{
		return $this->getBatch($writerId, $batchId);
	}

	/**
	 * Return detail of given job
	 * @param $jobId
	 * @param $writerId
	 * @return mixed
	 */
	public function getJob($writerId, $jobId)
	{
		return $this->getCommand('JobStatus', array(
			'jobId' => (int)$jobId,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return detail of given job
	 * @param $jobId
	 * @param $writerId
	 * @return mixed
	 * @deprecated
	 */
	public function job($writerId, $jobId)
	{
		return $this->getJob($writerId, $jobId);
	}




	/**
	 * Ask repeatedly for job or batch status until it is finished
	 * @param $writerId
	 * @param $jobId
	 * @param bool $isBatch
	 * @return mixed
	 */
	protected function waitForJob($writerId, $jobId, $isBatch = false)
	{
		$jobFinished = false;
		$i = 1;
		do {
			$jobInfo = $isBatch
				? $this->getBatch($writerId, $jobId)
				: $this->getJob($writerId, $jobId);
			if (isset($jobInfo['status']) && !in_array($jobInfo['status'], array('waiting', 'processing'))) {
				$jobFinished = true;
			}
			if (!$jobFinished) sleep($i * 10);
			$i++;
		} while(!$jobFinished);

		return $jobInfo;
	}

}
