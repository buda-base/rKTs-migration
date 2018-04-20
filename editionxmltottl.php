<?php

require_once "utils.php";

function edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info, $fileName, $lastpartnum, &$section_r, $eid=null, $bdrc=False, $tengyur=False) {
    if ($tengyur) {
        $rktsid = $item->rktst;
    } else {
        $rktsid = $item->rkts;
    }
    if ($rktsid == '-')
        return;
    $partnum = $lastpartnum+1;
    $eid = $bdrc ? $eid : $edition_info['confinfo']['EID'];
    $catalogue_index = catalogue_index_xml_to_rdf($item->ref);
    $url_parent_text = id_to_url_expression($rktsid, $config, $bdrc);
    $url_broader_edition = id_to_url_edition($eid, $config, $bdrc);
    $url_part = id_to_url_edition_text($eid, $catalogue_index, $config, $partnum, $bdrc);
    $graph_part = new EasyRdf_Graph();
    $part_r = $graph_part->resource($url_part);
    $part_r->addResource('bdo:workExpressionOf', $url_parent_text);
    $part_r->addResource('rdf:type', 'bdo:Work');
    $part_r->addResource('bdo:workPartType', 'bdr:WorkText');
    // not sure the following blank is good, maybe this is too specific to this point in time and is not future proof... commenting
    // if (!$bdrc){
    //     foreach ($edition_info['confinfo']['RID'] as $rid) {
    //         $url_part_other = id_to_url_edition_text($rid, $rktsid, $config, $partnum, true);
    //         $part_r->addResource('owl:sameAs', $url_part_other);
    //     }
    // } else {
    //     $url_part_other = id_to_url_edition_text($rid, $rktsid, $config, $partnum, false);
    //     $part_r->addResource('owl:sameAs', $url_part_other);
    // }
    $part_r->addLiteral('bdo:'.$edition_info['confinfo']['propSigla'], $catalogue_index);
    $colophon = $item->coloph;
    if (!empty($colophon->__toString())) {
        $lit = normalize_lit($colophon, 'bo-x-ewts', $bdrc);
        $part_r->add('bdo:workColophon', $lit);
    }
    $tib = $item->tib;
    if (!empty($tib->__toString())) {
        $lit = normalize_lit($tib, 'bo-x-ewts', $bdrc);
        $part_r->add('skos:prefLabel', $lit);
        add_title($part_r, 'WorkBibliographicTitle', $lit);
    }
    $skttrans = $item->skttrans;
    if (!empty($skttrans->__toString())) {
        $lit = normalize_lit($skttrans, 'sa-x-ewts', $bdrc);
        $part_r->add('skos:prefLabel', $lit);
        add_title($part_r, 'WorkSanskritTitle', $lit);
    }
    $location = get_text_loc($item->loc, $fileName, 'rkts_'.$rktsid);
    if (!empty($location)) { // useful for xml debugging only
        $current_section = '';
        if ($fileName == "chemdo") {
            $bvolnum = $location['bvolnum'];
            $volinfos = $edition_info['confinfo']['volumeMap']['volnumInfo'][$bvolnum];
            $current_section = $volinfos['sectionName'];
        } else {
            $current_section = $location['section'];
        }
        $url_semantic_section = get_url_section_part($current_section, $edition_info['confinfo']['volumeMap'], $eid, $config, $bdrc);
        $sectionIndex = get_SectionIndex($current_section, $edition_info['confinfo']['volumeMap']);
        $section_partTreeIndex = strval($sectionIndex);
        if (!$section_r || $section_r->getUri() != $url_semantic_section) {
            if ($section_r) {
                rdf_to_ttl($config, $section_r->getGraph(), $section_r->localName(), $bdrc);
                if (!$bdrc)
                    add_graph_to_global($section_r->getGraph(), $section_r->localName(), $global_graph_fd);
            }
            $graph_section = new EasyRdf_Graph();
            $section_r = $graph_section->resource($url_semantic_section);
            $section_r->addResource('rdf:type', 'bdo:Work');
            $section_r->addResource('bdo:workPartOf', $url_broader_edition);
            $section_r->addLiteral('bdo:workPartIndex', $sectionIndex);
            $section_r->addResource('bdo:workPartType', 'bdr:WorkSection');
            $section_r->addLiteral('skos:prefLabel', normalize_lit($current_section, 'bo-x-ewts', $bdrc));
            $section_r->addLiteral('bdo:workPartTreeIndex', $section_partTreeIndex);
        }
        $section_part_count = $section_r->countValues('bdo:workHasPart');
        $section_r->addResource('bdo:workHasPart', $part_r->getUri());
        $part_r->addResource('bdo:workPartType', 'bdr:WorkText');
        $part_r->addLiteral('bdo:workPartIndex', $section_part_count+1);
        $part_partTreeIndex = $section_partTreeIndex.'.'.sprintf("%02d", $section_part_count+1);
        $part_r->addLiteral('bdo:workPartTreeIndex', $part_partTreeIndex);
        $part_r->addResource('bdo:workPartOf', $url_semantic_section);
        add_location($part_r, $location, $edition_info['confinfo']['volumeMap']);
        // foreach ($item->bampo as $bampo) {
        //     $location = get_bampo_loc($bampo->p->__toString(), $fileName, 'rkts_'.$rktsid);
        //     $location['section'] = $current_section;
        //     if (empty($location['volname']))
        //         $location['volname'] = $bvolname;
        // }
        $chapnum = 0;
        $bvolname = '';
        if ($fileName != 'chemdo')
            $bvolname = $location['bvolname'];
        foreach ($item->chap as $chap) { // iterating on chapters
            if ($config['migrateChapters']) break;
            $chaptitle = $chap->__toString();
            if (empty($chaptitle))
                continue;
            $chapnum += 1;
            $partnum += 1;
            $chap_url = id_to_url_edition_text_chapter($eid, $rktsid, $chapnum, $config, $partnum, $bdrc);
            $graph_chap = new EasyRdf_Graph();
            $chap_r = $graph_chap->resource($chap_url);
            $chap_r->addResource('rdf:type', 'bdo:Work');
            $chap_r->addResource('bdo:workPartType', 'bdr:WorkChapter');
            $chap_r->addResource('bdo:workPartOf', $url_part);
            $chap_r->addLiteral('bdo:workPartIndex', $chapnum);
            $chap_r->addLiteral('bdo:workPartTreeIndex', $part_partTreeIndex.'.'.sprintf("%02d", $chapnum));
            $part_r->addResource('bdo:workHasPart', $chap_url);
            $dotpos = strpos($chaptitle, ". ");
            if ($dotpos < 5) {
                $chaptitle = substr($chaptitle, $dotpos+2);
            } else {
                //report_error($fileName, 'wrong chapter format', 'rkts_'.$rktsid, $chaptitle);
            }
            $lit = normalize_lit($chaptitle, 'bo-x-ewts', $bdrc);
            add_title($chap_r, 'WorkOtherTitle', $lit);
            $chap_r->addLiteral('skos:prefLabel', $lit);
            $location = get_chap_loc($chap->p->__toString(), $fileName, 'rkts_'.$rktsid);
            if ($location) {
                $location['section'] = $current_section;
                if ($fileName == 'chamdo' && empty($location['bvolname']))
                    $location['bvolname'] = $bvolname;
                add_location($chap_r, $location, $edition_info['confinfo']['volumeMap']);
            }
            rdf_to_ttl($config, $graph_chap, $chap_r->localName(), $bdrc);
            if (!$bdrc)
                add_graph_to_global($graph_chap, $chap_r->localName(), $global_graph_fd);
        }
    }
    //add_log_entry($part_r);
    rdf_to_ttl($config, $graph_part, $part_r->localName(), $bdrc);
    if (!$bdrc)
        add_graph_to_global($graph_part, $part_r->localName(), $global_graph_fd);
    return $partnum ;
}

function get_url_section_part($sectionName, $editionVolumeMap, $eid, $config, $bdrc=False) {
    foreach ($editionVolumeMap as $sectionIdx => $sectionArr) {
        if ($sectionArr['name'] == $sectionName) {
            return id_to_url_edition_section_part($eid, $config, $sectionIdx+1, $sectionIdx+1, $bdrc);
        }
    }
    return null;
}

function get_SectionIndex($sectionName, $editionVolumeMap) {
    foreach ($editionVolumeMap as $sectionIdx => $sectionArr) {
        if ($sectionArr['name'] == $sectionName) {
            return $sectionIdx+1;
        }
    }
    return null;
}

function create_volume_map($edition_r, &$editionVolumeMap, $config, $edition_info, $global_graph_fd, $eid=null, $bdrc=False) {
    $graph_edition = $edition_r->getGraph();
    $editionId = $bdrc ? $eid : $edition_info['confinfo']['EID'];
    $curVolNum = 0;
    foreach ($editionVolumeMap as $sectionIdx => &$sectionArr) {
        if (!isset($editionVolumeMap['volnumInfo'])) {
            $editionVolumeMap['volnumInfo'] = [];
            $editionVolumeMap['volnumInfo'][0] = null;
        }
        $sectionName = $sectionArr['name'];
        if (!isset($sectionArr['name'])) {
            print_r($sectionArr);
        }
        $sectionUrl = get_url_for_vol_section($editionId, $sectionIdx+1, $config, $bdrc);
        $sectionArr['url'] = $sectionUrl;
        if (!isset($sectionArr['namesUrlMap'])) {
            $sectionArr['namesUrlMap'] = [];
        }
        $namesUrlMap = &$sectionArr['namesUrlMap'];
        $edition_r->addResource('bdo:hasVolumeSection', $sectionUrl);
        $semantic_section_url = id_to_url_edition_section_part($eid, $config, $sectionIdx+1, $sectionIdx+1, $bdrc);
        $edition_r->addResource('bdo:workHasPart', $semantic_section_url);
        $graph_section = new EasyRdf_Graph();
        $section_r = $graph_section->resource($sectionUrl);
        $section_r->add('rdfs:label', $sectionArr['name'], "bo-x-ewts");
        $section_r->add('bdo:seqNum', $sectionIdx+1);
        foreach($sectionArr['volumes'] as $volumeIdx => $volumeName) {
            $curVolNum += 1;
            $volumeUrl = get_url_for_vol($editionId, $curVolNum, $config, $bdrc);
            $section_r->add('bdo:VolumeSectionHasVolume', $volumeUrl);
            $namesUrlMap[$volumeName] = $volumeUrl;
            $editionVolumeMap['volnumInfo'][$curVolNum] = ['url' => $volumeUrl, 'sectionName' => $sectionName];
            $graph_volume = new EasyRdf_Graph();
            $volume_r = $graph_volume->resource($volumeUrl);
            $volume_r->add('bdo:seqNum', $volumeIdx+1);
            $volume_r->add('bdo:VolumeSeqNumInWork', $curVolNum);
            $volume_r->add('rdfs:label', $volumeName, "bo-x-ewts");
            rdf_to_ttl($config, $graph_volume, $volume_r->localName(), $bdrc);
            if (!$bdrc)
                add_graph_to_global($graph_volume, $volume_r->localName(), $global_graph_fd);
        }
        rdf_to_ttl($config, $graph_section, $section_r->localName(), $bdrc);
        if (!$bdrc)
            add_graph_to_global($graph_section, $section_r->localName(), $global_graph_fd);
    }
    //print_r($editionVolumeMap);
}

$authorized_sections = ["'dul ba", "'bum", "nyi khri", "khri brgyad", "khri pa", "brgyad stong", "sher phyin", "dkon brtsegs", "mdo sde", "rgyud", "rnying rgyud", "gzungs", "dus 'khor", "phal chen"];

function add_to_map(&$volumeMap, $location, $fileName, $rktsid, $check_section=false, $eid=null, $bdrc=False) {
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

function write_edition_ttl($config, &$edition_info, $global_graph_fd, $xml, $eid=null, $bdrc=False) {
    $graph_edition = new EasyRdf_Graph();
    $eid = $bdrc ? $eid : $edition_info['confinfo']['EID'] ;
    $url_edition = id_to_url_edition($eid, $config, $bdrc);
    $edition_r = $graph_edition->resource($url_edition);
    $edition_r->addResource('rdf:type', 'bdo:Work');
    $edition_name = $xml->name->__toString();
    $edition_r->add('skos:prefLabel', $edition_name, 'en');
    $edition_r->add('bdo:langScript', $edition_info['confinfo']['langScript']);
    $edition_r->add('bdo:workPrintType', $edition_info['confinfo']['printType']);
    if ($bdrc) {
        $edition_r->addResource('rdfs:seeAlso', id_to_url_edition($edition_info['confinfo']['EID'], $config, !$bdrc));
    } else {
        foreach ($edition_info['confinfo']['RID'] as $rid) {
            $edition_r->addResource('rdfs:seeAlso', id_to_url_edition($rid, $config, !$bdrc));
        }
    }
    create_volume_map($edition_r, $edition_info['confinfo']['volumeMap'], $config, $edition_info, $global_graph_fd, $eid, $bdrc);
    add_log_entry($edition_r);
    rdf_to_ttl($config, $graph_edition, $edition_r->localName(), $bdrc);
    if (!$bdrc)
        add_graph_to_global($graph_edition, $edition_r->localName(), $global_graph_fd);
}

function editions_to_ttl($config, $xml, $global_graph_fd, $fileName, $bdrc=False) {
    if ($bdrc) {
        foreach ($config[$fileName]['RID'] as $rid) {
            edition_to_ttl($config, $xml, $global_graph_fd, $fileName, $rid, $bdrc);
        }
    } else {
        edition_to_ttl($config, $xml, $global_graph_fd, $fileName);
    }
}

function edition_to_ttl($config, $xml, $global_graph_fd, $fileName, $eid=null, $bdrc=False, $tengyur=False) {
    $edition_info = get_base_edition_info($config, $xml, $fileName);
    $eid = $bdrc ? $eid : $edition_info['confinfo']['EID'] ;
    write_edition_ttl($config, $edition_info, $global_graph_fd, $xml, $eid, $bdrc);
    $lastpartnum = $bdrc ? count($edition_info['confinfo']['volumeMap']) : 0;
    $section_r = null;
    foreach($xml->item as $item) {
        $lastpartnum = edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info, $fileName, $lastpartnum, $section_r, $eid, $bdrc, $tengyur);
        //return;
    }
    rdf_to_ttl($config, $section_r->getGraph(), $section_r->localName(), $bdrc);
    if (!$bdrc)
        add_graph_to_global($section_r->getGraph(), $section_r->localName(), $global_graph_fd);
}
