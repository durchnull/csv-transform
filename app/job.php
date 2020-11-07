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
        return $this->getFilename();
    }

    public function getFilename(): string
    {
        return $this->instructions['filename'];
    }

    public function getActions(): array
    {
        return $this->instructions['actions'];
    }

    public function complete(): void
    {
        info("Job complete \n");
    }

    // @todo
    private function validate()
    {
        return true;
    }
}