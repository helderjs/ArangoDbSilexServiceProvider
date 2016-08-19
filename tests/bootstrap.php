<?php

use triagens\ArangoDb\ConnectionOptions;
use triagens\ArangoDb\Connection;
use triagens\ArangoDb\UpdatePolicy;
use triagens\ArangoDb\Database;
use triagens\ArangoDb\Exception;

$loader = require realpath(__DIR__ . '/../vendor/autoload.php');

$prepareDatabase = function() {
    $connection = new Connection(
        [
            ConnectionOptions::OPTION_ENDPOINT => empty($_ENV['WERCKER']) ? "tcp://localhost:8529" : $_ENV['ARANGODB_PORT_8529_TCP'],
            ConnectionOptions::OPTION_AUTH_TYPE => 'Basic',
            ConnectionOptions::OPTION_AUTH_USER => 'root',
            ConnectionOptions::OPTION_AUTH_PASSWD => 'pass2arango',
            ConnectionOptions::OPTION_CONNECTION => 'Close',
            ConnectionOptions::OPTION_TIMEOUT => 3,
            ConnectionOptions::OPTION_RECONNECT => true,
            ConnectionOptions::OPTION_CREATE => true,
            ConnectionOptions::OPTION_UPDATE_POLICY => UpdatePolicy::LAST,
        ]
    );
    
    try {
        Database::delete($connection, 'db_test1');
        Database::delete($connection, 'db_test2');
    } catch (Exception $e) {}
    
    Database::create($connection, 'db_test1');
    Database::create($connection, 'db_test2');
};

$prepareDatabase();
