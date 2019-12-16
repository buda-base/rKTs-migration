<?php

require_once "utils.php";

function get_first_title_lit($item, $bdrc) {
    foreach ($item->children() as $tag => $child) {
        $content = trim($child->__toString());
        if (($tag == "sanskrit" || $tag == "skt") && !empty($content)) {
            return normalize_lit($content, 'sa-x-iast', $bdrc);
        }
        if (($tag == "tibetan" || $tag == "tib") && !empty($content)) {
            return normalize_lit($content, 'bo-x-ewts', $bdrc);
        }
    }
    return null;
}

function get_first_title_lits($item, $bdrc) {
    $res = [];
    $hasSkt = False;
    $hasTib = False;
    foreach ($item->children() as $tag => $child) {
        $content = trim($child->__toString());
        if (($tag == "sanskrit" || $tag == "skt") && !empty($content) && !$hasSkt) {
            $res[] = normalize_lit($content, 'sa-x-iast', $bdrc);
            $hasSkt = True;
        }
        if (($tag == "tibetan" || $tag == "tib") && !empty($content) && !$hasTib) {
            $res[] = normalize_lit($content, 'bo-x-ewts', $bdrc);
            $hasTib = True;
        }
    }
    return $res;
}

function add_props($resource, $props, $propidx, $ontoproperty) {
    if (isset($props[$propidx])) {
        foreach($props[$propidx] as $object) {
            $object = trim($object);
            if ($resource->localName() == $object) {
                report_error('kernel', 'pointer_to_self', $object, 'property '.$propidx);
            } else {
                $resource->addResource($ontoproperty, 'bdr:'.$object);
            }
        }
    }
}

function add_props_creator($resource, $props, $propidx, $roleres) {
    if (isset($props[$propidx])) {
        foreach($props[$propidx] as $object) {
            $object = trim($object);
            if ($resource->localName() == $object) {
                report_error('kernel', 'pointer_to_self', $object, 'property '.$propidx);
            } else {
                $nodeUri = bnode_url("CR", $resource, $resource, $roleres.$object);
                $airNode = $resource->getGraph()->resource($nodeUri);
                $airNode->addResource('rdf:type', 'bdo:AgentAsCreator');
                $resource->addResource('bdo:creator', $airNode);
                $airNode->addResource('bdo:agent', 'bdr:'.$object);
                $airNode->addResource('bdo:role', $roleres);
            }
        }
    }
}

$gl_KanToTenExpressions = [];

$subitemtoitem = [];

function kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc=False, $tengyur=False) {
    global $name_to_bcp, $gl_rkts_props, $gl_rkts_abstract, $gl_KanToTenExpressions, $gl_abstractUrl_catId, $subitemtoitem;
    if ($tengyur) {
        $id = $item->rktst->__toString();
    } else {
        $id = $item->rkts->__toString();
    }
    if (isset($item->now) || $item->count() < 2)
        return;
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
        add_props_creator($expression_r, $props, 'pa', 'bdr:R0ER0018');
        add_props_creator($expression_r, $props, 'tr', 'bdr:R0ER0026');
        add_props_creator($expression_r, $props, 're', 'bdr:R0ER0023');
    }
    if (isset($gl_abstractUrl_catId[$url_expression])) {
        foreach($gl_abstractUrl_catId[$url_expression] as $text_url) {
            $expression_r->addResource('bdo:workHasInstance', $text_url);
        }
    }
    $firstTitleLits = get_first_title_lits($item, $bdrc);
    if ($bdrc && $config['useAbstract'] && !$storeAsDuplicate) { // just one abstract text for duplicates
        $url_abstract = id_to_url_abstract($id, $config, $bdrc, $tengyur);
        $expression_r->addResource('bdo:workTranslationOf', $url_abstract);
        if (!$bdrc || !isset($config['SameTextDifferentTranslation'][$idwithletter])) { // we don't add the abstract text twice
            $graph_abstract = new EasyRdf_Graph();
            $abstract_r = $graph_abstract->resource($url_abstract);
            if ($bdrc && isset($gl_rkts_props[$idwithletter])) {
                $props = $gl_rkts_props[$idwithletter];
                add_props_creator($abstract_r, $props, 'ma', 'bdr:R0ER0019');
                add_props($abstract_r, $props, 'ab', 'bdo:workIsAbout');
                add_props($abstract_r, $props, 'ge', 'bdo:workGenre');
            }
            $abstract_r->addResource('rdf:type', 'bdo:Work'); // abstract
            // TODO: some are from Chinese  
            $abstract_r->addResource('bdo:workLangScript', 'bdr:Inc');
            $abstract_r->addLiteral('bdo:isRoot', true);
            foreach ($firstTitleLits as $firstTitleLit) {
                $abstract_r->add('skos:prefLabel', $firstTitleLit);
                //add_title($abstract_r, 'WorkBibliographicalTitle', $firstTitleLit);
            }
            $abstract_r->addResource('bdo:workHasTranslation', $url_expression);
            //$abstract_r->addResource('owl:sameAs', id_to_url_abstract($id, $config, !$bdrc, $tengyur));
            add_log_entry($abstract_r);
            rdf_to_ttl($config, $graph_abstract, $abstract_r->localName(), $bdrc);
            if (!$bdrc)
                add_graph_to_global($graph_abstract, $abstract_r->localName(), $global_graph_fd);
        }
    }
    $expression_r->addResource('rdf:type', 'bdo:Work'); // abstract
    if ($bdrc) {
        $expression_r->addResource('adm:sameAsrKTs', id_to_url_expression($id, $config, !$bdrc, $tengyur));
    } else {
        $expression_r->addResource('owl:sameAs', id_to_url_expression($id, $config, !$bdrc, $tengyur));
    }
    //$expression_r->addResource('bdo:language', 'bdr:LangBo');
    $expression_r->addResource('bdo:script', 'bdr:ScriptTibt');
    $expression_r->addLiteral('bdo:workRefrKTs'.($tengyur ? 'T' : 'K'), $id);
    $expression_r->addLiteral('bdo:isRoot', true);
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts" || $name == "rktst") continue;
        if (empty($child->__toString())) continue;
        if ($name == "note") {
            $noteUri = bnode_url("NT", $expression_r, $expression_r, $child->__toString());
            $noteNode = $expression_r->getGraph()->resource($noteUri);
            $expression_r->addResource('bdo:note', $noteNode);
            $noteNode->add('bdo:noteText', $child->__toString());
            continue;
        }
        if ($name == "subitem") {
            $subitem = $child->__toString();
            $subitemtoitem[$subitem] = $id;
            $expression_r->addResource('bdo:workHasPart', id_to_url_expression($subitem, $config, $bdrc, $tengyur));
            continue;
        }
        if (array_key_exists($id, $subitemtoitem)) {
            $parentid = $subitemtoitem[$id];
            $expression_r->addResource('bdo:workPartOf', id_to_url_expression($parentid, $config, $bdrc, $tengyur));
        }
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
            //add_title($expression_r, 'WorkBibliographicalTitle', $lit);
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

function fillmappings($xml, $tengyur=False) {
    global $gl_rkts_kmapping;
    foreach($xml->item as $item) {
        if (isset($item->now)) {
            if ($tengyur) {
                $id = $item->rktst->__toString();
            } else {
                $id = $item->rkts->__toString();
            }
            $now = $item->now->__toString();
            $gl_rkts_kmapping[$id] = $now;
        }
    }
}
