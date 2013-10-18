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
			'url' => 'https://gooddata-writer.keboola.com/'
		);
		$required = array('token');
		$config = Collection::fromConfig($config, $default, $required);
		$config['request.options'] = array(
			'headers' => array(
				'X-StorageApi-Token' => $config->get('token')
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
	 * @param $writerId
	 * @param bool $wait
	 * @return mixed
	 */
	public function createWriter($writerId, $wait = false)
	{
		return $this->getCommand('CreateWriter', array(
			'writerId' => $writerId,
			'wait' => $wait ? 1 : 0
		))->execute();
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
	 * Upload project to GoodData
	 * @param $writerId
	 * @param null $incrementalLoad
	 * @return mixed
	 */
	public function uploadProject($writerId, $incrementalLoad = null)
	{
		return $this->getCommand('UploadProject', array(
			'writerId' => $writerId,
			'incrementalLoad' => $incrementalLoad
		))->execute();
	}

	/**
	 * Upload table to GoodData
	 * @param $table
	 * @param $writerId
	 * @param null $incrementalLoad
	 * @return mixed
	 */
	public function uploadTable($writerId, $table, $incrementalLoad = null)
	{
		return $this->getCommand('UploadTable', array(
			'table' => $table,
			'writerId' => $writerId,
			'incrementalLoad' => $incrementalLoad
		))->execute();
	}

	/**
	 * Return XML configuration of given table
	 * @param $writerId
	 * @param $table
	 * @return mixed
	 */
	public function xml($writerId, $table)
	{
		return $this->getCommand('Xml', array(
			'table' => $table,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return list of jobs for given writerId
	 * @param $writerId
	 * @return mixed
	 */
	public function jobs($writerId)
	{
		return $this->getCommand('JobsList', array(
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return detail of given batch
	 * @param $batch
	 * @param $writerId
	 * @return mixed
	 */
	public function batch($writerId, $batch)
	{
		return $this->getCommand('BatchStatus', array(
			'batchId' => (int)$batch,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return detail of given job
	 * @param $job
	 * @param $writerId
	 * @return mixed
	 */
	public function job($writerId, $job)
	{
		return $this->getCommand('JobStatus', array(
			'jobId' => (int)$job,
			'writerId' => $writerId
		))->execute();
	}

}