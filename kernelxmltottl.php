<?php

require_once "utils.php";

function get_first_sanskrit_title($item) {
    foreach ($item->children() as $tag => $child) {
        if (($tag == "sanskrit" || $tag == "skt") && !empty($child->__toString())) {
            return $child->__toString();
        }
    }
    return null;
}

function add_props($resource, $props, $propidx, $ontoproperty) {
    if (isset($props[$propidx])) {
        foreach($props[$propidx] as $object) {
            $resource->addResource($ontoproperty, 'bdr:'.$object);
        }
    }
}

function kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc=False, $tengyur=False) {
    global $name_to_bcp, $gl_rkts_props, $gl_rkts_abstract;
    if (isset($item->now) || isset($item->old) || $item->count() < 2)
        return;
    if ($tengyur) {
        $id = $item->rktst;
    } else {
        $id = $item->rkts;
    }
    $idwithletter = ($tengyur ? 'T' : 'K').$id;
    $url_expression = id_to_url_expression($id, $config, $bdrc, $tengyur);
    $graph_expression = new EasyRdf_Graph();
    $expression_r = $graph_expression->resource($url_expression);
    if ($bdrc && isset($gl_rkts_props[$idwithletter])) {
        $props = $gl_rkts_props[$idwithletter];
        add_props($expression_r, $props, 'pa', 'bdo:creatorPandita');
        add_props($expression_r, $props, 'tr', 'bdo:creatorTranslator');
        add_props($expression_r, $props, 're', 'bdo:creatorReviserOfTranslation');
    }
    $firstSanskritTitle = get_first_sanskrit_title($item);
    if ($bdrc && $config['useAbstract']) {
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
            $lit = normalize_lit($firstSanskritTitle, 'sa-x-iats', $bdrc);
            $abstract_r->add('skos:prefLabel', $lit);
            $abstract_r->addResource('bdo:workType', 'bdr:WorkTypeAbstractWork');
            $abstract_r->addResource('bdo:language', 'bdr:LangSa');
            add_title($abstract_r, 'WorkBibliographicTitle', $lit);
            $abstract_r->addResource('bdo:workHasExpression', $url_expression);
            //$abstract_r->addResource('owl:sameAs', id_to_url_abstract($id, $config, !$bdrc, $tengyur));
            rdf_to_ttl($config, $graph_abstract, $abstract_r->localName(), $bdrc);
            if (!$bdrc)
                add_graph_to_global($graph_abstract, $abstract_r->localName(), $global_graph_fd);
        }
    }
    $expression_r->addResource('rdf:type', 'bdo:Work');
    $expression_r->addResource('owl:sameAs', id_to_url_expression($id, $config, !$bdrc, $tengyur));
    $expression_r->addResource('bdo:language', 'bdr:LangBo');
    $expression_r->addLiteral('bdo:workRefrKTs'.($tengyur ? 'T' : 'K'), intval($id));
    $seenTitles = [];
    $seenLangs = [];
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts" || $name == "rktst") continue;
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

function kernel_to_ttl($config, $xml, $global_graph_fd, $bdrc=False, $tengyur=False) {
    foreach($xml->item as $item) {
        kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc, $tengyur);
        //return;
    }
}
