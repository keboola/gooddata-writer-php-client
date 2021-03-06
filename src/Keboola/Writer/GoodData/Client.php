<?php
/**
 * @author Jakub Matejka <jakub@keboola.com>
 * @date 2012-11-26
 */

namespace Keboola\Writer\GoodData;

use Guzzle\Common\Collection;
use Guzzle\Plugin\Backoff\CurlBackoffStrategy;
use Guzzle\Plugin\Backoff\ExponentialBackoffStrategy;
use Guzzle\Plugin\Backoff\HttpBackoffStrategy;
use Guzzle\Service\Client as GuzzleClient;
use Guzzle\Service\Description\ServiceDescription;
use Guzzle\Plugin\Backoff\BackoffPlugin;
use Keboola\Backoff\TruncatedBackoffStrategy;
use Keboola\Writer\GoodData\Exception\ClientException;
use Keboola\Writer\GoodData\Exception\ServerException;

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
    public static function factory($config = [])
    {
        $default = [
            'url' => 'https://syrup.keboola.com/gooddata-writer'
        ];
        $required = ['token'];
        $config = Collection::fromConfig($config, $default, $required);
        $config['request.options'] = [
            'headers' => [
                'X-StorageApi-Token' => $config->get('token')
            ],
            'config' => [
                'curl' => [
                    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_0
                ]

            ]
        ];

        $client = new self($config->get('url'), $config);

        // Attach a service description to the client
        $description = ServiceDescription::factory(__DIR__ . '/service.json');
        $client->setDescription($description);

        $client->setBaseUrl($config->get('url'));

        // Setup exponential backoff
        // 503 retry always, other errors five times
        $backoffPlugin = new BackoffPlugin(new TruncatedBackoffStrategy(
            5,
            new HttpBackoffStrategy(
                null,
                new CurlBackoffStrategy(
                    null,
                    new ExponentialBackoffStrategy()
                )
            )
        ));
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
     * Get writer data
     * @param $writerId
     * @return mixed
     */
    public function getWriter($writerId)
    {
        $result = $this->getCommand('GetWriter', ['writerId' => $writerId])->execute();
        return $result['writer'];
    }

    /**
     * Create writer
     * @param $writerId
     * @param array $params
     * @return mixed
     */
    public function createWriterAsync($writerId, $params = [])
    {
        $params['writerId'] = $writerId;
        return $this->getCommand('CreateWriter', $params)->execute();
    }

    /**
     * Create writer and wait for finish
     * @param $writerId
     * @param array $params
     */
    public function createWriter($writerId, $params = [])
    {
        $job = $this->createWriterAsync($writerId, $params);
        if (!isset($job['url'])) {
            throw new ServerException('Create writer job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
    }

    /**
     * Create writer with existing GoodData project
     * @param $writerId
     * @param $pid
     * @param $username
     * @param $password
     * @param array $params
     * @return mixed
     */
    public function createWriterWithProjectAsync($writerId, $pid, $username, $password, $params = [])
    {
        $params['writerId'] = $writerId;
        $params['pid'] = $pid;
        $params['username'] = $username;
        $params['password'] = $password;

        return $this->getCommand('CreateWriterWithProject', $params)->execute();
    }

    /**
     * Create writer with existing GoodData project and wait for finish
     * @param $writerId
     * @param $pid
     * @param $username
     * @param $password
     * @param array $params
     * @return \Guzzle\Http\Message\RequestInterface
     * @throws ClientException
     * @throws ServerException
     */
    public function createWriterWithProject($writerId, $pid, $username, $password, $params = [])
    {
        $job = $this->createWriterWithProjectAsync($writerId, $pid, $username, $password, $params);
        if (!isset($job['url'])) {
            throw new ServerException('Create writer job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
    }


    /**
     * Delete writer
     * @param $writerId
     * @return mixed
     */
    public function deleteWriter($writerId)
    {
        return $this->getCommand('DeleteWriter', [
            'writerId' => $writerId
        ])->execute();
    }


    /**
     * Get list of users
     * @param $writerId
     * @return mixed
     */
    public function getUsers($writerId)
    {
        $result = $this->getCommand('GetUsers', [
            'writerId' => $writerId
        ])->execute();
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
        return $this->getCommand('CreateUser', [
            'writerId' => $writerId,
            'email' => $email,
            'password' => $password,
            'firstName' => $firstName,
            'lastName' => $lastName,
            'queue' => $queue
        ])->execute();
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
        if (!isset($job['url'])) {
            throw new ServerException('Create user job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        $result = $this->waitForJob($job['url']);
        if (!isset($result['result'][0]['uid'])) {
            throw new ServerException('Job info for create user returned unexpected result: ' . json_encode($result, JSON_PRETTY_PRINT));
        }

        return ['uid' => $result['result'][0]['uid']];
    }



    /**
     * Get list of projects
     * @param $writerId
     * @return mixed
     */
    public function getProjects($writerId)
    {
        $result = $this->getCommand('getProjects', [
            'writerId' => $writerId
        ])->execute();
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
        $params = [
            'writerId' => $writerId,
            'queue' => $queue
        ];
        if ($name) {
            $params['name'] = $name;
        }
        if ($accessToken) {
            $params['accessToken'] = $accessToken;
        }
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
        if (!isset($job['url'])) {
            throw new ServerException('Create project job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        $result = $this->waitForJob($job['url']);
        if (!isset($result['result'][0]['pid'])) {
            throw new ServerException('Job info for create project returned unexpected result: ' . json_encode($result, JSON_PRETTY_PRINT));
        }

        return ['pid' => $result['result'][0]['pid']];
    }



    /**
     * Get list of projects users
     * @param $writerId
     * @param $pid
     * @return mixed
     */
    public function getProjectUsers($writerId, $pid)
    {
        $result = $this->getCommand('getProjectUsers', [
            'writerId' => $writerId,
            'pid' => $pid
        ])->execute();
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
        return $this->getCommand('AddUserToProject', [
            'writerId' => $writerId,
            'pid' => $pid,
            'email' => $email,
            'role' => $role,
            'queue' => $queue
        ])->execute();
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
        if (!isset($job['url'])) {
            throw new ServerException('Create project job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
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
        $result = $this->getCommand('GetSSOLink', [
            'writerId' => $writerId,
            'pid' => $pid,
            'email' => $email
        ])->execute();
        if (!isset($result['ssoLink'])) {
            throw new ClientException('Getting SSO link failed. ' . (isset($result['error']) ? $result['error'] : ''));
        }
        return $result['ssoLink'];
    }

    /**
     * Get list of tables
     * @param $writerId
     * @return mixed
     */
    public function getTables($writerId)
    {
        return $this->getCommand('GetTables', [
            'writerId' => $writerId
        ])->execute();
    }

    /**
     * Get table configuration
     * @param $writerId
     * @param $tableId
     * @return mixed
     */
    public function getTable($writerId, $tableId)
    {
        return $this->getCommand('GetTable', [
            'writerId' => $writerId,
            'tableId' => $tableId
        ])->execute();
    }

    /**
     * Update table configuration
     * @param $writerId
     * @param $tableId
     * @param null $title
     * @param null $export
     * @param null $incrementalLoad
     * @param null $ignoreFilter
     * @return mixed
     */
    public function updateTable($writerId, $tableId, $title = null, $export = null, $incrementalLoad = null, $ignoreFilter = null)
    {
        $params = [
            'writerId' => $writerId,
            'tableId' => $tableId
        ];
        if ($title !== null) {
            $params['title'] = $title;
        }
        if ($export !== null) {
            $params['export'] = (bool)$export;
        }
        if ($incrementalLoad !== null) {
            $params['incrementalLoad'] = (bool)$incrementalLoad;
        }
        if ($ignoreFilter !== null) {
            $params['ignoreFilter'] = (bool)$ignoreFilter;
        }
        return $this->getCommand('UpdateTable', $params)->execute();
    }

    /**
     * Update table column configuration
     * @param $writerId
     * @param $tableId
     * @param $column
     * @param array $configuration Array with keys: [title, type, reference, schemaReference, format, dateDimension]
     * @throws ClientException
     * @return mixed
     */
    public function updateTableColumn($writerId, $tableId, $column, array $configuration)
    {
        $allowedConfigurationOptions = ['title', 'type', 'reference', 'schemaReference', 'format', 'dateDimension'];
        foreach ($configuration as $k => $v) {
            if (!in_array($k, $allowedConfigurationOptions)) {
                throw new ClientException("Option '$k' is not allowed, choose from: " . implode(', ', $allowedConfigurationOptions));
            }
        }

        $params = [
            'writerId' => $writerId,
            'tableId' => $tableId,
            'column' => $column
        ];
        $params = array_merge($params, $configuration);
        return $this->getCommand('UpdateTableColumn', $params)->execute();
    }

    /**
     * Update table columns configuration
     * @param $writerId
     * @param $tableId
     * @param array $columns Array of arrays with keys: [name, title, type, reference, schemaReference, format, dateDimension]
     * @throws ClientException
     * @return mixed
     */
    public function updateTableColumns($writerId, $tableId, array $columns)
    {
        $allowedConfigurationOptions = ['name', 'title', 'type', 'reference', 'schemaReference', 'format', 'dateDimension'];
        foreach ($columns as $column) {
            if (!isset($column['name'])) {
                throw new ClientException("One of the columns is missing 'name' parameter");
            }
            foreach ($column as $k => $v) {
                if (!in_array($k, $allowedConfigurationOptions)) {
                    throw new ClientException("Option '$k' for column '$column' is not allowed, choose from: " . implode(', ', $allowedConfigurationOptions));
                }
            }
        }

        $params = [
            'writerId' => $writerId,
            'tableId' => $tableId,
            'columns' => $columns
        ];
        return $this->getCommand('UpdateTableColumns', $params)->execute();
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
        return $this->getCommand('UploadProject', [
            'writerId' => $writerId,
            'incrementalLoad' => $incrementalLoad,
            'queue' => $queue
        ])->execute();
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
        $job = $this->uploadProjectAsync($writerId, $incrementalLoad, $queue);
        if (!isset($job['url'])) {
            throw new ServerException('Upload project job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
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
        return $this->getCommand('UploadTable', [
            'writerId' => $writerId,
            'tableId' => $tableId,
            'incrementalLoad' => $incrementalLoad,
            'queue' => $queue
        ])->execute();
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
        $job = $this->uploadTableAsync($writerId, $tableId, $incrementalLoad, $queue);
        if (!isset($job['url'])) {
            throw new ServerException('Upload table job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
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
        return $this->getCommand('UpdateModel', [
            'writerId' => $writerId,
            'tableId' => $tableId,
            'queue' => $queue
        ])->execute();
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
        if (!isset($job['url'])) {
            throw new ServerException('Update model job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
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
        return $this->getCommand('LoadData', [
            'writerId' => $writerId,
            'tables' => $tables,
            'incrementalLoad' => $incrementalLoad,
            'queue' => $queue
        ])->execute();
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
        if (!isset($job['url'])) {
            throw new ServerException('Load data job returned unexpected result: ' . json_encode($job, JSON_PRETTY_PRINT));
        }

        return $this->waitForJob($job['url']);
    }


    /**
     * Return list of jobs for given writerId
     * @param $writerId
     * @param null $since
     * @param null $until
     * @return mixed
     */
    public function getJobs($writerId, $since = null, $until = null)
    {
        $url = parse_url($this->getBaseUrl());
        $componentName = substr($url['path'], 1);
        $url = "https://{$url['host']}/queue/jobs?component={$componentName}&q=params.writerId={$writerId}";
        if ($since) {
            $url .= '&since=' . urlencode($since);
        }
        if ($until) {
            $url .= '&until=' . urlencode($until);
        }
        return $this->get($url)->send()->json();
    }

    /**
     * Return detail of given job
     * @param $jobId
     * @return mixed
     */
    public function getJob($jobId)
    {
        $url = parse_url($this->getBaseUrl());
        return $this->get("https://{$url['host']}/queue/job/{$jobId}")->send()->json();
    }


    /**
     * Ask repeatedly for job status until it is finished
     * @param $url
     * @return array
     */
    public function waitForJob($url)
    {
        $jobFinished = false;
        $i = 1;
        do {
            $jobInfo = $jobInfo = $this->get($url)->send()->json();
            if (isset($jobInfo['status']) && !in_array($jobInfo['status'], ['waiting', 'processing'])) {
                $jobFinished = true;
            }
            if (!$jobFinished) {
                usleep((1 << $i) * 1000000 + rand(0, 1000000));
            }
            $i++;
        } while (!$jobFinished);

        return $jobInfo;
    }

    public function enableProjectAccess($writerId, $pid)
    {
        $this->post("/gooddata-writer/v2/{$writerId}/projects/{$pid}/access")->send();
    }

    public function disableProjectAccess($writerId, $pid)
    {
        $this->delete("/gooddata-writer/v2/{$writerId}/projects/{$pid}/access")->send();
    }

    public function getProjectAccess($writerId, $pid)
    {
        $result = $this->get("/gooddata-writer/v2/{$writerId}/projects/{$pid}/access")->send()->json();
        if (!isset($result['link'])) {
            throw new ServerException("Get Project Access call returned unexpected result:"
                . json_encode($result, JSON_PRETTY_PRINT));
        }
        return $result['link'];
    }
}
