<?php

// companion to migrate.php

function id_to_str($id) {
    $id_int = intval($id);
    return sprintf("%05d", $id_int);
}

function chapnum_to_str($id) {
    $id_int = intval($id);
    return sprintf("%03d", $id_int);
}

function id_to_url_abstract($rktsid, $config) {
    return str_replace('%GID', id_to_str($id), $config['abstractUrlFormat']);
}

function id_to_url_expression($rktsid, $config) {
    return str_replace('%GID', id_to_str($rktsid), $config['expressionUrlFormat']);
}

function id_to_url_edition($eid, $config) {
    return str_replace('%EID', id_to_str($eid), $config['editionUrlFormat']);
}

function id_to_url_edition_text($eid, $rktsid, $config) {
    $estr = id_to_url_edition($eid, $config);
    return str_replace('%GID', id_to_str($rktsid), $estr);
}

function id_to_url_edition_text_chapter($eid, $rktsid, $chapnum, $config) {
    $txtstr = id_to_url_edition_text($eid, $rktsid, $chapnum);
    return str_replace('%CID', chapnum_to_str($chapnum), $txtstr);
}

function add_title($resource, $type, $lit) {
    $titleNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workTitle', $titleNode);
    $titleNode->add('rdfs:label', $lit);
    $titleNode->addResource('rdf:type', 'bdo:'.$type);
}

function report_error($file, $type, $id, $message) {
    error_log($file.':'.$id.':'.$type.': '.$message);
}

function add_label($resource, $type, $lit) {
//    $resource
}

$name_to_bcp = [
    'tibetan' => 'bo-x-ewts',
    'sktuni' => 'sa-Deva',
    'sanskrit' => 'sa-x-iats',
    'mongolian' => 'cmg-x-poppe-simpl',
    'mnguni' => 'cmg-Mong',
    'skttrans' => 'sa-x-ewts'
];

function normalize_lit($title, $langtag) {
    // todo: normalize mongolian romanization here
    return EasyRdf_Literal::create($title, $langtag);
}

function add_log_entry($resource) {
    $logNode = $resource->getGraph()->newBNode();
    $resource->addResource('adm:logEntry', $logNode);
    $logNode->addLiteral('adm:logDate', new DateTime());
    $logNode->addLiteral('adm:logMessage', 'migrated from xml', 'en');
}

$ntriples = EasyRdf_Format::getFormat('ntriples');

function add_graph_to_global($graph, $localname, $global_fd) {
    global $ntriples;
    $output = $graph->serialise($ntriples);
    // this is really crappy but it seems there is no other option
    // in easyrdf...
    $output = str_replace('_:genid', '_:genid'.$localname, $output);
    fwrite($global_fd, $output);
}

$turtle = EasyRdf_Format::getFormat('turtle');

function rdf_to_ttl($config, $graph, $basename) {
    global $turtle;
    $output = $graph->serialise($turtle);
    $filename = $config['opts']->getOption('output-dir').'/'.$basename.'.ttl';
    file_put_contents($filename, $output);
}
