<?php

namespace SilexArangoDb\Silex\Provider;

use Silex\Application;
use Silex\ServiceProviderInterface;
use triagens\ArangoDb\AdminHandler;
use triagens\ArangoDb\Collection;
use triagens\ArangoDb\CollectionHandler;
use triagens\ArangoDb\ConnectionOptions;
use triagens\ArangoDb\Document;
use triagens\ArangoDb\DocumentHandler;
use triagens\ArangoDb\Edge;
use triagens\ArangoDb\EdgeHandler;
use triagens\ArangoDb\Graph;
use triagens\ArangoDb\GraphHandler;
use triagens\ArangoDb\Statement;
use triagens\ArangoDb\Transaction;
use triagens\ArangoDb\UpdatePolicy;
use triagens\ArangoDb\Connection;
use triagens\ArangoDb\UserHandler;

class ArangoDbServiceProvider implements ServiceProviderInterface
{
    public function register(Application $app)
    {
        $app['arangodb.default_options'] = array(
            // database name
            ConnectionOptions::OPTION_DATABASE => '_system',
            // server endpoint to connect to
            ConnectionOptions::OPTION_ENDPOINT => 'tcp://127.0.0.1:8529',
            // authorization type to use (currently supported: 'Basic')
            ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            // user for basic authorization
            ConnectionOptions::OPTION_AUTH_USER => 'root',
            // password for basic authorization
            ConnectionOptions::OPTION_AUTH_PASSWD => '',
            // connection persistence on server. can use either 'Close' (one-time connections) or 'Keep-Alive' (re-used connections)
            ConnectionOptions::OPTION_CONNECTION => 'Close',
            // connect timeout in seconds
            ConnectionOptions::OPTION_TIMEOUT => 3,
            // whether or not to reconnect when a keep-alive connection has timed out on server
            ConnectionOptions::OPTION_RECONNECT => true,
            // optionally create new collections when inserting documents
            ConnectionOptions::OPTION_CREATE => true,
            // optionally create new collections when inserting documents
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
        );

        $app['arangodbs.options.initializer'] = $app->protect(
            function () use ($app) {
                static $initialized = false;

                if ($initialized) {
                    return;
                }

                $initialized = true;

                if (!isset($app['arangodbs.options'])) {
                    $app['arangodbs.options'] = array(
                        'default' => isset($app['arangodb.options']) ? $app['arangodb.options'] : array()
                    );
                }

                $tmp = $app['arangodbs.options'];
                foreach ($tmp as $name => &$options) {
                    $options = array_replace_recursive($app['arangodb.default_options'], $options);

                    if (!isset($app['arangodbs.default'])) {
                        $app['arangodbs.default'] = $name;
                    }
                }

                $app['arangodbs.options'] = $tmp;
            }
        );

        $app['arangodbs'] = $app->share(
            function ($app) {
                $app['arangodbs.options.initializer']();

                $dbs = new \Pimple();
                foreach (array_keys($app['arangodbs.options']) as $name) {
                    if ($app['arangodbs.default'] === $name) {
                        // we use shortcuts here in case the default has been overridden
                        $config = $app['arangodb.config'];
                    } else {
                        $config = $app['arangodbs.config'][$name];
                    }

                    $dbs[$name] = $dbs->share(
                        function () use ($config) {
                            return new Connection($config->getAll());
                        }
                    );
                }

                return $dbs;
            }
        );

        $app['arangodb'] = $app->share(
            function ($app) {
                $dbs = $app['arangodbs'];

                return $dbs[$app['arangodbs.default']];
            }
        );

        $app['arangodbs.config'] = $app->share(
            function ($app) {
                $app['arangodbs.options.initializer']();

                $configs = new \Pimple();
                foreach ($app['arangodbs.options'] as $name => $options) {
                    $configs[$name] = new ConnectionOptions($options);
                }

                return $configs;
            }
        );

        $app['arangodb.config'] = $app->share(
            function ($app) {
                $dbs = $app['arangodbs.config'];

                return $dbs[$app['arangodbs.default']];
            }
        );

        $this->collectionManagement($app);
        $this->documentManagement($app);
        $this->edgeManagement($app);
        $this->graphManagement($app);
        $this->statementManagement($app);
    }

    /**
     * @inheritdoc
     */
    public function boot(Application $app)
    {
    }

    protected function collectionManagement(Application $app)
    {
        $app['arangodb.collection'] = $app->protect(
            function (array $data = []) use ($app) {
                if (!empty($data)) {
                    return Collection::createFromArray($data);
                }

                return new Collection();
            }
        );

        $app['arangodb.collection_handler'] = $app->share(
            function ($app) {
                $db = $app['arangodb'];

                return new CollectionHandler($db);
            }
        );

        $app['arangodbs.collection_handler'] = $app->share(
            function ($app) {
                $app['arangodbs.options.initializer']();

                $handlers = new \Pimple();
                foreach ($app['arangodbs.options'] as $name => $options) {
                    $handlers[$name] = new CollectionHandler($app['arangodbs'][$name]);
                }

                return $handlers;
            }
        );
    }

    protected function documentManagement(Application $app)
    {
        $app['arangodb.document'] = $app->protect(
            function (array $data = []) use ($app) {
                if (!empty($data)) {
                    return Document::createFromArray($data);
                }
                return new Document();
            }
        );

        $app['arangodb.document_handler'] = $app->share(
            function ($app) {
                $db = $app['arangodb'];

                return new DocumentHandler($db);
            }
        );

        $app['arangodbs.document_handler'] = $app->share(
            function ($app) {
                $app['arangodbs.options.initializer']();

                $handlers = new \Pimple();
                foreach ($app['arangodbs.options'] as $name => $options) {
                    $handlers[$name] = new DocumentHandler($app['arangodbs'][$name]);
                }

                return $handlers;
            }
        );
    }

    protected function edgeManagement(Application $app)
    {
        $app['arangodb.edge'] = $app->protect(
            function (array $data = []) use ($app) {
                if (!empty($data)) {
                    return Edge::createFromArray($data);
                }

                return new Edge();
            }
        );

        $app['arangodb.edge_handler'] = $app->share(
            function ($app) {
                $db = $app['arangodb'];

                return new EdgeHandler($db);
            }
        );

        $app['arangodbs.edge_handler'] = $app->share(
            function ($app) {
                $app['arangodbs.options.initializer']();

                $handlers = new \Pimple();
                foreach ($app['arangodbs.options'] as $name => $options) {
                    $handlers[$name] = new EdgeHandler($app['arangodbs'][$name]);
                }

                return $handlers;
            }
        );
    }

    protected function graphManagement(Application $app)
    {
        $app['arangodb.graph'] = $app->protect(
            function (array $data = []) use ($app) {
                if (!empty($data)) {
                    return Graph::createFromArray($data);
                }

                return new Graph();
            }
        );

        $app['arangodb.graph_handler'] = $app->share(
            function ($app) {
                $db = $app['arangodb'];

                return new GraphHandler($db);
            }
        );

        $app['arangodbs.graph_handler'] = $app->share(
            function ($app) {
                $app['arangodbs.options.initializer']();

                $handlers = new \Pimple();
                foreach ($app['arangodbs.options'] as $name => $options) {
                    $handlers[$name] = new GraphHandler($app['arangodbs'][$name]);
                }

                return $handlers;
            }
        );
    }

    protected function statementManagement(Application $app)
    {
        $app['arangodb.statement'] = $app->protect(
            function (array $query) use ($app) {
                $db = $app['arangodb'];

                $statement = new Statement($db, $query);

                return $statement->execute();
            }
        );

        $app['arangodbs.statement'] = $app->protect(
            function (array $query) use ($app) {
                $app['arangodbs.options.initializer']();

                $handlers = new \Pimple();
                foreach ($app['arangodbs.options'] as $name => $options) {
                    $handlers[$name] = new Statement($app['arangodbs'][$name], $query);
                }

                return $handlers;
            }
        );
    }
}
