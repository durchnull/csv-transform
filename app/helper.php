<?php

function info($value) {
    if (is_string($value)) {
        echo $value . "\n";
    }
    else if (is_object($value)) {
        print_r($value);
    }
}

function dd($value) {
    print_r($value);
    die();
}

function action(string $type, string $name) {
    return include dirname(__FILE__) . '/../jobs/actions/' . $type . '/' . $name . '.php';
}