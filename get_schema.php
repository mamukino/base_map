<?php
/**
 * Database Schema Visualizer - Data API
 *
 * Extracts database schema metadata including tables, foreign keys,
 * and trigger dependencies. Returns JSON formatted for Cytoscape.js.
 *
 * @package SchemaVisualizer
 * @version 1.0.0
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

// Error handling
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

try {
    $config = require __DIR__ . '/config.php';

    $extractor = new SchemaExtractor($config);
    $data = $extractor->extract();

    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => true,
        'message' => $e->getMessage(),
        'trace' => defined('DEBUG') && DEBUG ? $e->getTraceAsString() : null
    ]);
}

/**
 * Schema Extractor Class
 *
 * Handles database connection and metadata extraction for multiple database types.
 */
class SchemaExtractor
{
    private $config;
    private $connection;
    private $dbType;

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->dbType = strtolower($config['db_type']);
        $this->connect();
    }

    /**
     * Establish database connection based on type
     */
    private function connect(): void
    {
        switch ($this->dbType) {
            case 'mysql':
                $this->connectMySQL();
                break;
            case 'oci':
                $this->connectOracle();
                break;
            case 'sqlsrv':
                $this->connectSQLServer();
                break;
            default:
                throw new Exception("Unsupported database type: {$this->dbType}");
        }
    }

    private function connectMySQL(): void
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $this->config['host'],
            $this->config['port'] ?? 3306,
            $this->config['database'],
            $this->config['charset'] ?? 'utf8mb4'
        );

        $this->connection = new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            $this->config['options'] ?? []
        );
    }

    private function connectOracle(): void
    {
        // For Oracle, we use OCI8 if available, otherwise PDO_OCI
        $host = $this->config['host'];
        $port = $this->config['port'] ?? 1521;
        $database = $this->config['database'];

        // Build connection string
        $connectionString = "(DESCRIPTION=(ADDRESS=(PROTOCOL=TCP)(HOST={$host})(PORT={$port}))(CONNECT_DATA=(SERVICE_NAME={$database})))";

        if (extension_loaded('oci8')) {
            $this->connection = oci_connect(
                $this->config['username'],
                $this->config['password'],
                $connectionString
            );

            if (!$this->connection) {
                $error = oci_error();
                throw new Exception("Oracle connection failed: " . ($error['message'] ?? 'Unknown error'));
            }
        } else {
            // Fallback to PDO_OCI
            $dsn = "oci:dbname={$connectionString}";
            $this->connection = new PDO(
                $dsn,
                $this->config['username'],
                $this->config['password'],
                $this->config['options'] ?? []
            );
        }
    }

    private function connectSQLServer(): void
    {
        $dsn = sprintf(
            'sqlsrv:Server=%s,%d;Database=%s',
            $this->config['host'],
            $this->config['port'] ?? 1433,
            $this->config['database']
        );

        $this->connection = new PDO(
            $dsn,
            $this->config['username'],
            $this->config['password'],
            $this->config['options'] ?? []
        );
    }

    /**
     * Extract all schema metadata
     */
    public function extract(): array
    {
        $tables = $this->getTables();
        $foreignKeys = $this->getForeignKeys();
        $triggerDeps = $this->getTriggerDependencies();

        return $this->formatForCytoscape($tables, $foreignKeys, $triggerDeps);
    }

    /**
     * Get all tables in the database
     */
    private function getTables(): array
    {
        switch ($this->dbType) {
            case 'mysql':
                return $this->getMySQLTables();
            case 'oci':
                return $this->getOracleTables();
            case 'sqlsrv':
                return $this->getSQLServerTables();
            default:
                return [];
        }
    }

    private function getMySQLTables(): array
    {
        $stmt = $this->connection->query("
            SELECT
                TABLE_NAME as table_name,
                TABLE_ROWS as row_count,
                TABLE_TYPE as table_type
            FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_TYPE = 'BASE TABLE'
            ORDER BY TABLE_NAME
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getOracleTables(): array
    {
        $schema = $this->config['schema'] ?: strtoupper($this->config['username']);

        if (is_resource($this->connection)) {
            // OCI8
            $sql = "SELECT TABLE_NAME as table_name, NUM_ROWS as row_count, 'TABLE' as table_type
                    FROM ALL_TABLES WHERE OWNER = :schema ORDER BY TABLE_NAME";
            $stmt = oci_parse($this->connection, $sql);
            oci_bind_by_name($stmt, ':schema', $schema);
            oci_execute($stmt);

            $tables = [];
            while ($row = oci_fetch_assoc($stmt)) {
                $tables[] = array_change_key_case($row, CASE_LOWER);
            }
            return $tables;
        } else {
            // PDO
            $stmt = $this->connection->prepare("
                SELECT TABLE_NAME as table_name, NUM_ROWS as row_count, 'TABLE' as table_type
                FROM ALL_TABLES
                WHERE OWNER = :schema
                ORDER BY TABLE_NAME
            ");
            $stmt->execute(['schema' => $schema]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    private function getSQLServerTables(): array
    {
        $schema = $this->config['schema'] ?: 'dbo';

        $stmt = $this->connection->prepare("
            SELECT
                t.name as table_name,
                p.rows as row_count,
                'TABLE' as table_type
            FROM sys.tables t
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            INNER JOIN sys.partitions p ON t.object_id = p.object_id AND p.index_id IN (0, 1)
            WHERE s.name = :schema
            ORDER BY t.name
        ");
        $stmt->execute(['schema' => $schema]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get foreign key relationships
     */
    private function getForeignKeys(): array
    {
        switch ($this->dbType) {
            case 'mysql':
                return $this->getMySQLForeignKeys();
            case 'oci':
                return $this->getOracleForeignKeys();
            case 'sqlsrv':
                return $this->getSQLServerForeignKeys();
            default:
                return [];
        }
    }

    private function getMySQLForeignKeys(): array
    {
        $stmt = $this->connection->query("
            SELECT
                kcu.TABLE_NAME as child_table,
                kcu.COLUMN_NAME as child_column,
                kcu.REFERENCED_TABLE_NAME as parent_table,
                kcu.REFERENCED_COLUMN_NAME as parent_column,
                kcu.CONSTRAINT_NAME as constraint_name
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = DATABASE()
            AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            ORDER BY kcu.TABLE_NAME, kcu.CONSTRAINT_NAME
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getOracleForeignKeys(): array
    {
        $schema = $this->config['schema'] ?: strtoupper($this->config['username']);

        $sql = "
            SELECT
                c.TABLE_NAME as child_table,
                cc.COLUMN_NAME as child_column,
                r.TABLE_NAME as parent_table,
                rc.COLUMN_NAME as parent_column,
                c.CONSTRAINT_NAME as constraint_name
            FROM ALL_CONSTRAINTS c
            JOIN ALL_CONS_COLUMNS cc ON c.CONSTRAINT_NAME = cc.CONSTRAINT_NAME AND c.OWNER = cc.OWNER
            JOIN ALL_CONSTRAINTS r ON c.R_CONSTRAINT_NAME = r.CONSTRAINT_NAME AND c.R_OWNER = r.OWNER
            JOIN ALL_CONS_COLUMNS rc ON r.CONSTRAINT_NAME = rc.CONSTRAINT_NAME AND r.OWNER = rc.OWNER
            WHERE c.CONSTRAINT_TYPE = 'R'
            AND c.OWNER = :schema
            ORDER BY c.TABLE_NAME, c.CONSTRAINT_NAME
        ";

        if (is_resource($this->connection)) {
            $stmt = oci_parse($this->connection, $sql);
            oci_bind_by_name($stmt, ':schema', $schema);
            oci_execute($stmt);

            $fks = [];
            while ($row = oci_fetch_assoc($stmt)) {
                $fks[] = array_change_key_case($row, CASE_LOWER);
            }
            return $fks;
        } else {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['schema' => $schema]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    private function getSQLServerForeignKeys(): array
    {
        $schema = $this->config['schema'] ?: 'dbo';

        $stmt = $this->connection->prepare("
            SELECT
                tp.name as child_table,
                cp.name as child_column,
                tr.name as parent_table,
                cr.name as parent_column,
                fk.name as constraint_name
            FROM sys.foreign_keys fk
            INNER JOIN sys.tables tp ON fk.parent_object_id = tp.object_id
            INNER JOIN sys.tables tr ON fk.referenced_object_id = tr.object_id
            INNER JOIN sys.foreign_key_columns fkc ON fk.object_id = fkc.constraint_object_id
            INNER JOIN sys.columns cp ON fkc.parent_column_id = cp.column_id AND fkc.parent_object_id = cp.object_id
            INNER JOIN sys.columns cr ON fkc.referenced_column_id = cr.column_id AND fkc.referenced_object_id = cr.object_id
            INNER JOIN sys.schemas s ON tp.schema_id = s.schema_id
            WHERE s.name = :schema
            ORDER BY tp.name, fk.name
        ");
        $stmt->execute(['schema' => $schema]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get trigger dependencies
     */
    private function getTriggerDependencies(): array
    {
        switch ($this->dbType) {
            case 'mysql':
                return $this->getMySQLTriggerDeps();
            case 'oci':
                return $this->getOracleTriggerDeps();
            case 'sqlsrv':
                return $this->getSQLServerTriggerDeps();
            default:
                return [];
        }
    }

    private function getMySQLTriggerDeps(): array
    {
        $stmt = $this->connection->query("SHOW TRIGGERS");
        $triggers = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $dependencies = [];
        $allTables = array_column($this->getMySQLTables(), 'table_name');

        foreach ($triggers as $trigger) {
            $triggerTable = $trigger['Table'];
            $triggerName = $trigger['Trigger'];
            $triggerBody = $trigger['Statement'] ?? '';

            // Parse trigger body for table references using regex
            $referencedTables = $this->parseTriggerBodyForTables($triggerBody, $allTables);

            foreach ($referencedTables as $refTable) {
                // Skip self-references
                if (strtolower($refTable) !== strtolower($triggerTable)) {
                    $dependencies[] = [
                        'trigger_name' => $triggerName,
                        'source_table' => $triggerTable,
                        'target_table' => $refTable,
                        'dependency_type' => 'TRIGGER'
                    ];
                }
            }
        }

        return $dependencies;
    }

    /**
     * Parse trigger body to find referenced table names
     */
    private function parseTriggerBodyForTables(string $body, array $knownTables): array
    {
        $foundTables = [];

        // Patterns to find table references in SQL
        $patterns = [
            // INSERT INTO table_name
            '/\bINSERT\s+INTO\s+[`"\']?(\w+)[`"\']?/i',
            // UPDATE table_name
            '/\bUPDATE\s+[`"\']?(\w+)[`"\']?\s+SET/i',
            // DELETE FROM table_name
            '/\bDELETE\s+FROM\s+[`"\']?(\w+)[`"\']?/i',
            // FROM table_name (SELECT)
            '/\bFROM\s+[`"\']?(\w+)[`"\']?/i',
            // JOIN table_name
            '/\bJOIN\s+[`"\']?(\w+)[`"\']?/i',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $body, $matches)) {
                foreach ($matches[1] as $tableName) {
                    // Check if this is actually a known table
                    foreach ($knownTables as $knownTable) {
                        if (strtolower($tableName) === strtolower($knownTable)) {
                            $foundTables[$knownTable] = true;
                            break;
                        }
                    }
                }
            }
        }

        return array_keys($foundTables);
    }

    private function getOracleTriggerDeps(): array
    {
        $schema = $this->config['schema'] ?: strtoupper($this->config['username']);

        // Use USER_DEPENDENCIES to map trigger-to-table links
        $sql = "
            SELECT DISTINCT
                d.NAME as trigger_name,
                t.TABLE_NAME as source_table,
                d.REFERENCED_NAME as target_table,
                'TRIGGER' as dependency_type
            FROM ALL_DEPENDENCIES d
            JOIN ALL_TRIGGERS t ON d.NAME = t.TRIGGER_NAME AND d.OWNER = t.OWNER
            WHERE d.OWNER = :schema
            AND d.TYPE = 'TRIGGER'
            AND d.REFERENCED_TYPE = 'TABLE'
            AND d.REFERENCED_NAME != t.TABLE_NAME
            ORDER BY t.TABLE_NAME, d.NAME
        ";

        if (is_resource($this->connection)) {
            $stmt = oci_parse($this->connection, $sql);
            oci_bind_by_name($stmt, ':schema', $schema);
            oci_execute($stmt);

            $deps = [];
            while ($row = oci_fetch_assoc($stmt)) {
                $deps[] = array_change_key_case($row, CASE_LOWER);
            }
            return $deps;
        } else {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute(['schema' => $schema]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        }
    }

    private function getSQLServerTriggerDeps(): array
    {
        $schema = $this->config['schema'] ?: 'dbo';

        // Use sys.sql_expression_dependencies to find trigger dependencies
        $stmt = $this->connection->prepare("
            SELECT DISTINCT
                tr.name as trigger_name,
                t.name as source_table,
                OBJECT_NAME(sed.referenced_id) as target_table,
                'TRIGGER' as dependency_type
            FROM sys.triggers tr
            INNER JOIN sys.tables t ON tr.parent_id = t.object_id
            INNER JOIN sys.schemas s ON t.schema_id = s.schema_id
            INNER JOIN sys.sql_expression_dependencies sed ON tr.object_id = sed.referencing_id
            WHERE s.name = :schema
            AND sed.referenced_id IS NOT NULL
            AND OBJECT_NAME(sed.referenced_id) != t.name
            AND OBJECTPROPERTY(sed.referenced_id, 'IsUserTable') = 1
            ORDER BY t.name, tr.name
        ");
        $stmt->execute(['schema' => $schema]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Format data for Cytoscape.js consumption
     */
    private function formatForCytoscape(array $tables, array $foreignKeys, array $triggerDeps): array
    {
        $nodes = [];
        $edges = [];
        $tableSet = [];

        // Create nodes for each table
        foreach ($tables as $table) {
            $tableName = $table['table_name'];
            $tableSet[$tableName] = true;

            $nodes[] = [
                'data' => [
                    'id' => $tableName,
                    'label' => $tableName,
                    'rowCount' => $table['row_count'] ?? 0,
                    'type' => 'table'
                ]
            ];
        }

        // Create edges for foreign keys
        $fkEdgeMap = [];
        foreach ($foreignKeys as $fk) {
            $childTable = $fk['child_table'];
            $parentTable = $fk['parent_table'];

            // Skip if tables don't exist in our set
            if (!isset($tableSet[$childTable]) || !isset($tableSet[$parentTable])) {
                continue;
            }

            // Create unique edge ID to avoid duplicates
            $edgeId = "fk_{$childTable}_{$parentTable}";

            if (!isset($fkEdgeMap[$edgeId])) {
                $fkEdgeMap[$edgeId] = [
                    'data' => [
                        'id' => $edgeId,
                        'source' => $childTable,
                        'target' => $parentTable,
                        'type' => 'foreign_key',
                        'label' => 'FK',
                        'constraints' => [],
                        'columns' => []
                    ]
                ];
            }

            // Add constraint details
            $fkEdgeMap[$edgeId]['data']['constraints'][] = $fk['constraint_name'];
            $fkEdgeMap[$edgeId]['data']['columns'][] = [
                'child' => $fk['child_column'],
                'parent' => $fk['parent_column']
            ];
        }

        $edges = array_values($fkEdgeMap);

        // Create edges for trigger dependencies
        $triggerEdgeMap = [];
        foreach ($triggerDeps as $dep) {
            $sourceTable = $dep['source_table'];
            $targetTable = $dep['target_table'];

            // Skip if tables don't exist in our set
            if (!isset($tableSet[$sourceTable]) || !isset($tableSet[$targetTable])) {
                continue;
            }

            // Create unique edge ID
            $edgeId = "trigger_{$sourceTable}_{$targetTable}";

            if (!isset($triggerEdgeMap[$edgeId])) {
                $triggerEdgeMap[$edgeId] = [
                    'data' => [
                        'id' => $edgeId,
                        'source' => $sourceTable,
                        'target' => $targetTable,
                        'type' => 'trigger',
                        'label' => 'TRG',
                        'triggers' => []
                    ]
                ];
            }

            $triggerEdgeMap[$edgeId]['data']['triggers'][] = $dep['trigger_name'];
        }

        $edges = array_merge($edges, array_values($triggerEdgeMap));

        // Calculate connection stats
        $connectionCount = [];
        foreach ($edges as $edge) {
            $source = $edge['data']['source'];
            $target = $edge['data']['target'];
            $connectionCount[$source] = ($connectionCount[$source] ?? 0) + 1;
            $connectionCount[$target] = ($connectionCount[$target] ?? 0) + 1;
        }

        // Update nodes with connection count
        foreach ($nodes as &$node) {
            $tableName = $node['data']['id'];
            $node['data']['connections'] = $connectionCount[$tableName] ?? 0;
            $node['data']['isolated'] = ($connectionCount[$tableName] ?? 0) === 0;
        }

        return [
            'nodes' => $nodes,
            'edges' => $edges,
            'meta' => [
                'dbType' => $this->dbType,
                'database' => $this->config['database'],
                'tableCount' => count($tables),
                'fkCount' => count($fkEdgeMap),
                'triggerDepCount' => count($triggerEdgeMap),
                'generatedAt' => date('Y-m-d H:i:s')
            ]
        ];
    }

    public function __destruct()
    {
        if ($this->dbType === 'oci' && is_resource($this->connection)) {
            oci_close($this->connection);
        }
        $this->connection = null;
    }
}
