<?php
/**
 * @user Jakub Matejka <jakub@keboola.com>
 * @date 2012-11-26
 *
 */
namespace Keboola\Writer\GoodData;

use Guzzle\Http\Exception\ClientErrorResponseException;

class FunctionalTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var \Keboola\Writer\GoodData\Client
     */
    protected $client;

    public function setUp()
    {
        $this->client = \Keboola\Writer\GoodData\Client::factory([
            'url' => GOODDATA_WRITER_API_URL,
            'token' => STORAGE_API_TOKEN
        ]);

        foreach ($this->client->getWriters() as $writer) {
            if ($writer['writerId'] != FUNCTIONAL_WRITER_ID) {
                $this->client->deleteWriter($writer['writerId']);
            }
        }
    }

    public function testConfiguration()
    {
        $result = $this->client->getTables(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('tables', $result);
        $this->assertCount(2, $result['tables']);
        $categoriesFound = false;
        $productsFound = false;
        foreach ($result['tables'] as $t) {
            $this->assertArrayHasKey('id', $t);
            $this->assertArrayHasKey('bucket', $t);
            $this->assertTrue(isset($t['name']) || isset($t['title']));
            $this->assertArrayHasKey('export', $t);
            $this->assertArrayHasKey('isExported', $t);
            if ($t['id'] == 'out.c-main.categories') {
                $categoriesFound = true;
            }
            if ($t['id'] == 'out.c-main.products') {
                $productsFound = true;
            }
        }
        $this->assertTrue($categoriesFound);
        $this->assertTrue($productsFound);

        $tableId = 'out.c-main.products';
        $result = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertArrayHasKey('table', $result);
        $this->assertArrayHasKey('id', $result['table']);
        $this->assertArrayHasKey('title', $result['table']);
        $this->assertArrayHasKey('export', $result['table']);
        $this->assertArrayHasKey('isExported', $result['table']);
        $this->assertArrayHasKey('incrementalLoad', $result['table']);
        $this->assertArrayHasKey('ignoreFilter', $result['table']);
        $this->assertArrayHasKey('columns', $result['table']);
        $column = current($result['table']['columns']);
        $this->assertArrayHasKey('name', $column);
        $this->assertArrayHasKey('title', $column);
        $this->assertArrayHasKey('type', $column);

        $oldName = $result['table']['title'];
        $newName = uniqid();
        $columnId = $column['name'];
        $oldColumnName = $column['title'];
        $newColumnName = uniqid();

        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $newName);
        $this->client->updateTableColumn(FUNCTIONAL_WRITER_ID, $tableId, $column['name'], ['title' => $newColumnName]);
        $result = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);

        $this->assertEquals($newName, $result['table']['title']);
        $column = [];
        foreach ($result['table']['columns'] as $c) {
            if ($c['name'] == $columnId) {
                $column = $c;
            }
        }
        $this->assertEquals($newColumnName, $column['title']);

        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $oldName);
        $this->client->updateTableColumns(FUNCTIONAL_WRITER_ID, $tableId, [['name' => $column['name'], 'title' => $oldColumnName]]);

        $result = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($oldName, $result['table']['title'], 'Table out.c-main.products should have old name back');
        $column = [];
        foreach ($result['table']['columns'] as $c) {
            if ($c['name'] == $columnId) {
                $column = $c;
            }
        }
        $this->assertEquals($oldColumnName, $column['title']);
    }

    public function testProxy()
    {
        $writer = $this->client->getWriter(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('gd', $writer);
        $this->assertArrayHasKey('pid', $writer['gd']);
        $result = $this->client->get('/gooddata-writer/proxy?writerId='.FUNCTIONAL_WRITER_ID.'&query=/gdc/projects/'.$writer['gd']['pid'])->send();
        $response = $result->json();
        $this->assertArrayHasKey('response', $response);
        $this->assertArrayHasKey('project', $response['response']);

        $result = $this->client->post('/gooddata-writer/proxy', null, json_encode([
            'writerId' => FUNCTIONAL_WRITER_ID,
            'query' => '/gdc/md/'.$writer['gd']['pid'].'/service/timezone',
            'payload' => ["service" => ["timezone"=>"UTC"]]
        ]))->send();
        $response = $result->json();
        $this->assertArrayHasKey('url', $response);
        $result = $this->client->waitForJob($response['url']);
        $this->assertArrayHasKey('jobs', $result);
        $this->assertGreaterThanOrEqual(1, $result['jobs']);
        $this->assertArrayHasKey('status', $result['jobs'][0]);
        $this->assertEquals('success', $result['jobs'][0]['status']);
    }

    public function testTablesConfiguration()
    {
        $tables = $this->client->getTables(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('tables', $tables);
        $this->assertGreaterThanOrEqual(1, $tables['tables']);
        $this->assertArrayHasKey('id', $tables['tables'][0]);
        $this->assertArrayHasKey('bucket', $tables['tables'][0]);
        $this->assertTrue(isset($tables['tables'][0]['name']) || isset($tables['tables'][0]['title']));
        $this->assertArrayHasKey('identifier', $tables['tables'][0]);

        $tableId = $tables['tables'][0]['id'];
        $tableName = isset($tables['tables'][0]['name']) ? $tables['tables'][0]['name'] : $tables['tables'][0]['title'];

        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertArrayHasKey('table', $table);
        $this->assertArrayHasKey('id', $table['table']);
        $this->assertTrue(isset($table['table']['name']) || isset($table['table']['title']));
        $this->assertArrayHasKey('columns', $table['table']);
        $this->assertGreaterThanOrEqual(1, $table['table']['columns']);
        $this->assertEquals($tableName, $table['table']['title']);
        $this->assertArrayHasKey('id', $table['table']['columns']);
        $this->assertArrayHasKey('name', $table['table']['columns']['id']);
        $this->assertArrayHasKey('title', $table['table']['columns']['id']);
        $columnName = $table['table']['columns']['id']['name'];
        $columnTitle = $table['table']['columns']['id']['title'];

        $newTableName = uniqid();
        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $newTableName);
        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($newTableName, $table['table']['title']);
        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $tableName);

        $newColumnTitle = uniqid();
        $this->client->updateTableColumn(FUNCTIONAL_WRITER_ID, $tableId, $columnName, ['title' => $newColumnTitle]);
        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($newColumnTitle, $table['table']['columns']['id']['title']);
        $this->client->updateTableColumns(FUNCTIONAL_WRITER_ID, $tableId, [['name' => $columnName, 'title' => $columnTitle]]);
        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($columnTitle, $table['table']['columns']['id']['title']);
    }

    public function testUpload()
    {
        $result = $this->client->uploadProject(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);

        $result = $this->client->uploadTable(FUNCTIONAL_WRITER_ID, 'out.c-main.categories');
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);

        $result = $this->client->updateModel(FUNCTIONAL_WRITER_ID, 'out.c-main.categories');
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);

        $result = $this->client->loadData(FUNCTIONAL_WRITER_ID, ['out.c-main.categories', 'out.c-main.products']);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);
    }

    public function testCreateUserAndProject()
    {
        $projectId = substr(STORAGE_API_TOKEN, 0, strpos(STORAGE_API_TOKEN, '-'));
        $writerId = 'test' . uniqid();
        $this->client->createWriter($writerId);

        $email = $projectId. '-test-functional-' . uniqid() . '@test.com';
        $result = $this->client->createUser($writerId, $email, uniqid(), 'functional', 'test user');
        $this->assertArrayHasKey('uid', $result);

        $result = $this->client->createProject($writerId, '[Test] functional ' . uniqid());
        $this->assertArrayHasKey('pid', $result);
        $pid = $result['pid'];

        $result = $this->client->addUserToProject($writerId, $pid, $email);
        $this->assertArrayHasKey('status', $result);
        $this->assertEquals('success', $result['status']);

        $result = $this->client->getUsers($writerId);
        $this->assertCount(2, $result);
        $userFound = false;
        foreach ($result as $r) {
            $this->assertArrayHasKey('email', $r);
            if ($r['email'] == $email) {
                $userFound = true;
            }
        }
        $this->assertTrue($userFound);

        $result = $this->client->getProjects($writerId);
        $this->assertCount(2, $result);
        $projectFound = false;
        foreach ($result as $r) {
            $this->assertArrayHasKey('pid', $r);
            if ($r['pid'] == $pid) {
                $projectFound = true;
            }
        }
        $this->assertTrue($projectFound);

        $result = $this->client->getProjectUsers($writerId, $pid);
        $this->assertCount(3, $result);

        $result = $this->client->getSsoLink($writerId, $pid, $email);
        $this->assertNotEmpty($result, "Result of getSsoLink request should contain link");
    }

    public function testUserFilters()
    {
        $filterName = 'filter '.uniqid();
        $writer = $this->client->getWriter(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('gd', $writer);
        $this->assertArrayHasKey('pid', $writer['gd']);

        $result = $this->client->post('/gooddata-writer/filters', null, json_encode([
            'writerId' => FUNCTIONAL_WRITER_ID,
            'pid' => $writer['gd']['pid'],
            'name' => $filterName,
            'attribute' => 'out.c-main.categories.name',
            'value' => 'Category 1'
        ]))->send()->json();
        $this->assertArrayHasKey('url', $result);
        $jobResult = $this->client->waitForJob($result['url']);
        $this->assertArrayHasKey('status', $jobResult);
        $this->assertEquals('success', $jobResult['status']);

        $result = $this->client->get('/gooddata-writer/filters?writerId='.FUNCTIONAL_WRITER_ID)->send()->json();
        $this->assertArrayHasKey('filters', $result);
        $filterFound = false;
        foreach ($result['filters'] as $f) {
            if ($f['name'] == $filterName) {
                $filterFound = true;
            }
        }
        $this->assertTrue($filterFound);

        $result = $this->client->post('/gooddata-writer/filters-users', null, json_encode([
            'writerId' => FUNCTIONAL_WRITER_ID,
            'filters' => [$filterName],
            'email' => $writer['gd']['username']
        ]))->send()->json();
        $this->assertArrayHasKey('url', $result);
        $jobResult = $this->client->waitForJob($result['url']);
        $this->assertArrayHasKey('status', $jobResult);
        $this->assertEquals('success', $jobResult['status']);

        $result = $this->client->delete('/gooddata-writer/filters?writerId='.FUNCTIONAL_WRITER_ID.'&name='.$filterName)->send()->json();
        $this->assertArrayHasKey('url', $result);
        $jobResult = $this->client->waitForJob($result['url']);
        $this->assertArrayHasKey('status', $jobResult);
        $this->assertEquals('success', $jobResult['status']);

        $result = $this->client->get('/gooddata-writer/filters?writerId='.FUNCTIONAL_WRITER_ID)->send()->json();
        $this->assertArrayHasKey('filters', $result);
        foreach ($result['filters'] as $f) {
            if ($f['name'] == $filterName) {
                $this->fail();
            }
        }
    }

    public function testJobs()
    {
        $job = $this->client->loadDataAsync(FUNCTIONAL_WRITER_ID, ['out.c-main.categories', 'out.c-main.products']);
        $this->assertArrayHasKey('id', $job);

        $result = $this->client->getJob($job['id']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('component', $result);
        $this->assertArrayHasKey('id', $result);

        $result = $this->client->getJobs(FUNCTIONAL_WRITER_ID);
        $this->assertGreaterThanOrEqual(1, $result);
    }

    public function testProjectAccess()
    {
        $writer = $this->client->getWriter(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('gd', $writer);
        $this->assertArrayHasKey('pid', $writer['gd']);

        try {
            $this->client->disableProjectAccess(FUNCTIONAL_WRITER_ID, $writer['gd']['pid']);
        } catch (ClientErrorResponseException $e) {
        }

        try {
            $this->client->getProjectAccess(FUNCTIONAL_WRITER_ID, $writer['gd']['pid']);
            $this->fail();
        } catch (ClientErrorResponseException $e) {
        }

        $this->client->enableProjectAccess(FUNCTIONAL_WRITER_ID, $writer['gd']['pid']);

        $result = $this->client->getProjectAccess(FUNCTIONAL_WRITER_ID, $writer['gd']['pid']);
        $this->assertNotEmpty($result);
        $this->assertStringStartsWith('https://secure.gooddata.com/', $result);

        $this->client->disableProjectAccess(FUNCTIONAL_WRITER_ID, $writer['gd']['pid']);

        try {
            $this->client->getProjectAccess(FUNCTIONAL_WRITER_ID, $writer['gd']['pid']);
            $this->fail();
        } catch (ClientErrorResponseException $e) {
        }
    }
}
