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
                $file = $fileinfo->getPath() . '/' . $fileinfo->getFilename();
                $job = include $fileinfo->getPath() . '/' . $fileinfo->getFilename();

                // @todo set job filename by fileinfo path if filename is not set

                $this->jobs[] =  new Job($job);
            }
        }
    }

    public function run()
    {
        foreach ($this->jobs as $index => $job) {
            info('Job ' . $job->getTitle());
            new CSVTransformer($job);
        }
    }
}