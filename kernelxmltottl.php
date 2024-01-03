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

function kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc=False, $edition="K") {
    global $name_to_bcp, $gl_rkts_props, $gl_rkts_abstract, $gl_KanToTenExpressions, $gl_abstractUrl_catId, $subitemtoitem;
    $id = null;
    if ($edition == "K") {
        $id = $item->rkts->__toString();
    } elseif ($edition == "G") {
        $id = $item->rktsg->__toString();
    } else {
        $id = $item->rktst->__toString();
    }
    if (isset($item->now) || $item->count() < 2)
        return;
    $storeAsDuplicate = false;
    $restoredFromDuplicate = false;
    $url_expression = id_to_url_expression($id, $config, $bdrc, $edition);
    $graph_expression = new EasyRdf_Graph();
    $expression_r = $graph_expression->resource($url_expression);
    $seenTitles = [];
    $seenLangs = [];
    if ($bdrc && $edition == "K" && isset($config['KTMapping'][intval($id)])) {
        $storeAsDuplicate = true;
        $idtostore = intval($config['KTMapping'][intval($id)]);
    }
    $idwithletter = $edition.$id;
    if ($bdrc && $edition == "T" && isset($gl_KanToTenExpressions[intval($id)])) {
        $stored_expr_data = $gl_KanToTenExpressions[intval($id)];
        $expression_r = $stored_expr_data["res"];
        $graph_expression = $stored_expr_data["graph"];
        $seenTitles = $stored_expr_data["seenTitles"];
        $seenLangs = $stored_expr_data["seenLangs"];
        $restoredFromDuplicate = true;
    }
    if ($bdrc && isset($gl_rkts_props[$idwithletter]) && !has_bdrc_abstract($idwithletter, $config, $bdrc, $edition)) {
        $props = $gl_rkts_props[$idwithletter];
        //add_props_creator($expression_r, $props, 'pa', 'bdr:R0ER0018');
        //add_props_creator($expression_r, $props, 'tr', 'bdr:R0ER0026');
        //add_props_creator($expression_r, $props, 're', 'bdr:R0ER0023');
        //add_props_creator($expression_r, $props, 'ma', 'bdr:R0ER0019');
        add_props($expression_r, $props, 'ab', 'bdo:workIsAbout');
        add_props($expression_r, $props, 'ge', 'bdo:workGenre');
    }
    $abstract_r = null;
    $firstTitleLits = get_first_title_lits($item, $bdrc);
    if ($bdrc && $config['useAbstract'] && !$storeAsDuplicate && $edition != "G") { // just one abstract text for duplicates
        $url_abstract = id_to_url_abstract($id, $config, $bdrc, $edition);
        $expression_r->addResource('bdo:workHasParallelsIn', $url_abstract);
        if (!$bdrc || !isset($config['SameTextDifferentTranslation'][$idwithletter])) { // we don't add the abstract text twice
            $graph_abstract = new EasyRdf_Graph();
            $abstract_r = $graph_abstract->resource($url_abstract);
            if ($bdrc && isset($gl_rkts_props[$idwithletter]) && !has_bdrc_abstract($idwithletter, $config, $bdrc, $edition)) {
                $props = $gl_rkts_props[$idwithletter];
                //add_props_creator($abstract_r, $props, 'ma', 'bdr:R0ER0019');
                add_props($abstract_r, $props, 'ab', 'bdo:workIsAbout');
                add_props($abstract_r, $props, 'ge', 'bdo:workGenre');
            }
            $abstract_r->addResource('rdf:type', 'bdo:Work'); // abstract
            // TODO: some are from Chinese  
            $abstract_r->addResource('bdo:language', 'bdr:LangInc');
            $abstract_r->addLiteral('bdo:isRoot', true);
            foreach ($firstTitleLits as $firstTitleLit) {
                if ($firstTitleLit->getLang() == 'sa-x-iast') {
                    $abstract_r->add('skos:prefLabel', $firstTitleLit);
                    $titletotest = strtolower(str_replace(array("-", " "), "", $firstTitleLit->getValue()));
                    $seenTitles[$titletotest] = true;
                } else {
                    //$abstract_r->add('skos:altLabel', $firstTitleLit);
                }
                //add_title($abstract_r, 'WorkBibliographicalTitle', $firstTitleLit);
            }
            $abstract_r->addResource('bdo:workHasParallelsIn', $url_expression);
            //$abstract_r->addResource('owl:sameAs', id_to_url_abstract($id, $config, !$bdrc, $edition));
            add_log_entry($abstract_r);
        }
    }
    $expression_r->addResource('rdf:type', 'bdo:Work'); // abstract
    if ($bdrc) {
        $expression_r->addResource('owl:sameAs', id_to_url_expression($id, $config, !$bdrc, $edition));
    } else {
        $expression_r->addResource('owl:sameAs', id_to_url_expression($id, $config, !$bdrc, $edition));
    }
    $expression_r->addResource('bdo:language', 'bdr:LangBo');
    //$expression_r->addResource('bdo:script', 'bdr:ScriptTibt');
    $idUri = bnode_url("ID", $expression_r, $expression_r, $id);
    $idNode = $expression_r->getGraph()->resource($idUri);
    $expression_r->addResource('bf:identifiedBy', $idNode);
    $idNode->add('rdf:value', $id);
    $idNode->addResource('rdf:type', 'bdr:RefrKTs'.$edition);
    $expression_r->addLiteral('bdo:isRoot', true);
    foreach ($item->children() as $child) {
        $name = $child->getName();
        if ($name == "rkts" || $name == "rktst" || $name == "rktsg") continue;
        if (empty($child->__toString()) && $name != "cmp") {
            continue;
        }
        if ($name == "section") continue;
        if ($name == "English84000") continue;
        if ($name == "cmp") {
            $rtype = $child->type->__toString();
            $ref = $child->ref->__toString();
            $rktsref = id_to_url_expression(substr($ref, 1), $config, $bdrc, substr($ref, 0, 1) == "T");
            if ($rtype == "parallel") {
                $expression_r->addResource('bdo:workHasParallelsIn', $rktsref);
            }
            if ($rtype == "parallel-part") {
                $expression_r->addResource('bdo:workHasParallelPartsIn', $rktsref);
            }
            if ($rtype == "extract") {
                $expression_r->addResource('bdo:workExtractOf', $rktsref);
            }
            if ($rtype == "translation-taisho") {
                $expression_r->addResource('bdo:workTranslationOf', "bdr:WA0TTET".$ref);
            }
            continue;
        }
        if ($name == "note") {
            $noteUri = bnode_url("NT", $expression_r, $expression_r, $child->__toString());
            $noteNode = $expression_r->getGraph()->resource($noteUri);
            $expression_r->addResource('bdo:note', $noteNode);
            $noteNode->add('bdo:noteText', $child->__toString());
            $noteNode->addResource('rdf:type', "bdo:Note");
            continue;
        }
        if ($name == "subitem") {
            $subitem = $child->__toString();
            $subitemtoitem[$subitem] = $id;
            $expression_r->addResource('bdo:hasPart', id_to_url_expression($subitem, $config, $bdrc, $edition));
            continue;
        }
        if (array_key_exists($id, $subitemtoitem)) {
            $parentid = $subitemtoitem[$id];
            $expression_r->addResource('bdo:partOf', id_to_url_expression($parentid, $config, $bdrc, $edition));
        }
        $langtag = $name_to_bcp[$name];
        if (!$langtag) continue;
        if ($config['oneTitleInExpression'] && isset($seenLangs[$langtag]))
            continue;
        $title = trim($child->__toString());
        $titletotest = $title;
        if ($langtag == 'sa-x-iast') {
            $titletotest = strtolower(str_replace(array("-", " "), "", $title));
        }
        if (!$restoredFromDuplicate && isset($seenTitles[$titletotest])) {
            //report_error('kernel', 'duplicate', 'rkts_'.$id, 'title "'.$title.'" appears more than once');
            continue;
        }
        $lit = normalize_lit($title, $langtag, $bdrc);
        if ($lit) {
            if (!isset($seenLangs[$langtag]) && $lit->getLang() == 'bo-x-ewts') {
                $expression_r->add('skos:prefLabel', $lit);
            } elseif ($lit->getLang() == 'sa-x-iast' && $abstract_r != null) {
                $abstract_r->add('skos:altLabel', $lit);
            } elseif ($lit->getLang() != 'en') {
                $expression_r->add('skos:altLabel', $lit);
            }
        }
        $seenTitles[$titletotest] = true;
        $seenLangs[$langtag] = true;
    }
    if ($abstract_r != null) {
        rdf_to_ttl($config, $graph_abstract, $abstract_r->localName(), $bdrc);
        if (!$bdrc)
            add_graph_to_global($graph_abstract, $abstract_r->localName(), $global_graph_fd);
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

function kernel_to_ttl($config, $xml, $global_graph_fd, $bdrc=False, $edition="K") {
    foreach($xml->item as $item) {
        kernel_item_to_ttl($config, $item, $global_graph_fd, $bdrc, $edition);
    }
}

function fillmappings($xml, $edition="K") {
    global $gl_rkts_kmapping;
    foreach($xml->item as $item) {
        if (isset($item->now)) {
            if ($edition == "T") {
                $id = $item->rktst->__toString();
            } elseif ($edition == "G") {
                $id = $item->rktsg->__toString();
            } else {
                $id = $item->rkts->__toString();
            }
            $now = $item->now->__toString();
            $gl_rkts_kmapping[$id] = $now;
        }
    }
}
