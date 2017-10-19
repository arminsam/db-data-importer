<?php

namespace DataImporter\Services;

use Exception;

class ImportService extends BasicService
{
    /**
     * @param string $importPath
     * @param bool $quiet
     */
    public function __construct(string $importPath = '', bool $quiet = true)
    {
        parent::__construct('import', $importPath);
        $this->quiet = $quiet;
    }

    /**
     * Import the content of an exported sql file.
     */
    public function import()
    {
        if (! file_exists($this->file)) {
            throw new Exception("Import file {$this->file} cannot be found.");
        }

        $query = '';
        $lines = file($this->file);

        echo $this->quiet ? '' : '[x] IMPORTING TABLE DATA' . PHP_EOL;
        foreach ($lines as $line) {
            if (substr($line, 0, 2) == '--' || trim($line) == '') {
                continue;
            }

            $query .= $line;

            if (substr(trim($line), -1, 1) == ';') {
                if (substr($query, 0, 11) == 'LOCK TABLES') {
                    $tableName = explode(' ', $query);
                    $tableName = trim($tableName[2], '`');
                    echo $this->quiet ? '' : 'Importing table ' . $tableName . PHP_EOL;
                }
                $this->executeQuery($query, [], false);
                $query = '';
            }
        }

        echo $this->quiet ? '' : '[✓] IMPORT FINISHED' . PHP_EOL . PHP_EOL;
    }
}