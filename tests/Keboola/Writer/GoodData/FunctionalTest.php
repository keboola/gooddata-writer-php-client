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
            'url' => GOODDATA_WRITER_API_URL,
            'token' => STORAGE_API_TOKEN
        ));

        foreach ($this->client->getWriters() as $writer) {
            if ($writer['id'] != FUNCTIONAL_WRITER_ID) {
                $this->client->deleteWriter($writer['id']);
            }
        }
    }

    public function testConfiguration()
    {
        $result = $this->client->getTables(FUNCTIONAL_WRITER_ID);
        $this->assertArrayHasKey('tables', $result, "Result of GET API call /tables should contain 'tables' key");
        $this->assertCount(2, $result['tables'], "Result of GET API call /tables should return two configured tables");
        $categoriesFound = false;
        $productsFound = false;
        foreach ($result['tables'] as $t) {
            $this->assertArrayHasKey('id', $t, "Configured table of GET API call /tables should contain 'id' key");
            $this->assertArrayHasKey('bucket', $t, "Configured table of GET API call /tables should contain 'bucket' key");
            $this->assertArrayHasKey('name', $t, "Configured table of GET API call /tables should contain 'name' key");
            $this->assertArrayHasKey('export', $t, "Configured table of GET API call /tables should contain 'export' key");
            $this->assertArrayHasKey('isExported', $t, "Configured table of GET API call /tables should contain 'isExported' key");
            $this->assertArrayHasKey('lastChangeDate', $t, "Configured table of GET API call /tables should contain 'lastChangeDate' key");
            if ($t['id'] == 'out.c-main.categories') {
                $categoriesFound = true;
            }
            if ($t['id'] == 'out.c-main.products') {
                $productsFound = true;
            }
        }
        $this->assertTrue($categoriesFound, "Result of GET API call /tables should return configured table Categories");
        $this->assertTrue($productsFound, "Result of GET API call /tables should return configured table Products");

        $tableId = 'out.c-main.products';
        $result = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertArrayHasKey('table', $result, "Result of GET API call /tables?tableId= should contain 'table' key");
        $this->assertArrayHasKey('id', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.id' key");
        $this->assertArrayHasKey('name', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.name' key");
        $this->assertArrayHasKey('export', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.export' key");
        $this->assertArrayHasKey('isExported', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.isExported' key");
        $this->assertArrayHasKey('lastChangeDate', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.lastChangeDate' key");
        $this->assertArrayHasKey('incrementalLoad', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.incrementalLoad' key");
        $this->assertArrayHasKey('ignoreFilter', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.ignoreFilter' key");
        $this->assertArrayHasKey('columns', $result['table'], "Result of GET API call /tables?tableId= should contain 'table.columns' key");
        $column = current($result['table']['columns']);
        $this->assertArrayHasKey('name', $column, "Result of GET API call /tables?tableId= should contain column with 'name' key");
        $this->assertArrayHasKey('gdName', $column, "Result of GET API call /tables?tableId= should contain column with 'gdName' key");
        $this->assertArrayHasKey('type', $column, "Result of GET API call /tables?tableId= should contain column with 'type' key");

        $oldName = $result['table']['name'];
        $newName = uniqid();
        $columnId = $column['name'];
        $oldColumnName = $column['gdName'];
        $newColumnName = uniqid();

        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $newName);
        $this->client->updateTableColumn(FUNCTIONAL_WRITER_ID, $tableId, $column['name'], array('gdName' => $newColumnName));
        $result = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);

        $this->assertEquals($newName, $result['table']['name'], 'Table out.c-main.products should have new name');
        $column = array();
        foreach ($result['table']['columns'] as $c) {
            if ($c['name'] == $columnId) {
                $column = $c;
            }
        }
        $this->assertEquals($newColumnName, $column['gdName'], 'Table out.c-main.products should have column with new name');

        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $oldName);
        $this->client->updateTableColumns(FUNCTIONAL_WRITER_ID, $tableId, array(array('name' => $column['name'], 'gdName' => $oldColumnName)));

        $result = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($oldName, $result['table']['name'], 'Table out.c-main.products should have old name back');
        $column = array();
        foreach ($result['table']['columns'] as $c) {
            if ($c['name'] == $columnId) {
                $column = $c;
            }
        }
        $this->assertEquals($oldColumnName, $column['gdName'], 'Table out.c-main.products should have column with old name back');
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
        $this->assertArrayHasKey('name', $tables['tables'][0]);
        $this->assertArrayHasKey('identifier', $tables['tables'][0]);

        $tableId = $tables['tables'][0]['id'];
        $tableName = $tables['tables'][0]['name'];

        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertArrayHasKey('table', $table);
        $this->assertArrayHasKey('id', $table['table']);
        $this->assertArrayHasKey('name', $table['table']);
        $this->assertArrayHasKey('columns', $table['table']);
        $this->assertGreaterThanOrEqual(1, $table['table']['columns']);
        $this->assertEquals($tableName, $table['table']['name']);
        $this->assertArrayHasKey('name', $table['table']['columns'][0]);
        $this->assertArrayHasKey('gdName', $table['table']['columns'][0]);
        $columnName = $table['table']['columns'][0]['name'];
        $columnTitle = $table['table']['columns'][0]['gdName'];

        $newTableName = uniqid();
        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $newTableName);
        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($newTableName, $table['table']['name']);
        $this->client->updateTable(FUNCTIONAL_WRITER_ID, $tableId, $tableName);

        $newColumnTitle = uniqid();
        $this->client->updateTableColumn(FUNCTIONAL_WRITER_ID, $tableId, $columnName, ['gdName' => $newColumnTitle]);
        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($newColumnTitle, $table['table']['columns'][0]['gdName']);
        $this->client->updateTableColumns(FUNCTIONAL_WRITER_ID, $tableId, [['name' => $columnName, 'gdName' => $columnTitle]]);
        $table = $this->client->getTable(FUNCTIONAL_WRITER_ID, $tableId);
        $this->assertEquals($columnTitle, $table['table']['columns'][0]['gdName']);
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
        $projectId = substr(STORAGE_API_TOKEN, 0, strpos(STORAGE_API_TOKEN, '-'));
        $writerId = 'test' . uniqid();
        $this->client->createWriter($writerId);

        $email = $projectId. '-test-functional-' . uniqid() . '@test.com';
        $result = $this->client->createUser($writerId, $email, uniqid(), 'functional', 'test user');
        $this->assertArrayHasKey('uid', $result, "Result of createUser request should return uid of created user");

        $result = $this->client->createProject($writerId, '[Test] functional ' . uniqid());
        $this->assertArrayHasKey('pid', $result, "Result of createProject request should return pid of created project");
        $pid = $result['pid'];

        $result = $this->client->addUserToProject($writerId, $pid, $email);
        $this->assertArrayHasKey('status', $result, "Result of addUserToProject request should return status");
        $this->assertEquals('success', $result['status'], "Result of addUserToProject request should contain 'status' key with value 'success'");

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
        $this->assertCount(1, $result);

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

        $result = $this->client->post('/gooddata-writer/sync-filters', null, json_encode([
            'writerId' => FUNCTIONAL_WRITER_ID
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
}
