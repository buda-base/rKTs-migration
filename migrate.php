#!/usr/bin/env php
<?php

use GetOpt\GetOpt;
use GetOpt\Option;
use GetOpt\Command;
use GetOpt\ArgumentException;
use GetOpt\ArgumentException\Missing;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/vendor/autoload.php';

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
        ->setDescription('input directory with XML files (defaults to input/)'),

    Option::create('o', 'output-dir', GetOpt::REQUIRED_ARGUMENT)
        ->setDefaultValue('output/')
        ->setDescription('output directory for RDF files (defaults to output/)'),

    Option::create('c', 'config', GetOpt::REQUIRED_ARGUMENT)
        ->setDefaultValue('rkts.yaml')
        ->setDescription('Yaml config file (defaults to rkts.yaml)'),

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

$config = Yaml::parseFile($getOpt->getOption('config'));

$config['opts'] = $getOpt;

EasyRdf_Namespace::set('adm', 'http://purl.bdrc.io/ontology/admin/');
EasyRdf_Namespace::set('bdd', 'http://purl.bdrc.io/data/');
EasyRdf_Namespace::set('bdo', 'http://purl.bdrc.io/ontology/core/');
EasyRdf_Namespace::set('bdr', 'http://purl.bdrc.io/resource/');
EasyRdf_Namespace::set('skos', 'http://www.w3.org/2004/02/skos/core#');
EasyRdf_Namespace::set('tbr', 'http://purl.bdrc.io/ontology/toberemoved/');
EasyRdf_Namespace::set('rkts', 'http://purl.rkts.eu/resource/');

$kernel_xml = simplexml_load_file($getOpt->getOption('input-dir').'/'.'rkts.xml');

mkdir($getOpt->getOption('output-dir'), 0777, true);
mkdir($getOpt->getOption('output-dir').'rKTs');
mkdir($getOpt->getOption('output-dir').'bdrc');

require_once "kernelxmltottl.php";

mb_internal_encoding('UTF-8');

$global_filename = $config['opts']->getOption('output-dir').'/global.nt';
$global_graph_fd = fopen($global_filename, "w");

kernel_to_ttl($config, $kernel_xml, $global_graph_fd);
kernel_to_ttl($config, $kernel_xml, $global_graph_fd, true);

require_once "editionxmltottl.php";

$filesList = ["stog", /*"derge"*/];

foreach ($filesList as $fileName) {
    $edition_xml = simplexml_load_file($getOpt->getOption('input-dir').'/'.$fileName.'.xml');
    editions_to_ttl($config, $edition_xml, $global_graph_fd, $fileName);
    editions_to_ttl($config, $edition_xml, $global_graph_fd, $fileName, true);
}

fclose($global_graph_fd);
