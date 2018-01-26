#!/usr/bin/env php
<?php

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\Command;
use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use Symfony\Component\Yaml\Yaml;

require __DIR__ . '/vendor/autoload.php';

define('VERSION', '0.1.0');

$getOpt = new GetOpt();

// define common options
$getOpt->addOptions([
   
    Option::create(null, 'version', GetOpt::NO_ARGUMENT)
        ->setDescription('Show version information and quit'),
        
    Option::create('?', 'help', GetOpt::NO_ARGUMENT)
        ->setDescription('Show this help and quit'),

    Option::create('i', 'input-dir', GetOpt::REQUIRED_ARGUMENT)
        ->setDefaultValue('input/')
        ->setDescription('input directory with XML files'),

    Option::create('o', 'output-dir', GetOpt::REQUIRED_ARGUMENT)
        ->setDefaultValue('output/')
        ->setDescription('output directory for RDF files'),

    Option::create('c', 'config', GetOpt::REQUIRED_ARGUMENT)
        ->setDefaultValue('rkts.yaml')
        ->setDescription('Yaml config file (for rKTs or bdrc)'),

]);

// process arguments and catch user errors
try {
    try {
        $getOpt->process();
    } catch (Missing $exception) {
        // catch missing exceptions if help is requested
        if (!$getOpt->getOption('help')) {
            throw $exception;
        }
    }
} catch (ArgumentException $exception) {
    file_put_contents('php://stderr', $exception->getMessage() . PHP_EOL);
    echo PHP_EOL . $getOpt->getHelpText();
    exit;
}

// show help and quit
if ($getOpt->getOption('help')) {
    echo $getOpt->getHelpText();
    exit;
}

$conf = Yaml::parseFile($getOpt->getOption('config'));

function getAbstractID($conf, $rKTsID) {
    // super insecure, but we don't care
    if (!$conf->useAbstract)
        return null;
    return string_replace('%GID', $rKTsID, $conf->abstractUrlFormat);
}

function getExpressionID($conf, $rKTsID) {
    // super insecure, but we don't care
    return string_replace('%GID', $rKTsID, $conf->expressionUrlFormat);
}

$xml = simplexml_load_file($getOpt->getOption('input-dir').'/'.'rkts.xml');

mkdir($getOpt->getOption('output-dir'), 0777, true);
