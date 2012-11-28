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

		$client = new self($config->get('url'), $config);

		// Attach a service description to the client
		$description = ServiceDescription::factory(__DIR__ . '/service.json');
		$client->setDescription($description);

		$client->setBaseUrl($config->get('url'));

		// Set Storage API token
		$client->setDefaultHeaders(array(
			'X-StorageApi-Token' => $config->get('token')
		));

		// Setup exponential backoff
		$backoffPlugin = BackoffPlugin::getExponentialBackoff();
		$client->addSubscriber($backoffPlugin);

		return $client;
	}

	/**
	 * Upload project to GoodData
	 * @param $writerId
	 * @param null $incrementalLoad
	 * @param null $sanitize
	 * @return mixed
	 */
	public function uploadProject($writerId, $incrementalLoad = null, $sanitize = null)
	{
		return $this->getCommand('UploadProject', array(
			'writerId' => $writerId,
			'incrementalLoad' => $incrementalLoad,
			'sanitize' => $sanitize
		))->execute();
	}

	/**
	 * Upload table to GoodData
	 * @param $table
	 * @param $writerId
	 * @param null $incrementalLoad
	 * @param null $sanitize
	 * @return mixed
	 */
	public function uploadTable($table, $writerId, $incrementalLoad = null, $sanitize = null)
	{
		return $this->getCommand('UploadProject', array(
			'table' => $table,
			'writerId' => $writerId,
			'incrementalLoad' => $incrementalLoad,
			'sanitize' => $sanitize
		))->execute();
	}

	/**
	 * Return XML configuration of given table
	 * @param $table
	 * @param $writerId
	 * @return mixed
	 */
	public function xml($table, $writerId)
	{
		return $this->getCommand('Xml', array(
			'table' => $table,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Delete writer configuration and schedule GoodData project for deletion
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
	 * Return list of jobs for given writerId
	 * @param $writerId
	 * @param null $count
	 * @param null $offset
	 * @return mixed
	 */
	public function jobs($writerId, $count=null, $offset=null)
	{
		return $this->getCommand('JobsList', array(
			'writerId' => $writerId,
			'count' => $count,
			'offset' => $offset
		))->execute();
	}

	/**
	 * Return detail of given batch
	 * @param $writerId
	 * @return mixed
	 */
	public function batch($writerId)
	{
		return $this->getCommand('BatchStatus', array(
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return detail of given job
	 * @param $job
	 * @param $writerId
	 * @return mixed
	 */
	public function job($job, $writerId)
	{
		return $this->getCommand('JobStatus', array(
			'job' => $job,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return xml of given job
	 * @param $job
	 * @param $writerId
	 * @return mixed
	 */
	public function jobXml($job, $writerId)
	{
		return $this->getCommand('JobXml', array(
			'job' => $job,
			'writerId' => $writerId
		))->execute();
	}

	/**
	 * Return preview of csv of given job
	 * @param $job
	 * @param $writerId
	 * @return mixed
	 */
	public function jobCsv($job, $writerId)
	{
		return $this->getCommand('JobCsv', array(
			'job' => $job,
			'writerId' => $writerId
		))->execute();
	}

}