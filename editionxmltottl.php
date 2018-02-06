<?php

require_once "utils.php";

function edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info) {
    $rktsid = $item->rkts;
    $url_parent_text = id_to_url_expression($rktsid, $config);
    $url_broader_edition = id_to_url_edition($edition_info['confinfo']['EID'], $config);
    $url_part = id_to_url_edition_text($edition_info['confinfo']['EID'], $config);
    $graph_part = new EasyRdf_Graph();
    $part_r = $graph_expression->resource($url_part);
    $part_r->addResource('bdo:workExpressionOf', $url_parent_text);
    // TODO: partOf with hierarchical sections
}

function get_base_edition_info($config, $xml, $fileName) {
    $edition_info = [];
    $edition_info['volumeMap'] = [];
    $edition_info['sectionMap'] = [];
    $edition_info['confinfo'] = $config[$fileName];
}

function write_edition_ttl($config, $edition_info, $global_graph_fd) {

}

function edition_to_ttl($config, $xml, $global_graph_fd, $fileName) {
    $edition_info = get_base_edition_info();
    foreach($xml->item as $item) {
        edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info);
    }
}

