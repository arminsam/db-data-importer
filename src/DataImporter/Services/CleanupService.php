<?php

namespace DataImporter\Services;

class CleanupService extends BasicService
{
    /**
     * CleanupService constructor.
     * @param bool $quiet
     */
    public function __construct(bool $quiet = true)
    {
        parent::__construct('import');
        $this->quiet = $quiet;
    }

    /**
     * Truncate all the tables belonging to the given table group.
     *
     * @param string $tableGroup
     */
    public function removeData($tableGroup = 'operational_tables')
    {
        $tableGroupText = (empty($tableGroup) ? 'ALL' : $tableGroup);
        echo $this->quiet ? '' : "[x] CLEANING UP TABLES FOR TABLE GROUP \"{$tableGroupText}\"" . PHP_EOL;
        $this->listAllTables($tableGroup);
        $existingTables = $this->executeQuery("SHOW TABLES;");
        $existingTables = array_column($existingTables, 'Tables_in_'.$this->config['import_database']['database']);

        $this->executeQuery("/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;", [], false);

        foreach ($this->tables as $table) {
            if (in_array($table, $existingTables)) {
                echo $this->quiet ? '' : "Cleaning up table {$table}" . PHP_EOL;
                $this->executeQuery("TRUNCATE TABLE {$table};", [], false);
            }
        }

        $this->executeQuery("/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;", [], false);

        echo $this->quiet ? '' : '[✓] CLEANUP FINISHED' . PHP_EOL . PHP_EOL;
    }
}