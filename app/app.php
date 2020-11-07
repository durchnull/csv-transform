<?php

include 'helper.php';
include 'csv.php';
include 'job.php';

use App\CSVTransformer;
use App\Job;

class App
{
    private $jobs = [];

    public function __construct()
    {
        $jobsDirectory = new DirectoryIterator(dirname(__FILE__) . "/../jobs");

        foreach ($jobsDirectory as $fileinfo) {
            if (! $fileinfo->isDot() &&
                ! $fileinfo->isDir() &&
                $fileinfo->getFilename() !== 'example_job.php' &&
                $fileinfo->getExtension() == 'php'
            ) {
                $instructions = include $fileinfo->getPath() . '/' . $fileinfo->getFilename();
                $this->jobs[] = new Job($instructions);
            }
        }
    }

    public function run()
    {
        foreach ($this->jobs as $index => $job) {
            $files = $job->getFiles();
            info('Job: ' . $job->getTitle());

            foreach ($files as $file) {
                info('File: ' . $file);
                new CSVTransformer($file, $job->getActions());
            }

            info('');
        }
    }
}