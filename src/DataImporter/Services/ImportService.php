<?php

namespace DataImporter\Services;

class ImportService extends BasicService
{
    /**
     * @param string $importPath
     */
    public function __construct(string $importPath = '')
    {
        parent::__construct('import', $importPath);
    }

    /**
     * Import the content of an exported sql file.
     */
    public function import()
    {
        $query = '';
        $lines = file($this->file);

        echo "[x] IMPORTING TABLE DATA" . PHP_EOL;
        foreach ($lines as $line) {
            if (substr($line, 0, 2) == '--' || trim($line) == '') {
                continue;
            }

            $query .= $line;

            if (substr(trim($line), -1, 1) == ';') {
                if (substr($query, 0, 11) == 'LOCK TABLES') {
                    $tableName = explode(' ', $query);
                    $tableName = trim($tableName[2], '`');
                    echo 'Importing table ' . $tableName . PHP_EOL;
                }
                $this->executeQuery($query, [], false);
                $query = '';
            }
        }

        echo '[✓] IMPORT FINISHED' . PHP_EOL . PHP_EOL;
    }
}