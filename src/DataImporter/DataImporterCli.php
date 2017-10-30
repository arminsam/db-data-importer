<?php

namespace DataImporter;

use Exception;

class DataImporterCli
{
    /**
     * @var array
     */
    private $supportedActions = [
        'cleanup' => [
            'context' => ['operational_tables', 'fixed_tables', 'all']
        ],
        'import' => [
            'path' => []
        ],
        'export' => [
            'topic' => [],
            'ids' => [],
            'table-schema' => ['true', 'false'],
            'fixed-tables' => ['true', 'false'],
            'path' => []
        ]
    ];

    /**
     * @var bool
     */
    private $quiet = false;

    /**
     * Evaluate and execute the given command.
     */
    public function run()
    {
        try {
            $command = $this->getAction($_SERVER['argv']);
            $options = $this->getOptions($command, $_SERVER['argv']);
            $this->execute($command, $options);
        } catch (Exception $e) {
            echo 'Error! ' . $e->getMessage() . PHP_EOL;
            exit(1);
        }
    }

    /**
     * @param array $argv
     * @return string
     * @throws Exception
     */
    private function getAction(array $argv)
    {
        if (! isset($argv[1]) || ! in_array($argv[1], array_keys($this->supportedActions))) {
            throw new Exception('Unknown command! Supported commands: '
                . implode(', ', array_keys($this->supportedActions)));
        }

        return $argv[1];
    }

    /**
     * @param string $command
     * @param array $argv
     * @return array
     */
    private function getOptions(string $command, array $argv)
    {
        $options = [];
        $tmpOptions[] = isset($argv[2]) ? $this->getOption($argv[2]) : null;
        $tmpOptions[] = isset($argv[3]) ? $this->getOption($argv[3]) : null;
        $tmpOptions[] = isset($argv[4]) ? $this->getOption($argv[4]) : null;
        $tmpOptions[] = isset($argv[5]) ? $this->getOption($argv[5]) : null;
        $tmpOptions[] = isset($argv[6]) ? $this->getOption($argv[6]) : null;

        foreach ($tmpOptions as $option) {
            if (is_null($option)) {
                continue;
            }
            $options[$option['name']] = $option['value'];
        }

        $this->validateOptions($command, $options);

        return $options;
    }

    /**
     * @param string $arg
     * @return array
     * @throws Exception
     */
    private function getOption(string $arg)
    {
        $option = explode('=', ltrim($arg, '--'));

        if (count($option) < 2) {
            throw new Exception("Invalid option -- {$arg}.");
        }

        return ['name' => $option[0], 'value' => $option[1]];
    }

    /**
     * @param string $command
     * @param array $options
     */
    private function validateOptions(string $command, array $options)
    {
        $maxOptionCount = count($this->supportedActions[$command]);
        $this->validateMaxOptionCount($command, $options, $maxOptionCount);
        foreach ($options as $optionName => $optionValue) {
            $this->validateOptionName($command, $optionName);
            $this->validateOptionValue($command, $optionName, $optionValue);
        }
    }

    /**
     * @param string $command
     * @param array $options
     * @param int $maxCount
     * @throws Exception
     */
    private function validateMaxOptionCount(string $command, array $options, int $maxCount)
    {
        if (count($options) > $maxCount) {
            $message = 'Invalid number of options given. ';
            if (count($this->supportedActions[$command]) > 0) {
                $message .= 'Supported options: ' . implode(', ', array_keys($this->supportedActions[$command]));
            }
            throw new Exception($message);
        }
    }

    /**
     * @param string $command
     * @param string $optionName
     * @throws Exception
     */
    private function validateOptionName(string $command, string $optionName)
    {
        if (! in_array($optionName, array_keys($this->supportedActions[$command]))) {
            throw new Exception("Invalid option -- {$optionName}.");
        }
    }

    /**
     * @param string $command
     * @param string $optionName
     * @param string $optionValue
     * @throws Exception
     */
    private function validateOptionValue(string $command, string $optionName, string $optionValue)
    {
        if (empty($this->supportedActions[$command][$optionName])) {
            return;
        }
        if (! in_array($optionValue, $this->supportedActions[$command][$optionName])) {
            $message = 'Invalid value given for --' . $optionName . '. Supported values: '
                . implode(', ', $this->supportedActions[$command][$optionName]);
            throw new Exception($message);
        }
    }

    /**
     * @param string $command
     * @param array $options
     */
    private function execute(string $command, array $options)
    {
        switch ($command) {
            case 'cleanup' : $this->executeCleanupCommand($options); break;
            case 'import' : $this->executeImportCommand($options); break;
            case 'export' : $this->executeExportCommand($options); break;
        }
    }

    /**
     * @param array $options
     */
    private function executeCleanupCommand(array $options)
    {
        $client = new DataImporterClient();
        $context = '';
        if (isset($options['context'])) {
            if (strtolower($options['context']) != 'all') {
                $context = $options['context'];
            }
        } else {
            $context = 'operational_tables';
        }

        $client->cleanup($context, $this->quiet);
    }

    /**
     * @param array $options
     */
    private function executeImportCommand(array $options)
    {
        $client = new DataImporterClient();
        $filePath = isset($options['path']) ? $options['path'] : '';
        $client->import($filePath, $this->quiet);
    }

    /**
     * @param array $options
     */
    private function executeExportCommand(array $options)
    {
        $client = new DataImporterClient();
        $topic = isset($options['topic']) ? trim($options['topic']) : '';
        $ids = isset($options['ids']) ? explode(',', str_replace(' ', '',$options['ids'])) : [];
        $tableSchema = isset($options['table-schema']) ? $options['table-schema'] : false;
        $fixedTables = isset($options['fixed-tables']) ? $options['fixed-tables'] : false;
        $filePath = isset($options['path']) ? $options['path'] : '';
        $client->export($topic, $ids, $tableSchema, $fixedTables, $filePath, $this->quiet);
    }
}