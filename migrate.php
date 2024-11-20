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
error_reporting(E_ALL ^ E_NOTICE); 

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

// print_r($config['KTMapping']);
// return;

$config['opts'] = $getOpt;

EasyRdf_Namespace::set('adm', 'http://purl.bdrc.io/ontology/admin/');
EasyRdf_Namespace::set('bdg', 'http://purl.bdrc.io/graph/');
EasyRdf_Namespace::set('bdo', 'http://purl.bdrc.io/ontology/core/');
EasyRdf_Namespace::set('bf', 'http://id.loc.gov/ontologies/bibframe/');
EasyRdf_Namespace::set('bdr', 'http://purl.bdrc.io/resource/');
EasyRdf_Namespace::set('skos', 'http://www.w3.org/2004/02/skos/core#');
EasyRdf_Namespace::set('bda', 'http://purl.bdrc.io/admindata/');
EasyRdf_Namespace::set('rkts', 'http://purl.rkts.eu/resource/');

mkdir($getOpt->getOption('output-dir'), 0777, true);
mkdir($getOpt->getOption('output-dir').'rKTs');
mkdir($getOpt->getOption('output-dir').'bdrc');

require_once "kernelxmltottl.php";

mb_internal_encoding('UTF-8');

$global_filename = $config['opts']->getOption('output-dir').'/global.nt';
$global_graph_fd = fopen($global_filename, "w");

$gl_rkts_kmapping = [];

require_once "utils.php";

$gl_rkts_props = get_rkts_props();
$gl_rkts_abstract = get_abstract_mapping($config);

require_once "editionxmltottl.php";

$gl_abstractUrl_catId = [];

$kernel_xml = simplexml_load_file('rKTs/Kernel/rkts.xml');
fillmappings($kernel_xml);

$filesList = [ "derge" , "chemdo" , "goldenmustang" , "egoo",  "shey", "stog", "narthang", "peking", "lhasa", "urga", "cone", "lithang",  "phugdrak", "ragya", "hemishi", "tashiyangtse", "go", "ng", "bd", "l" ];


foreach ($filesList as $fileName) {
    $edition_xml = simplexml_load_file("rKTs/".$config[$fileName]["file"]);
    editions_to_ttl($config, $edition_xml, $global_graph_fd, $fileName, true);
}

kernel_to_ttl($config, $kernel_xml, $global_graph_fd);
kernel_to_ttl($config, $kernel_xml, $global_graph_fd, true);
unset($kernel_xml);

//$gl_abstractUrl_catId = []; // do not uncomment, would break texts that are common to Tengyur and Kangyur

$filesList = [ "chemdot", "tanjurg", "tanjurn", "tanjurd", "tanjurq" ];

foreach ($filesList as $fileName) {
    $edition_xml = simplexml_load_file("rKTs/".$config[$fileName]["file"]);
    editions_to_ttl($config, $edition_xml, $global_graph_fd, $fileName, true, "T");
}

$kernelt_xml = simplexml_load_file('rKTs/Kernel/rktst.xml');
kernel_to_ttl($config, $kernelt_xml, $global_graph_fd, false, "T");
kernel_to_ttl($config, $kernelt_xml, $global_graph_fd, true, "T");
unset($kernelt_xml);

$filesList = [ "ggk" , "gaz", "gbm", "gkh", "gpb", "gcu"];

foreach ($filesList as $fileName) {
    $edition_xml = simplexml_load_file("rKTs/".$config[$fileName]["file"]);
    editions_to_ttl($config, $edition_xml, $global_graph_fd, $fileName, true, "G");
}

$kernelt_xml = simplexml_load_file('rKTs/sql_export/rktsg.xml');
kernel_to_ttl($config, $kernelt_xml, $global_graph_fd, false, "G");
kernel_to_ttl($config, $kernelt_xml, $global_graph_fd, true, "G");
unset($kernelt_xml);

$filesList = [ "bks", "bkt"];

foreach ($filesList as $fileName) {
    $edition_xml = simplexml_load_file("rKTs/".$config[$fileName]["file"]);
    editions_to_ttl($config, $edition_xml, $global_graph_fd, $fileName, true, "B");
}

$kernelt_xml = simplexml_load_file('rKTs/sql_export/rktsb.xml');
kernel_to_ttl($config, $kernelt_xml, $global_graph_fd, false, "B");
kernel_to_ttl($config, $kernelt_xml, $global_graph_fd, true, "B");
unset($kernelt_xml);

fclose($global_graph_fd);
