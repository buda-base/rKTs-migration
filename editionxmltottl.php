<?php

require_once "utils.php";

function edition_item_to_ttl($config, $item, $global_graph_fd, &$edition_info, $fileName) {
    $rktsid = $item->rkts;
    $url_parent_text = id_to_url_expression($rktsid, $config);
    $url_broader_edition = id_to_url_edition($edition_info['confinfo']['EID'], $config);
    $url_part = id_to_url_edition_text($edition_info['confinfo']['EID'], $rktsid, $config);
    $graph_part = new EasyRdf_Graph();
    $part_r = $graph_part->resource($url_part);
    $part_r->addResource('bdo:workExpressionOf', $url_parent_text);
    $part_r->addResource('rdf:type', 'bdo:Work');
    $catalogue_index = catalogue_index_xml_to_rdf($item->ref);
    $part_r->addLiteral('bdo:'.$edition_info['confinfo']['propSigla'], $catalogue_index);
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
    $volumeMapWithUrls = create_volume_map($edition_r, $edition_info['volumeMap']);
    $location = get_text_loc($item->loc, $fileName, 'rkts_'.$rktsid);
    if (!empty($location)) {
        $current_section = $location['section'];
        $bvolname = $location['bvolname'];
        foreach ($item->bampo as $bampo) {
            $location = get_bampo_loc($bampo->p->__toString(), $fileName, 'rkts_'.$rktsid);
            $location['section'] = $current_section;
            if (empty($location['volname']))
                $location['volname'] = $bvolname;
        }
        foreach ($item->chap as $chap) {
            $location = get_chap_loc($chap->p->__toString(), $fileName, 'rkts_'.$rktsid);
            $location['section'] = $current_section;
            if (empty($location['volname']))
                $location['volname'] = $bvolname;
        }
    }
    add_log_entry($part_r);
    //rdf_to_ttl($config, $graph_part, $part_r->localName());
    //add_graph_to_global($graph_part, $part_r->localName(), $global_graph_fd);
    // TODO: partOf with hierarchical sections
}

function create_volume_map($edition_r, &$editionVolumeMap) {
    $graph = $edition_r->getGraph();
    foreach ($editionVolumeMap as $sectionIdx => &$sectionArr) {
        $sectionName = $sectionArr['name'];
        $sectionUrl = get_url_for_vol_section($sectionIdx+1, $config);
        $sectionArr['url'] = $sectionUrl;
        if (!isset($sectionArr['namesUrlMap'])) {
            $sectionArr['namesUrlMap'] = [];
        }
        $namesUrlMap = $sectionArr['namesUrlMap'];
        $edition_r->addResource('bdo:hasVolumeSection', $sectionUrl);
        // new graph?
        $section_r = $graph->resource($sectionUrl);
        $section_r->add('rdfs:label', $sectionArr['name'], "bo-x-ewts");
        $section_r->add('bdo:seqNum', $sectionIdx+1);
        foreach($sectionArr['volumes'] as $volumeIdx => $volumeName) {
            $volumeUrl = get_url_for_vol($sectionIdx+$volumeIdx+2, $config);
            $section_r->add('bdo:VolumeSectionHasVolume', $volumeUrl);
            $namesUrlMap[$volumeName] = $volumeUrl;
            $volume_r = $graph->resource($volumeUrl);
            $volume_r->add('bdo:seqNum', $volumeIdx+1);
            $volume_r->add('bdo:VolumeSeqNumInWork', $sectionIdx+$volumeIdx+2);
            $volume_r->add('rdfs:label', $volumeName, "bo-x-ewts");
        }
    }
}

$authorized_sections = ["'dul ba", "'bum", "nyi khri", "khri brgyad", "khri pa", "brgyad stong", "sher phyin", "dkon brtsegs", "mdo sde", "rgyud", "rnying rgyud", "gzungs", "dus 'khor", "phal chen"];

function add_to_map(&$volumeMap, $location, $fileName, $rktsid, $check_section=false) {
    global $authorized_sections;
    $section = $location['section'];
    if ($check_section && !in_array($section, $authorized_sections)) {
        report_error($fileName, 'invalid_section', $rktsid, 'invalid section name: "'.$section.'"');
    }
    if (!isset($volumeMap[$location['section']]))
        $volumeMap[$section] = [];
    $sectionMap = $volumeMap[$section];
    $volume = isset($location['volname']) ? $location['volname'] : $location['bvolname'];
    if (empty($volumeMap[$section]) || end($volumeMap[$section]) !== $volume) {
        // if (in_array($volume, $volumeMap[$section])) {
        //     report_error($fileName, 'invalid_vol_order', $rktsid, 'incoherent volume names, "'.$volume.'" appears twice.');
        // }
        $volumeMap[$section][] = $volume;
    }
    
}

function get_base_edition_info($config, $xml, $fileName) {
    $edition_info = [];
    $edition_info['volumeMap'] = [];
    $edition_info['sectionMap'] = [];
    $edition_info['confinfo'] = $config[$fileName];
    return $edition_info;
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

