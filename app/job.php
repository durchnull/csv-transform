<?php

namespace App;

class Job
{
    private $instructions;

    public function __construct(array $instructions)
    {
        $this->instructions = $instructions;
        $this->validate();
    }

    public function getTitle(): string
    {
        return $this->instructions['filename'];
    }

    public function getFiles(): array
    {
        $files = [];

        $filename = $this->instructions['filename'];

        if (strpos($filename, '*')) {
            $inputDirectory = new \DirectoryIterator(dirname(__FILE__) . "/../storage/input");

            foreach ($inputDirectory as $fileinfo) {
                if (! $fileinfo->isDot() &&
                    ! $fileinfo->isDir() &&
                    $fileinfo->getExtension() == 'csv'
                ) {
                    if (strpos($fileinfo->getFilename(), str_replace('*', '', $filename)) !== false) {
                        $files[] = $fileinfo->getFilename();
                    }
                }
            }
        }
        else {
            $files[] = $filename;
        }

        return $files;
    }

    public function getActions(): array
    {
        return $this->instructions['actions'];
    }

    // @todo
    private function validate()
    {
        return true;
    }
}