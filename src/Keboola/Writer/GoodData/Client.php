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

		// Set Storage API token
		$client->setDefaultHeaders(array(
			'X-StorageApi-Token' => $config->get('token')
		));

		// Setup exponential backoff
		$backoffPlugin = BackoffPlugin::getExponentialBackoff();
		$client->addSubscriber($backoffPlugin);

		return $client;
	}

}