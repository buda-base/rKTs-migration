<?php

require_once "utils.php";

function get_first_sanskrit_title($item) {
    foreach ($item->children() as $tag => $child) {
        if ($tag == "sanskrit" && !empty($child->__toString())) {
            return $child->__toString();
        }
    }
    return null;
}

function kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc=False) {
    global $name_to_bcp;
    if (isset($item->now) || $item->count() < 2)
        return;
    $id = $item->rkts;
    $url_expression = id_to_url_expression($id, $config, $bdrc);
    $graph_expression = new EasyRdf_Graph();
    $expression_r = $graph_expression->resource($url_expression);
    $firstSanskritTitle = get_first_sanskrit_title($item);
    if ($config['useAbstract'] && $firstSanskritTitle) {
        $graph_abstract = new EasyRdf_Graph();
        $url_abstract = id_to_url_abstract($id, $config, $bdrc);
        $abstract_r = $graph_abstract->resource($url_abstract);
        $abstract_r->addResource('rdf:type', 'bdo:Work');
        $lit = normalize_lit($firstSanskritTitle, 'sa-x-iats', $bdrc);
        $abstract_r->add('skos:prefLabel', $lit);
        add_title($abstract_r, 'WorkBibliographicTitle', $lit);
        $abstract_r->addResource('bdo:workHasExpression', $url_expression);
        $abstract_r->addResource('owl:sameAs', id_to_url_abstract($id, $config, !$bdrc));
        $expression_r->addResource('bdo:workExpressionOf', $url_abstract);
        rdf_to_ttl($config, $graph_abstract, $abstract_r->localName(), $bdrc);
        if (!$bdrc)
            add_graph_to_global($graph_abstract, $abstract_r->localName(), $global_graph_fd);
    }
    $expression_r->addResource('rdf:type', 'bdo:Work');
    $expression_r->addResource('owl:sameAs', id_to_url_expression($id, $config, !$bdrc));
    $labelAdded = false;
    $seenTitles = [];
    $seenLangs = [];
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts") continue;
        if (empty($child->__toString())) continue;
        $langtag = $name_to_bcp[$name];
        if ($config['oneTitleInExpression'] && isset($seenLangs[$langtag]))
            continue;
        $title = $child->__toString();
        if (isset($seenTitles[$title])) {
            report_error('kernel', 'duplicate', 'rkts_'.$id, 'title "'.$title.'" appears more than once');
            continue;
        }
        $lit = normalize_lit($title, $langtag, $bdrc);
        if ($lit) {
            add_title($expression_r, 'WorkBibliographicTitle', $lit);
            if (!isset($seenLangs[$langtag])) {
                $expression_r->add('skos:prefLabel', $lit);
            }
        }
        $seenTitles[$title] = true;
        $seenLangs[$langtag] = true;

    }
    add_log_entry($expression_r);
    rdf_to_ttl($config, $graph_expression, $expression_r->localName(), $bdrc);
    if (!$bdrc)
        add_graph_to_global($graph_expression, $expression_r->localName(), $global_graph_fd);
}

function kernel_to_ttl($config, $xml, $global_graph_fd, $bdrc=False) {
    foreach($xml->item as $item) {
        kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc);
    }
}

