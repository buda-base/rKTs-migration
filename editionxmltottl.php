<?php

require_once "utils.php";

function edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info, $fileName) {
    $rktsid = $item->rkts;
    $url_parent_text = id_to_url_expression($rktsid, $config);
    $url_broader_edition = id_to_url_edition($edition_info['confinfo']['EID'], $config);
    $url_part = id_to_url_edition_text($edition_info['confinfo']['EID'], $rktsid, $config);
    $graph_part = new EasyRdf_Graph();
    $part_r = $graph_part->resource($url_part);
    $part_r->addResource('bdo:workExpressionOf', $url_parent_text);
    $part_r->addResource('rdf:type', 'bdo:Work');
    $catalogue_index = catalogue_index_xml_to_rdf($item->ref);
    $part_r->addLiteral('bdo:'.$edition_info['propSigla'], $catalogue_index);
    $colophon = $item->coloph;
    if (!empty($colophon->__toString())) {
        $lit = normalize_lit($colophon, 'bo-x-ewts');
        $part_r->add('bdo:workColophon', $lit);
    }
    $tib = $item->tib;
    if (!empty($tib->__toString())) {
        $lit = normalize_lit($tib, 'bo-x-ewts');
        // TODO: add skos:label here for BDRC dataset
        add_title($part_r, 'WorkBibliographicTitle', $lit);
    }
    $skttrans = $item->skttrans;
    if (!empty($skttrans->__toString())) {
        $lit = normalize_lit($skttrans, 'sa-x-ewts');
        // TODO: add skos:label here for BDRC dataset
        add_title($part_r, 'WorkSanskritTitle', $lit);
    }
    $location = get_text_loc($item->loc, $fileName, 'rkts_'.$rktsid);
    foreach ($item->bampo as $bampo) {
        $location = get_bampo_loc($bampo->p->__toString(), $fileName, 'rkts_'.$rktsid);
    }
    foreach ($item->chap as $chap) {
        $location = get_chap_loc($chap->p->__toString(), $fileName, 'rkts_'.$rktsid);
    }
    add_log_entry($part_r);
    //rdf_to_ttl($config, $graph_part, $part_r->localName());
    //add_graph_to_global($graph_part, $part_r->localName(), $global_graph_fd);
    // TODO: partOf with hierarchical sections
}

function get_base_edition_info($config, $xml, $fileName) {
    $edition_info = [];
    $edition_info['volumeMap'] = [];
    $edition_info['sectionMap'] = [];
    $edition_info['confinfo'] = $config[$fileName];
}

function write_edition_ttl($config, $edition_info, $global_graph_fd) {
    $graph_edition = new EasyRdf_Graph();
    $url_edition = id_to_url_edition($edition_info['confinfo']['EID'], $config);
    $edition_r = $graph_edition->resource($url_edition);
    $edition_r->addResource('rdf:type', 'bdo:Work');
    add_log_entry($edition_r);
    rdf_to_ttl($config, $graph_edition, $edition_r->localName());
    add_graph_to_global($graph_edition, $edition_r->localName(), $global_graph_fd);
}

function edition_to_ttl($config, $xml, $global_graph_fd, $fileName) {
    $edition_info = get_base_edition_info($config, $xml, $fileName);
    foreach($xml->item as $item) {
        edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info, $fileName);
    //    return;
    }
}

