<?php

require_once "utils.php";

function kernel_item_to_ttl($config, $item, $global_graph_fd) {
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
    $seenTitles = [];
    $seenLangs = [];
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts") continue;
        if (empty($child->__toString())) continue;
        $langtag = $name_to_bcp[$name];
        if ($config['oneTitleInHigherLevels'] && isset($seenLangs[$langtag]))
            continue;
        $seenLangs[$langtag] = true;
        $title = $child->__toString();
        if (isset($seenTitles[$title])) {
            report_error('kernel', 'duplicate', $id, 'title "'.$title.'" appears more than once');
            continue;
        }
        $seenTitles[$title] = true;
        $lit = normalize_lit($title, $langtag);
        // TODO: add skos:label here for BDRC dataset
        add_title($expression_r, 'WorkBibliographicTitle', $lit);
    }
    add_log_entry($expression_r);
    rdf_to_ttl($config, $graph_expression, $expression_r->localName());
    add_graph_to_global($graph_expression, $expression_r->localName(), $global_graph_fd);
}

function kernel_to_ttl($config, $xml) {
    $global_filename = $config['opts']->getOption('output-dir').'/global.n3';
    $global_graph_fd = fopen($global_filename, "w");
    foreach($xml->item as $item) {
        kernel_item_to_ttl($config, $item, $global_graph_fd);
    }
    fclose($global_graph_fd);
}

