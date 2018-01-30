<?php

// companion to migrate.php

function id_to_str($id) {
    $id_int = intval($id);
    return sprintf("%05d", $id_int);
}

function id_to_url_abstract($id, $config) {
    // super insecure, but we don't care
    if (!$config->useAbstract)
        return null;
    return str_replace('%GID', id_to_str($id), $config->abstractUrlFormat);
}

function id_to_url_expression($id, $config) {
    // super insecure, but we don't care
    return str_replace('%GID', id_to_str($id), $config['expressionUrlFormat']);
}

function add_title($resource, $type, $lit) {
    $titleNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workTitle', $titleNode);
    $titleNode->add('rdfs:label', $lit);
    $titleNode->addResource('rdf:type', 'bdo:'.$type);
}

function add_label($resource, $type, $lit) {
//    $resource
}

$name_to_bcp = [
    'tibetan' => 'bo-x-ewts',
    'sktuni' => 'sa-Deva',
    'sanskrit' => 'sa-x-iats',
    'mongolian' => 'cmg-x-poppe-simpl',
    'mnguni' => 'cmg-Mong'
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

function kernel_item_to_ttl($config, $item) {
    global $name_to_bcp;
    if (isset($item->now))
        return;
    $id = $item->rkts;
    $url_expression = id_to_url_expression($id, $config);
    // if ($config->useAbstract) {
    //     $graph_abstract = new EasyRdf_Graph();
    //     $url_abstract = id_to_url_abstract($id, $config);
    //     $abstract_r = $graph_abstract->resource($url_abstract);
    // }
    $graph_expression = new EasyRdf_Graph();
    $expression_r = $graph_expression->resource($url_expression);
    $expression_r->addResource('rdf:type', 'bdo:Work');
    $labelAdded = false;
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts") continue;
        if (empty($child->__toString())) continue;
        $langtag = $name_to_bcp[$name];
        $lit = normalize_lit($child->__toString(), $langtag);
        // TODO: add skos:label here for BDRC dataset
        add_title($expression_r, 'WorkBibliographicTitle', $lit);
    }
    add_log_entry($expression_r);
    rdf_to_ttl($config, $graph_expression, $expression_r->localName());
}

$turtle = EasyRdf_Format::getFormat('turtle');

function rdf_to_ttl($config, $graph, $basename) {
    global $turtle;
    $output = $graph->serialise($turtle);
    $filename = $config['opts']->getOption('output-dir').'/'.$basename.'.ttl';
    file_put_contents($filename, $output);
}

function kernel_to_ttl($config, $xml) {
    foreach($xml->item as $item) {
        kernel_item_to_ttl($config, $item);
    }
}

// TODO:
// - detect double titles (rkts 456)
// - handle (?) and (distorded) and other parenthesis
