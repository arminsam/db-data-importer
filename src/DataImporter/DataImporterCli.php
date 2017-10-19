<?php

namespace DataImporter;

class DataImporterCli
{
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

    private $quiet = false;

    public function run()
    {
        $command = $this->getAction($_SERVER['argv']);
        $options = $this->getOptions($command, $_SERVER['argv']);
        $this->execute($command, $options);
    }

    private function getAction($argv)
    {
        if (! isset($argv[1]) || ! in_array($argv[1], array_keys($this->supportedActions))) {
            echo 'Unknown command! Supported commands: ' . implode(', ', array_keys($this->supportedActions)) . PHP_EOL;
            exit(1);
        }

        return $argv[1];
    }

    private function getOptions($command, $argv)
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

    private function getOption($str)
    {
        $option = explode('=', ltrim($str, '--'));

        if (count($option) < 2) {
            echo 'Invalid option > ' . $str . PHP_EOL;
            exit(1);
        }

        return ['name' => $option[0], 'value' => $option[1]];
    }

    private function validateOptions($command, $options)
    {
        $maxOptionCount = count($this->supportedActions[$command]);
        $this->validateMaxOptionCount($command, $options, $maxOptionCount);
        foreach ($options as $optionName => $optionValue) {
            $this->validateOptionName($command, $optionName);
            $this->validateOptionValue($command, $optionName, $optionValue);
        }
    }

    private function validateMaxOptionCount($command, $options, $maxCount)
    {
        if (count($options) > $maxCount) {
            echo 'Invalid number of options given. ';
            if (count($this->supportedActions[$command]) > 0) {
                echo 'Supported options: ' . implode(', ', array_keys($this->supportedActions[$command]));
            }
            echo PHP_EOL;
            exit(1);
        }
    }

    private function validateOptionName($command, $optionName)
    {
        if (! in_array($optionName, array_keys($this->supportedActions[$command]))) {
            echo 'Invalid option --' . $optionName . PHP_EOL;
            exit(1);
        }
    }

    private function validateOptionValue($command, $optionName, $optionValue)
    {
        if (empty($this->supportedActions[$command][$optionName])) {
            return;
        }
        if (! in_array($optionValue, $this->supportedActions[$command][$optionName])) {
            echo 'Invalid value given for --' . $optionName . '. Supported values: '
                . implode(', ', $this->supportedActions[$command][$optionName]) . PHP_EOL;
            exit(1);
        }
    }

    private function execute($command, $options)
    {
        switch ($command) {
            case 'cleanup' : $this->executeCleanupCommand($options); break;
            case 'import' : $this->executeImportCommand($options); break;
            case 'export' : $this->executeExportCommand($options); break;
        }
    }

    private function executeCleanupCommand($options)
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

    private function executeImportCommand($options)
    {
        $client = new DataImporterClient();
        $filePath = isset($options['path']) ? $options['path'] : '';
        $client->import($filePath, $this->quiet);
    }

    private function executeExportCommand($options)
    {
        $client = new DataImporterClient();
        $topic = isset($options['topic']) ? $options['topic'] : '';
        $ids = isset($options['ids']) ? explode(',', str_replace(' ', '',$options['ids'])) : [];
        $tableSchema = isset($options['table-schema']) ? $options['table-schema'] : false;
        $fixedTables = isset($options['fixed-tables']) ? $options['fixed-tables'] : false;
        $filePath = isset($options['path']) ? $options['path'] : '';
        $client->export($topic, $ids, $tableSchema, $fixedTables, $filePath, $this->quiet);
    }
}