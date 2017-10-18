<?php

namespace DataImporter\Services;

use DataImporter\TableNodes\Table;
use Exception;
use PDO;
use RecursiveArrayIterator;
use RecursiveIteratorIterator;

class BasicService
{
    /**
     * @var string
     */
    protected $file;

    /**
     * @var array
     */
    protected $config;

    /**
     * @var PDO
     */
    protected $connection;

    /**
     * @var array
     */
    protected $tables;

    /**
     * @var Table[]
     */
    protected $tablesTreeMap;

    /**
     * @param string $mode : 'export' | 'import'
     * @param string $filePath
     */
    protected function __construct(string $mode, string $filePath = '')
    {
        $this->loadConfig();
        $this->setExportFile($mode, $filePath);
        $this->listAllTables();
        $this->buildTablesTreeMap();
        $this->createConnection($mode);
    }

    /**
     * Set the export file name and path, and cleanup the file if exists.
     *
     * @param string $mode
     * @param string $path
     */
    protected function setExportFile(string $mode, string $path) {
        $this->file = empty($path) ? $this->config['export_path'] : $path;

        if ($mode == 'export' && file_exists($this->file)) {
            unlink($this->file);
        }
    }

    /**
     * Execute a given query against the set connection.
     *
     * @param string $query
     * @param array $args
     * @param bool $prepare
     * @return array
     */
    protected function executeQuery(string $query, array $args = [], bool $prepare = true)
    {
        try {
            if ($prepare) {
                $statement = $this->connection->prepare($query);
                $statement->execute($args);

                $result = $statement->fetchAll(PDO::FETCH_ASSOC);
            } else {
                $result = $this->connection->exec($query);
            }
        } catch (Exception $e) {
            print 'Error!: ' . $e->getMessage() . PHP_EOL;
            die();
        }

        return $result;
    }

    /**
     * Put quote around the value (if necessary) and scape special chars.
     *
     * @param $value
     * @return string
     */
    protected function quoteString($value)
    {
        return $this->connection->quote($value);
    }

    /**
     * Load and set configurations.
     */
    protected function loadConfig()
    {
        $config = null;

        foreach ([__DIR__.'/../../../test/di_config.php', __DIR__.'/../../../../di_config.php', __DIR__.'/../../../di_config.php'] as $file) {
            if (file_exists($file)) {
                $config = $file;
                break;
            }
        }

        if (! $config) {
            echo 'Unable to find the config file.' . PHP_EOL;
            die();
        }

        $this->config = require $config;
    }

    /**
     * Extract and set the list of all tables in database from the config file.
     *
     * @param string $context
     * @return array
     */
    protected function listAllTables($context = '')
    {
        $tables = [];

        if (empty($context)) {
            $config = $this->config['fixed_tables'] + $this->config['operational_tables'] + $this->config['ignored_tables'];
        } else {
            $config = $this->config[$context];
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveArrayIterator($config),
            RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $key => $value) {
            if (in_array($key, ['has_many', 'belongs_to', 'foreign_key', 'other_key', 'pk'])) {
                continue;
            }

            $tables[] = str_replace('@', '', $key);
        }

        $this->tables = array_values(array_unique($tables));
    }

    /**
     * Building a map between the root table nodes and their constructed tables tree structure.
     */
    protected function buildTablesTreeMap()
    {
        $treeMap = [];

        foreach ($this->config['fixed_tables'] as $root => $children) {
            $treeMap[$root] = $this->buildTablesTree($root, $children, '');
        }

        foreach ($this->config['operational_tables'] as $root => $children) {
            $treeMap[$root] = $this->buildTablesTree($root, $children, '');
        }

        $this->tablesTreeMap = $treeMap;
    }

    /**
     * Recursively build a tree structure containing Table objects on each node.
     *
     * @param string $tableName
     * @param array $children
     * @param string $relation
     * @param Table|null $parent
     * @return Table
     */
    protected function buildTablesTree(string $tableName, array $children, string $relation, Table $parent = null)
    {
        $table = new Table();
        $table->name = $tableName;
        $table->parent = $parent;
        $table->pk = isset($children['pk']) ? $children['pk'] : 'id';
        $table->foreignKey = isset($children['foreign_key']) ? $children['foreign_key'] : null;
        $table->otherKey = isset($children['other_key']) ? $children['other_key'] : 'id';
        $table->relation = $relation;
        $table->children = [];

        foreach ($children as $key => $values) {
            if ($key == 'has_many') {
                foreach ($values as $tName => $tChildren) {
                    $table->children[] = $this->buildTablesTree($tName, $tChildren, 'has_many', $table);
                }
            }
            if ($key == 'belongs_to') {
                foreach ($values as $tName => $tChildren) {
                    $table->children[] = $this->buildTablesTree($tName, $tChildren, 'belongs_to', $table);
                }
            }
        }

        return $table;
    }

    /**
     * Create and set a new PDO connection based on the mode given and configured credentials.
     *
     * @param $mode: 'export' | 'import'
     */
    protected function createConnection($mode)
    {
        $username = $this->config["{$mode}_database"]["username"];
        $password = $this->config["{$mode}_database"]["password"];
        $host = $this->config["{$mode}_database"]["host"];
        $port = $this->config["{$mode}_database"]["port"];
        $db = $this->config["{$mode}_database"]["database"];
        $this->connection = new PDO("mysql:dbname={$db};host={$host};port={$port}", $username, $password);
        $this->connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
}