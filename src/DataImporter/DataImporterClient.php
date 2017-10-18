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
     * @return $this
     */
    public function export(string $topic = '', array $ids = [], bool $tableSchema = false, bool $fixedTables = false, string $filePath = '')
    {
        try {
            $exportService = new ExportService($filePath);
            $exportService
                ->addHeaderContent()
                ->createTableSchemas($tableSchema)
                ->exportData($fixedTables, $topic, $ids)
                ->addFooterContent();
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            die();
        }

        return $this;
    }

    /**
     * @param string $filePath
     * @return $this
     */
    public function import(string $filePath = '')
    {
        try {
            $importService = new ImportService($filePath);
            $importService->import();
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            die();
        }

        return $this;
    }

    /**
     * @param string $context
     * @return $this
     */
    public function cleanup(string $context = 'operational_tables')
    {
        try {
            $cleanupService = new CleanupService();
            $cleanupService->removeData($context);
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            die();
        }

        return $this;
    }
}