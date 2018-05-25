<?php

require_once "utils.php";

function get_first_sanskrit_title($item) {
    foreach ($item->children() as $tag => $child) {
        if (($tag == "sanskrit" || $tag == "skt") && !empty($child->__toString())) {
            return trim($child->__toString());
        }
    }
    return null;
}

function add_props($resource, $props, $propidx, $ontoproperty) {
    if (isset($props[$propidx])) {
        foreach($props[$propidx] as $object) {
            $object = trim($object);
            if ($resource->localName() == $object) {
                report_error('kernel', 'pointer_to_self', $object, 'property '.$propidx);
                return;
            }
            $resource->addResource($ontoproperty, 'bdr:'.$object);
        }
    }
}

$gl_KanToTenExpressions = [];

function kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc=False, $tengyur=False) {
    global $name_to_bcp, $gl_rkts_props, $gl_rkts_abstract, $gl_KanToTenExpressions, $gl_abstractUrl_catId;
    if (isset($item->now) || isset($item->old) || $item->count() < 2)
        return;
    if ($tengyur) {
        $id = $item->rktst;
    } else {
        $id = $item->rkts;
    }
    $storeAsDuplicate = false;
    $restoredFromDuplicate = false;
    $url_expression = id_to_url_expression($id, $config, $bdrc, $tengyur);
    $graph_expression = new EasyRdf_Graph();
    $expression_r = $graph_expression->resource($url_expression);
    $seenTitles = [];
    $seenLangs = [];
    if ($bdrc && !$tengyur && isset($config['KTMapping'][intval($id)])) {
        $storeAsDuplicate = true;
        $idtostore = intval($config['KTMapping'][intval($id)]);
    }
    $idwithletter = ($tengyur ? 'T' : 'K').$id;
    if ($bdrc && $tengyur && isset($gl_KanToTenExpressions[intval($id)])) {
        $stored_expr_data = $gl_KanToTenExpressions[intval($id)];
        $expression_r = $stored_expr_data["res"];
        $graph_expression = $stored_expr_data["graph"];
        $seenTitles = $stored_expr_data["seenTitles"];
        $seenLangs = $stored_expr_data["seenLangs"];
        $restoredFromDuplicate = true;
    }
    if ($bdrc && isset($gl_rkts_props[$idwithletter])) {
        $props = $gl_rkts_props[$idwithletter];
        add_props($expression_r, $props, 'pa', 'bdo:creatorPandita');
        add_props($expression_r, $props, 'tr', 'bdo:creatorTranslator');
        add_props($expression_r, $props, 're', 'bdo:creatorReviserOfTranslation');
    }
    if (isset($gl_abstractUrl_catId[$url_expression])) {
        foreach($gl_abstractUrl_catId[$url_expression] as $text_url) {
            $expression_r->addResource('bdo:workHasExpression', $text_url);
        }
    }
    $firstSanskritTitle = get_first_sanskrit_title($item);
    if ($bdrc && $config['useAbstract'] && !$storeAsDuplicate) { // just one abstract text for duplicates
        $url_abstract = id_to_url_abstract($id, $config, $bdrc, $tengyur);
        $expression_r->addResource('bdo:workExpressionOf', $url_abstract);
        if (!$bdrc || !isset($config['SameTextDifferentTranslation'][$idwithletter])) { // we don't add the abstract text twice
            $graph_abstract = new EasyRdf_Graph();
            $abstract_r = $graph_abstract->resource($url_abstract);
            if ($bdrc && isset($gl_rkts_props[$idwithletter])) {
                $props = $gl_rkts_props[$idwithletter];
                add_props($abstract_r, $props, 'ma', 'bdo:creatorMainAuthor');
                add_props($abstract_r, $props, 'ab', 'bdo:workIsAbout');
                add_props($abstract_r, $props, 'ge', 'bdo:workGenre');
            }
            $abstract_r->addResource('rdf:type', 'bdo:Work');
            $lit = normalize_lit($firstSanskritTitle, 'sa-x-iast', $bdrc);
            $abstract_r->add('skos:prefLabel', $lit);
            $abstract_r->addResource('bdo:workType', 'bdr:WorkTypeAbstractWork');
            $abstract_r->addResource('bdo:language', 'bdr:LangSa');
            add_title($abstract_r, 'WorkBibliographicalTitle', $lit);
            $abstract_r->addResource('bdo:workHasExpression', $url_expression);
            //$abstract_r->addResource('owl:sameAs', id_to_url_abstract($id, $config, !$bdrc, $tengyur));
            add_log_entry($abstract_r);
            rdf_to_ttl($config, $graph_abstract, $abstract_r->localName(), $bdrc);
            if (!$bdrc)
                add_graph_to_global($graph_abstract, $abstract_r->localName(), $global_graph_fd);
        }
    }
    $expression_r->addResource('rdf:type', 'bdo:Work');
    $expression_r->addResource('owl:sameAs', id_to_url_expression($id, $config, !$bdrc, $tengyur));
    $expression_r->addResource('bdo:workLangScript', 'bdr:BoTibt'); // TODO: some works are just sanskrit dharanis...
    $expression_r->addLiteral('bdo:workRefrKTs'.($tengyur ? 'T' : 'K'), intval($id));
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts" || $name == "rktst") continue;
        if (empty($child->__toString())) continue;
        $langtag = $name_to_bcp[$name];
        if ($config['oneTitleInExpression'] && isset($seenLangs[$langtag]))
            continue;
        $title = trim($child->__toString());
        if (!$restoredFromDuplicate && isset($seenTitles[$title])) {
            report_error('kernel', 'duplicate', 'rkts_'.$id, 'title "'.$title.'" appears more than once');
            continue;
        }
        $lit = normalize_lit($title, $langtag, $bdrc);
        if ($lit) {
            add_title($expression_r, 'WorkBibliographicalTitle', $lit);
            if (!isset($seenLangs[$langtag])) {
                $expression_r->add('skos:prefLabel', $lit);
            }
        }
        $seenTitles[$title] = true;
        $seenLangs[$langtag] = true;
    }
    if ($storeAsDuplicate) {
        $gl_KanToTenExpressions[$idtostore] = [
            "res" => $expression_r,
            "graph" => $graph_expression,
            "seenLangs" => $seenLangs,
            "seenTitles" => $seenTitles
        ];
        return;
    }
    add_log_entry($expression_r);
    rdf_to_ttl($config, $graph_expression, $expression_r->localName(), $bdrc);
    if (!$bdrc)
        add_graph_to_global($graph_expression, $expression_r->localName(), $global_graph_fd);
}

function kernel_to_ttl($config, $xml, $global_graph_fd, $bdrc=False, $tengyur=False) {
    foreach($xml->item as $item) {
        kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc, $tengyur);
        //return;
    }
}
