<?php

namespace DataImporter;

use DataImporter\Services\CleanupService;
use DataImporter\Services\ExportService;
use DataImporter\Services\ImportService;
use Exception;

class DataImporterClient
{
    /**
     * @param string $topic
     * @param array $ids
     * @param bool $tableSchema
     * @param bool $fixedTables
     * @param string $filePath
     * @param bool $quiet
     * @return $this
     */
    public function export(
        string $topic = '',
        array $ids = [],
        bool $tableSchema = false,
        bool $fixedTables = false,
        string $filePath = '',
        bool $quiet = true
    ) {
        try {
            $exportService = new ExportService($filePath, $quiet);
            $exportService
                ->addHeaderContent()
                ->createTableSchemas($tableSchema)
                ->exportData($fixedTables, $topic, $ids)
                ->addFooterContent();
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            exit(1);
        }

        return $this;
    }

    /**
     * @param string $filePath
     * @param bool $quiet
     * @return $this
     */
    public function import(string $filePath = '', bool $quiet = true)
    {
        try {
            $importService = new ImportService($filePath, $quiet);
            $importService->import();
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            die();
        }

        return $this;
    }

    /**
     * @param string $context
     * @param bool $quiet
     * @return $this
     */
    public function cleanup(string $context = 'operational_tables',  bool $quiet = true)
    {
        try {
            $cleanupService = new CleanupService($quiet);
            $cleanupService->removeData($context);
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            die();
        }

        return $this;
    }
}