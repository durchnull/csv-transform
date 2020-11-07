<?php

namespace App;

use App\Job;

class CSVTransformer
{
    private $delimiter = ';';
    private $filename;
    private $data = [];
    private $dataHeader = [];
    private $actions = [];
    private $emptyCellValue = '-';
    private $inputDirectory = '../storage/input';
    private $outputDirectory = '../storage/output';
    private $categoryIndex = null;
    private $amountIndex = null;
    private $hashIndices = [];

    // @todo Create Action classes
    private $sortedAndAllowedActions = [
        'sliceBeginningRows',
        'renameHeaders',
        'removeRowIf',
        'removeColumnByHeaders',
        'hashByHeaders',
        'addColumns'
    ];

    private $possibleAmountHeader = [
        'Betrag',
        'Eur'
    ];

    private $possibleCategoryHeader = [
        'Kategorie',
        'Category'
    ];

    public function __construct(string $filename, array $actions = [])
    {
        // @todo Determine delimiter based on file
        if (strpos($filename, 'harvest') !== false) {
            $this->delimiter = ',';
        }

        $this->setFilename($filename);
        $this->setActions($actions);
        $this->readFile();
        $this->executeActions();
        $this->setAmountIndex();
        $this->storeFiles();
    }

    public function setFilename(string $filename)
    {
        $this->filename = $filename;
    }

    public function setAmountIndex()
    {
        foreach ($this->possibleAmountHeader as $possibleAmountHeader) {
            foreach ($this->dataHeader as $headerIndex => $header) {
                if (strpos(strtolower($header), strtolower($possibleAmountHeader))) {
                    $this->amountIndex = $headerIndex;
                }
            }
        }
    }

    private function setActions(array $actions)
    {
        foreach ($this->sortedAndAllowedActions as $action) {
            if (isset($actions[$action])) {
                $this->actions[$action] = $actions[$action];
            }
            else if ($action == 'hashByHeaders') {
                $this->actions[$action] = 'all';
            }
        }
    }

    public function executeActions()
    {
        if (! isset($this->actions['sliceBeginningRows'])) {
            $this->setDataHeader();
        }

        foreach ($this->actions as $action => $value) {
            if (method_exists($this, $action)) {
                $this->{$action}($value);

                if ($action == 'sliceBeginningRows') {
                    $this->setDataHeader();
                }
            }
        }
    }

    private function readFile()
    {
        $filename = __DIR__ . "/{$this->inputDirectory}/{$this->filename}";

        if (! file_exists($filename)) {
            throw new \Exception("File {$filename} not found.");
        }

        if (($file = fopen($filename, "r")) !== FALSE) {
            while (($row = fgetcsv($file, 1000, $this->delimiter)) !== FALSE) {
                $this->data[] = $row;
            }
            fclose($file);
        }
    }

    private function setDataHeader()
    {
        $this->dataHeader = reset($this->data);
    }

    private function sliceBeginningRows(int $offset = 0)
    {
        $this->data = array_slice($this->data, $offset);
    }

    private function renameHeaders(array $headers)
    {
        foreach ($headers as $index => $name) {
            $this->data[0][$index] = $name;
        }

        $this->setDataHeader();
    }

    private function removeColumnByHeaders(array $headers)
    {
        foreach ($headers as $header) {
            $headerIndex = is_int($header)
                ? $header
                : array_search($header, $this->dataHeader);

            if ($headerIndex !== false) {
                foreach ($this->data as $rowIndex => $row) {
                    unset($this->data[$rowIndex][$headerIndex]);
                }
            }
        }

        $this->setDataHeader();
    }

    private function removeRowIf(array $conditions)
    {
        foreach ($conditions as $header => $values) {
            $headerIndex = array_search($header, $this->dataHeader);

            if ($headerIndex !== false) {
                if (! is_array($values)) {
                    $values = [$values];
                }

                foreach ($this->data as $rowIndex => $row) {
                    if (in_array($row[$headerIndex], $values)) {
                        unset($this->data[$rowIndex]);
                    }
                }
            }
        }
    }

    private function addColumns(array $columns)
    {
        foreach ($columns as $header => $column) {
            foreach ($this->data as $rowIndex => $row) {
                if ($rowIndex == 0) {
                    $this->data[0][] = $header;
                    $this->setDataHeader();

                    if (in_array($header, $this->possibleCategoryHeader)) {
                        $this->categoryIndex = array_key_last($this->data[0]);
                    }
                }
                else {
                    $rowValue = null;

                    foreach ($column as $appendRowValue => $conditions) {
                        if ($this->validateConditions($conditions, $row)) {
                            $rowValue = is_null($rowValue)
                                ? $appendRowValue
                                : $rowValue . ', ' . $appendRowValue;
                        }
                    }

                    if (is_null($rowValue)) {
                        $rowValue = $this->emptyCellValue;
                    }

                    $this->data[$rowIndex][] = $rowValue;
                }
            }
        }
    }

    private function validateConditions(array $conditions, array $row)
    {
        foreach ($conditions as $condition => $values) {
            foreach ($values as $header => $conditionalValues) {
                $headerIndex = array_search($header, $this->dataHeader);

                if ($headerIndex) {
                    $cellValue = $row[$headerIndex];

                    switch ($condition) {
                        case 'contains':
                            foreach ($conditionalValues as $conditionalValue) {
                                if (strpos($cellValue, $conditionalValue) !== false) {
                                    return true;
                                }
                            }
                            break;
                        case 'equals':
                            foreach ($conditionalValues as $conditionalValue) {
                                if ($cellValue == $conditionalValue) {
                                    return true;
                                }
                            }
                            break;
                    }
                }
            }
        }

        return false;
    }

    public function hashByHeaders()
    {
        $this->hashIndices = $this->actions['hashByHeaders'] === 'all'
            ? array_keys($this->dataHeader)
            : $this->actions['hashByHeaders'];

        foreach ($this->data as $rowIndex => $row) {
            $hashArray = array_map(function ($index) use ($row) {
                return trim($row[$index]);
            }, $this->hashIndices);

            $hashString = implode('', $hashArray);
            $hashString = str_replace(' ', '', $hashString);

            $this->data[$rowIndex][] = $rowIndex == 0
                ? 'Hash'
                : hash('sha1', $hashString);
        }

        $this->setDataHeader();
    }

    public function getRevenue(): array
    {
        $result = array_filter(array_slice($this->data, 1), function($row) {
            return is_numeric((double)$row[$this->amountIndex]) && ((double) $row[$this->amountIndex] > 0);
        });

        array_unshift($result, $this->dataHeader);

        return $result;
    }

    public function getExpenditure(): array
    {
        $result = array_filter(array_slice($this->data, 1), function($row) {
            return is_numeric((double)$row[$this->amountIndex]) && ((double) $row[$this->amountIndex] < 0);
        });

        array_unshift($result, $this->dataHeader);

        return $result;
    }

    public function getUncategorized(): array
    {
        $result = array_filter(array_slice($this->data, 1), function($row) {
            return $row[$this->categoryIndex] == $this->emptyCellValue;
        });

        array_unshift($result, $this->dataHeader);

        return $result;
    }

    public function storeFiles()
    {
        $this->save($this->data, $this->filename, $this->delimiter);

        // @todo Define bevaiour through job
        if ($this->amountIndex) {
            $this->save($this->getRevenue(), str_replace('.csv', '-einnahmen.csv', $this->filename), $this->delimiter);
            $this->save($this->getExpenditure(), str_replace('.csv', '-ausgaben.csv', $this->filename), $this->delimiter);
        }

        if ($this->categoryIndex) {
            $this->save($this->getUncategorized(), str_replace('.csv', '-unkategorisiert.csv', $this->filename), $this->delimiter);
        }
    }

    private function save(array $data, string $filename, $delimiter)
    {
        if (count($data) <= 1) {
            return;
        }

        $filename = __DIR__ . "/{$this->outputDirectory}/{$filename}";

        if (($handle = fopen($filename, 'w')) !== FALSE) {
            foreach ($data as $row) {
                fputcsv($handle, $row, $this->delimiter);
            }
            fclose($handle);
        }
    }
}
