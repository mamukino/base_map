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
    'db_type' => 'oci',

    /*
    |--------------------------------------------------------------------------
    | Database Host / Connection String
    |--------------------------------------------------------------------------
    |
    | For MySQL/SQL Server: hostname or IP address
    | For Oracle, supports multiple formats:
    |   - EZConnect:      '//10.100.100.88:1521/testdb' or '//10.100.100.88/testdb'
    |   - TNS Descriptor: '(DESCRIPTION=(ADDRESS=...))'
    |   - Leave empty and set 'database' to a TNS alias from tnsnames.ora
    |
    */
    'host' => '//10.100.100.88/testdb',

    /*
    |--------------------------------------------------------------------------
    | Database Port
    |--------------------------------------------------------------------------
    |
    | The port number for database connection.
    | Default ports: MySQL=3306, Oracle=1521, SQL Server=1433
    | Note: For Oracle EZConnect in 'host', port can be included there instead.
    |
    */
    'port' => 1521,

    /*
    |--------------------------------------------------------------------------
    | Database Name / Service Name
    |--------------------------------------------------------------------------
    |
    | For MySQL/SQL Server: The database name
    | For Oracle: Service name, SID, or TNS alias
    | Note: If using EZConnect format in 'host', this can be left empty.
    |
    */
    'database' => '',

    /*
    |--------------------------------------------------------------------------
    | Database Username
    |--------------------------------------------------------------------------
    */
    'username' => 'enreg',

    /*
    |--------------------------------------------------------------------------
    | Database Password
    |--------------------------------------------------------------------------
    */
    'password' => 'test',

    /*
    |--------------------------------------------------------------------------
    | Character Set
    |--------------------------------------------------------------------------
    |
    | The character set for the connection.
    | MySQL default: utf8mb4, Oracle default: UTF8
    |
    */
    'charset' => 'UTF8',

    /*
    |--------------------------------------------------------------------------
    | Schema Filter (Optional)
    |--------------------------------------------------------------------------
    |
    | For Oracle and SQL Server, specify the schema to query.
    | For Oracle: defaults to the connected username (uppercase)
    | For SQL Server: defaults to 'dbo'
    |
    */
    'schema' => 'ENREG',

    /*
    |--------------------------------------------------------------------------
    | Connection Options (PDO)
    |--------------------------------------------------------------------------
    |
    | Additional PDO options for the connection.
    | Note: OCI8 native driver ignores these options.
    |
    */
    'options' => [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ],
];
