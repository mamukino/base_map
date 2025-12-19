<?php
/**
 * Database Schema Visualizer - Configuration File
 *
 * Universal configuration for MySQL, Oracle (OCI), and SQL Server connections.
 * Modify the settings below to match your database environment.
 *
 * @package SchemaVisualizer
 * @version 1.0.0
 */

return [
    /*
    |--------------------------------------------------------------------------
    | Database Type
    |--------------------------------------------------------------------------
    |
    | Supported values: 'mysql', 'oci', 'sqlsrv'
    | - mysql:  MySQL / MariaDB
    | - oci:    Oracle Database (requires OCI8 extension)
    | - sqlsrv: Microsoft SQL Server (requires sqlsrv extension)
    |
    */
    'db_type' => 'mysql',

    /*
    |--------------------------------------------------------------------------
    | Database Host
    |--------------------------------------------------------------------------
    |
    | The hostname or IP address of your database server.
    | For Oracle, this can be a TNS name or connection string.
    | Examples: 'localhost', '127.0.0.1', 'db.example.com', 'oracle-server:1521/ORCL'
    |
    */
    'host' => 'localhost',

    /*
    |--------------------------------------------------------------------------
    | Database Port
    |--------------------------------------------------------------------------
    |
    | The port number for database connection.
    | Default ports: MySQL=3306, Oracle=1521, SQL Server=1433
    |
    */
    'port' => 3306,

    /*
    |--------------------------------------------------------------------------
    | Database Name / Service Name
    |--------------------------------------------------------------------------
    |
    | The name of the database to connect to.
    | For Oracle, this is the service name or SID.
    |
    */
    'database' => 'your_database_name',

    /*
    |--------------------------------------------------------------------------
    | Database Username
    |--------------------------------------------------------------------------
    |
    | The username for database authentication.
    |
    */
    'username' => 'your_username',

    /*
    |--------------------------------------------------------------------------
    | Database Password
    |--------------------------------------------------------------------------
    |
    | The password for database authentication.
    |
    */
    'password' => 'your_password',

    /*
    |--------------------------------------------------------------------------
    | Character Set (MySQL/SQL Server)
    |--------------------------------------------------------------------------
    |
    | The character set for the connection.
    |
    */
    'charset' => 'utf8mb4',

    /*
    |--------------------------------------------------------------------------
    | Schema Filter (Optional)
    |--------------------------------------------------------------------------
    |
    | For Oracle and SQL Server, you can specify a schema to filter.
    | Leave empty to use the default schema for the connected user.
    |
    */
    'schema' => '',

    /*
    |--------------------------------------------------------------------------
    | Connection Options
    |--------------------------------------------------------------------------
    |
    | Additional PDO options for the connection.
    |
    */
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
