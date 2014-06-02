<?php

namespace SilexArangoDb\Tests\Silex\Provider;

use Silex\Application;
use SilexArangoDb\Silex\Provider\ArangoDbServiceProvider;
use triagens\ArangoDb\Collection;
use triagens\ArangoDb\ConnectionOptions;
use triagens\ArangoDb\UpdatePolicy;

class ArangoDbServiceProviderTest extends \PHPUnit_Framework_TestCase
{
    protected $configsTest = array(
        'arangodb1' => array(
            ConnectionOptions::OPTION_ENDPOINT => 'tcp://127.0.0.1:8529',
            ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            ConnectionOptions::OPTION_AUTH_USER => 'root',
            ConnectionOptions::OPTION_AUTH_PASSWD => '',
            ConnectionOptions::OPTION_CONNECTION => 'Close',
            ConnectionOptions::OPTION_TIMEOUT => 3,
            ConnectionOptions::OPTION_RECONNECT => true,
            ConnectionOptions::OPTION_CREATE => true,
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
            ConnectionOptions::OPTION_DATABASE => "unidadedigital",
        ),
        'arangodb2' => array(
            ConnectionOptions::OPTION_ENDPOINT => 'tcp://127.0.0.1:8529',
            ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            ConnectionOptions::OPTION_AUTH_USER => 'root',
            ConnectionOptions::OPTION_AUTH_PASSWD => '',
            ConnectionOptions::OPTION_CONNECTION => 'Keep-Alive',
            ConnectionOptions::OPTION_TIMEOUT => 5,
            ConnectionOptions::OPTION_RECONNECT => true,
            ConnectionOptions::OPTION_CREATE => true,
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
            ConnectionOptions::OPTION_DATABASE => "unidadedigital_test",
        ),
    );

    public function setUp()
    {
        parent::setUp();

        if (!class_exists('triagens\ArangoDb\Connection')) {
            $this->markTestSkipped('ArangoDB-PHP is not available');
        }
    }

    public function testSingleConnection()
    {
        $app = new Application();
        $app->register(
            new ArangoDbServiceProvider(),
            array(
                'arangodb.options' => $this->configsTest['arangodb1'],
            )
        );

        $arangodb = $app['arangodb'];

        $this->assertInstanceOf('triagens\ArangoDb\Connection', $arangodb);
        $this->assertEquals('unidadedigital', $arangodb->getOption(ConnectionOptions::OPTION_DATABASE));
        $this->assertSame($app['arangodbs']['default'], $arangodb);

        $response = $arangodb->get('/_admin/statistics');
        $this->assertTrue($response->getHttpCode() == 200, 'Did not return http code 200');
    }

    public function testMultipleConnections()
    {
        $app = new Application();
        $app->register(
            new ArangoDbServiceProvider(),
            array(
                'arangodbs.options' => $this->configsTest,
            )
        );

        $arangodb = $app['arangodb'];

        $this->assertInstanceOf('triagens\ArangoDb\Connection', $arangodb);
        $this->assertEquals('unidadedigital', $arangodb->getOption(ConnectionOptions::OPTION_DATABASE));
        $this->assertEquals('3', $arangodb->getOption(ConnectionOptions::OPTION_TIMEOUT));
        $this->assertEquals('Close', $arangodb->getOption(ConnectionOptions::OPTION_CONNECTION));
        $this->assertSame($app['arangodbs'][$app['arangodbs.default']], $arangodb);

        $response = $arangodb->get('/_admin/statistics');
        $this->assertTrue($response->getHttpCode() == 200, 'Did not return http code 200');

        $arangodb2 = $app['arangodbs']['arangodb2'];
        $this->assertInstanceOf('triagens\ArangoDb\Connection', $arangodb2);
        $this->assertEquals('unidadedigital_test', $arangodb2->getOption(ConnectionOptions::OPTION_DATABASE));
        $this->assertEquals('5', $arangodb2->getOption(ConnectionOptions::OPTION_TIMEOUT));
        $this->assertEquals('Keep-Alive', $arangodb2->getOption(ConnectionOptions::OPTION_CONNECTION));

        $response = $arangodb2->get('/_admin/statistics');
        $this->assertTrue($response->getHttpCode() == 200, 'Did not return http code 200');
    }

    /**
     * @depends testSingleConnection
     */
    public function testCollection()
    {
        $app = new Application();
        $app->register(
            new ArangoDbServiceProvider(),
            array(
                'arangodb.options' => $this->configsTest['arangodb2'],
            )
        );

        try {
            $app['arangodb.collection_handler']->drop('collection_test');
        } catch (\Exception $e) {
            //
        }

        $collection = $app['arangodb.collection']();
        $collection->setName('collection_test');
        $collection->setType(Collection::TYPE_DOCUMENT);
        $this->assertInstanceOf('triagens\ArangoDb\Collection', $collection);

        $this->assertTrue(is_numeric($app['arangodb.collection_handler']->create($collection)));
    }

    /**
     * @depends testCollection
     */
    public function testCreateDocument()
    {
        $app = new Application();
        $app->register(
            new ArangoDbServiceProvider(),
            array(
                'arangodb.options' => $this->configsTest['arangodb2'],
            )
        );

        try {
            $app['arangodb.collection_handler']->drop('collection_test');
        } catch (\Exception $e) {
            //
        }

        $collection = $app['arangodb.collection']();
        $collection->setName('collection_test');
        $collection->setType(Collection::TYPE_DOCUMENT);
        $app['arangodb.collection_handler']->create($collection);

        $document = $app['arangodb.document']();
        $document->set('name', 'Helder Santana');
        $document->set('email', 'contato@heldersantana.net');
        $this->assertInstanceOf('triagens\ArangoDb\Document', $document);
        $this->assertTrue(is_numeric($app['arangodb.document_handler']->save('collection_test', $document)));
    }
}
