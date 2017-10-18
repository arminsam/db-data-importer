<?php

namespace DataImporter\Services;

use DataImporter\TableNodes\Table;

class ExportService extends BasicService
{
    /**
     * @param string $exportPath
     */
    public function __construct(string $exportPath = '')
    {
        parent::__construct('export', $exportPath);
    }

    /**
     * Export table schemas for all tables in the database.
     *
     * @param bool $tableSchema
     * @return $this
     */
    public function createTableSchemas(bool $tableSchema)
    {
        if (! $tableSchema) {
            return $this;
        }

        $lines = [];

        echo "[x] EXPORTING TABLE SCHEMAS" . PHP_EOL;
        foreach ($this->tables as $table) {
            echo "Exporting table schema for {$table}" . PHP_EOL;
            $query = "SHOW CREATE TABLE {$table}";
            $createTableStatement = $this->executeQuery($query)[0]['Create Table'];
            $createTableStatement = str_replace('CREATE TABLE', 'CREATE TABLE IF NOT EXISTS', $createTableStatement);
            $lines[] = $createTableStatement . ';' . PHP_EOL;
        }

        $this->writeToFile($lines);
        echo "[✓] EXPORTING TABLE SCHEMAS FINISHED" . PHP_EOL. PHP_EOL;

        return $this;
    }

    /**
     * Export data for tables specified in the configuration.
     *
     * @param bool $fixedTables
     * @param string $topic
     * @param array $ids
     * @return $this
     */
    public function exportData(bool $fixedTables, string $topic, array $ids)
    {
        $this->exportFixedTablesData($fixedTables);
        $this->exportOperationalTablesData($topic, $ids);

        return $this;
    }

    /**
     * Add header statements important for the import to go through successfully.
     *
     * @return $this
     */
    public function addHeaderContent()
    {
        $lines = [];
        $lines[] = "/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;";
        $lines[] = "/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;";
        $lines[] = "/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;";
        $lines[] = "/*!40101 SET NAMES utf8 */;";
        $lines[] = "/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;";
        $lines[] = "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;";
        $lines[] = "/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;";
        $lines[] = PHP_EOL . PHP_EOL;
        $this->writeToFile($lines);

        return $this;
    }

    /**
     * Add footer statements important for the import to go through successfully.
     *
     * @return $this
     */
    public function addFooterContent()
    {
        $lines = [];
        $lines[] = PHP_EOL . PHP_EOL;
        $lines[] = "/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;";
        $lines[] = "/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;";
        $lines[] = "/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;";
        $lines[] = "/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;";
        $lines[] = "/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;";
        $lines[] = "/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;";
        $this->writeToFile($lines);

        return $this;
    }

    /**
     * Export data for the fixed tables.
     *
     * @param bool $fixedTables
     */
    protected function exportFixedTablesData(bool $fixedTables)
    {
        if (! $fixedTables) {
            return;
        }

        $fixedTables = $this->config['fixed_tables'];

        echo "[x] EXPORTING FIXED TABLES DATA" . PHP_EOL;
        foreach ($fixedTables as $tableName => $config) {
            $this->export($this->tablesTreeMap[$tableName], 'fixed');
        }
        echo "[✓] EXPORTING FIXED TABLES DATA FINISHED" . PHP_EOL . PHP_EOL;
    }

    /**
     * Export data for the operational tables and for the given branch.
     *
     * @param string $topic
     * @param array $ids
     */
    protected function exportOperationalTablesData(string $topic, array $ids)
    {
        if (empty(trim($topic)) || empty($ids)) {
            return;
        }

        echo "[x] EXPORTING OPERATIONAL TABLES DATA" . PHP_EOL;
        $this->tablesTreeMap[$topic]->ids = $ids;
        $this->export($this->tablesTreeMap[$topic], 'operational');
        echo '[✓] EXPORTING OPERATIONAL TABLES DATA FINISHED' . PHP_EOL . PHP_EOL;
    }

    /**
     * @param Table $table
     * @param string $type
     */
    protected function export(Table $table, string $type)
    {
        if ($type == 'operational' && empty($table->ids)) {
            echo "Table {$table->name} has no exportable id" . PHP_EOL;
            return;
        }

        if ($table->isReferenced()) {
            $refTable = $this->tablesTreeMap[$table->getReferencedTableName()];
            $refTable->ids = $table->ids;
            $refTable->foreignKey = $table->foreignKey;
            $refTable->otherKey = $table->otherKey;
            $refTable->relation = $table->relation;
            $table = $refTable;
        }

        echo "Exporting table {$table->name}" . PHP_EOL;
        $query = $this->createSelectQuery($table);
        $table->data = $this->executeQuery($query, $table->ids);
        $table->setChildrenIds();
        $this->dumpTableData($table);
        $table->data = null;

        foreach ($table->children as $childTable) {
            $this->export($childTable, $type);
        }
    }

    /**
     * @param Table $table
     * @return string
     */
    protected function createSelectQuery(Table $table)
    {
        if (empty($table->ids)) {
            return "SELECT * FROM {$table->name}";
        }
        $inQuery = implode(',', array_fill(0, count($table->ids), '?'));

        return "SELECT * FROM {$table->name} WHERE {$table->getQueryColumn()} IN ({$inQuery})";
    }

    /**
     * Dump table data to export file.
     *
     * @param Table $table
     */
    protected function dumpTableData(Table $table)
    {
        if (empty($table->data)) {
            return;
        }

        $lines = [];
        $columns = array_keys($table->data[0]);
        $columnNames = implode(', ', array_map(function($column) { return "`{$column}`"; }, $columns));
        $lines[] = "LOCK TABLES `{$table->name}` WRITE;";
        $lines[] = "/*!40000 ALTER TABLE `{$table->name}` DISABLE KEYS */;" . PHP_EOL;
        $lines[] = "INSERT INTO `{$table->name}` ({$columnNames})";
        $lines[] = "VALUES";

        foreach ($table->data as $index => $row) {
            $rowData = array_map(function($cellData) {
                return is_null($cellData) ? "NULL" : $this->quoteString($cellData);
            }, $row);
            $lines[] = "\t(" . implode(', ', $rowData) . ')' . ($index + 1 == count($table->data) ? '' : ',');
        }

        $lines[] = "ON DUPLICATE KEY UPDATE {$table->pk}={$table->pk};" . PHP_EOL;
        $lines[] = "/*!40000 ALTER TABLE `{$table->name}` ENABLE KEYS */;";
        $lines[] = "UNLOCK TABLES;" . PHP_EOL;
        $this->writeToFile($lines);
    }

    /**
     * Write lines to the export file.
     *
     * @param array $lines
     */
    protected function writeToFile(array $lines)
    {
        $exportFile = fopen($this->file, 'a');

        foreach ($lines as $line) {
            fwrite($exportFile, utf8_encode($line) . PHP_EOL);
        }

        fclose($exportFile);
    }
}