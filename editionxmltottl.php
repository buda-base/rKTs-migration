<?php

require_once "utils.php";

$tag_to_event_role = [
    'author' => ['bdr:R0ER0011', 'bdo:AuthoredEvent'],
    'translator-pandita' => ['bdr:R0ER0018', 'bdo:TranslatedEvent'],
    'translator' => ['bdr:R0ER0026', 'bdo:TranslatedEvent'],
    'sponsor' => ['bdr:R0ER0030', 'bdo:TranslatedEvent'],
    'scribe' => ['bdr:R0ER0024', 'bdo:TranslatedEvent'],
    'translator2-pandita' => ['bdr:R0ER0018', 'bdo:SecondTranslatedEvent'],
    'translator2' => ['bdr:R0ER0018', 'bdo:SecondTranslatedEvent'],
    'revisor-pandita' => ['bdr:R0ER0018', 'bdo:RevisedEvent'],
    'revisor' => ['bdr:R0ER0023', 'bdo:RevisedEvent'],
    'revisor2-pandita' => ['bdr:R0ER0018', 'bdo:SecondRevisedEvent'],
    'revisor2' => ['bdr:R0ER0023', 'bdo:SecondRevisedEvent'],
    'revisor3-pandita' => ['bdr:R0ER0018', 'bdo:ThirdRevisedEvent'],
    'revisor3' => ['bdr:R0ER0023', 'bdo:ThirdRevisedEvent']
];

function edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info, $fileName, $lastpartnum, $lastloc, &$section_r, $eid=null, $bdrc=False, $edition="K", $parentId=null, $thisPartTreeIndex=null, $hassections=true) {
    global $gl_abstractUrl_catId , $tag_to_event_role;
    if ($edition == "T") {
        $rktsid = $item->rktst;
    } elseif ($edition == "G") {
        $rktsid = $item->rktsg;
    } else {
        $rktsid = $item->rkts;
    }
    if ($rktsid == "" || $rktsid == '-' || $rktsid == "new" || $rktsid == "?" || $rktsid == "new?" )
        $rktsid = null;
    $partnum = $lastpartnum+1;
    if ($item->ref == "A") {
        return array($lastpartnum, $lastloc) ;
    }
    $eid = $bdrc ? $eid : $edition_info['confinfo']['EID'];
    $catalogue_index = catalogue_index_xml_to_rdf($item->ref, $edition_info, $edition);
    $url_broader_edition = id_to_url_edition($eid, $config, $bdrc);
    $url_part = id_to_url_edition_text($eid, $catalogue_index, $config, $partnum, $bdrc);
    $graph_part = new EasyRdf_Graph();
    $part_r = $graph_part->resource($url_part);
    if ($rktsid) {
        $url_parent_text = id_to_url_expression($rktsid, $config, $bdrc, $edition);
        if (!isset($gl_abstractUrl_catId[$url_parent_text])) {
            $gl_abstractUrl_catId[$url_parent_text] = [];
        }
        array_push($gl_abstractUrl_catId[$url_parent_text], $url_part);
        $part_r->addResource('bdo:instanceOf', $url_parent_text);
    }
    $part_r->addResource('rdf:type', 'bdo:Instance');
    $part_r->addResource('bdo:partType', 'bdr:PartTypeText');
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

    $idUri = bnode_url("ID", $part_r, $part_r, $item->ref);
    $idNode = $part_r->getGraph()->resource($idUri);
    $part_r->addResource('bf:identifiedBy', $idNode);
    $idNode->add('rdf:value', $item->ref);
    $idNode->addResource('rdf:type', 'bdr:'.$edition_info['confinfo']['propSigla']);

    $colophon = $item->coloph;
    if (!empty($colophon->__toString())) {
        $lit = normalize_lit($colophon, 'bo-x-ewts', $bdrc);
        $part_r->add('bdo:colophon', $lit);
    }
    $tib = trim($item->tib->__toString());
    $bibliographicalTitleNode = null;
    if (!empty($tib)) {
        $lit = normalize_lit($tib, 'bo-x-ewts', $bdrc);
        $part_r->add('skos:prefLabel', $lit);
        //if on derge, tib is the incipit title:
        if ($edition_info['confinfo']['EID'] == "D") {
            $bibliographicalTitleNode = add_title($part_r, 'IncipitTitle', $lit);
        } else {
            $bibliographicalTitleNode = add_title($part_r, 'Title', $lit);
        }
    }
    $coltitle = trim($item->coltitle->__toString());
    if (!empty($coltitle) && $coltitle != "-") {
        if ($tib != $coltitle) {
            $lit = normalize_lit($coltitle, 'bo-x-ewts', $bdrc);
            if (empty($tib)) {
                $part_r->add('skos:prefLabel', $lit);
            }
            add_title($part_r, 'ColophonTitle', $lit, $bibliographicalTitleNode);
        } else {
            $bibliographicalTitleNode->addResource('rdf:type', 'bdo:ColophonTitle');
        }
    }
    $skttrans = trim($item->skttrans->__toString());
    if (!empty($skttrans) && $skttrans != "-") {
        $lit = normalize_lit($skttrans, 'sa-x-ewts', $bdrc);
        add_title($part_r, 'IncipitTitle', $lit, $bibliographicalTitleNode);
    }
    $bsktrans = trim($item->bsktrans->__toString());
    if (!empty($bsktrans) && $bsktrans != "-") {
        $lit = normalize_lit($bsktrans, 'bsk-x-ewts', $bdrc);
        add_title($part_r, 'IncipitTitle', $lit, $bibliographicalTitleNode);
    }
    $zhtrans = trim($item->zhtrans->__toString());
    if (!empty($zhtrans)) {
        $lit = normalize_lit($zhtrans, 'zh-x-ewts', $bdrc);
        add_title($part_r, 'IncipitTitle', $lit, $bibliographicalTitleNode);
    }
    /*
    $events = [];
    foreach ($tag_to_event_role as $tag => $eventrole) {
        $event = $eventrole[1];
        $role = $eventrole[0];
        foreach($item->$tag as $node) {
            $lit = normalize_lit($node, 'bo-x-ewts', $bdrc);
            $eventResource = null;
            if (!array_key_exists($event, $events)) {
                $nodeUri = bnode_url("EV", $part_r, $part_r, $event.$lit->getValue());
                $eventResource = $part_r->getGraph()->resource($nodeUri);
                $eventResource->addResource('rdf:type', $event);
                $part_r->addResource('bdo:instanceEvent', $eventResource);
                $events[$event] = $eventResource;
            } else {
                $eventResource = $events[$event];
            }
            $airUri = bnode_url("AIR", $part_r, $part_r, $event.$lit->getValue());
            $airResource = $part_r->getGraph()->resource($airUri);
            $airResource->addResource('rdf:type', 'bdo:AgentAsCreator');
            $airResource->addResource('bdo:role', $role);
            $airResource->add('rdfs:label', $lit);
            $eventResource->addResource('bdo:eventWho', $airResource);
        }
    }*/
    $location = get_text_loc($item, $fileName, 'rkts_'.$rktsid, $eid);
    if (!empty($location) && (!$hassections || array_key_exists('section', $location))) { // useful for xml debugging only
        $current_section = $location['section'];
        if ($current_section == null) {
            $current_section = $location['bpsection'];
        }
        if ($hassections) {
            $sectionIndex = get_SectionIndex($current_section, $edition_info['confinfo']['volumeMap']);
            if (!$sectionIndex) {
                if (!$section_r) {
                    $sectionIndex = 1;
                } else {
                    //print($section_r->getLiteral('bdo:partIndex'));
                    $sectionIndex = intval($section_r->getLiteral('bdo:partIndex')->getValue());
                    $curSectionName = strval($section_r->getLiteral('skos:prefLabel')->getValue());
                    if (normalize_lit($current_section, 'bo-x-ewts', $bdrc) != $curSectionName) {
                        $sectionIndex += 1;
                    }
                }
            }
            $url_semantic_section = get_url_section_part($current_section, $edition_info['confinfo']['volumeMap'], $eid, $config, $bdrc, $sectionIndex);
            if ($parentId != null) {
                $url_semantic_section = $parentId;
            }
            if ($url_semantic_section == null) {
                print("error: no section url for ".$current_section);
                return array($partnum, $location);
            }
            $section_partTreeIndex = sprintf("%02d", $sectionIndex);
            if ($parentId == null && (!$section_r || $section_r->getUri() != $url_semantic_section)) {
                if ($section_r) {
                    if ($lastloc != null && !empty($lastloc))
                        add_location_section_end($section_r, $lastloc, $edition_info, $eid);
                    rdf_to_ttl($config, $section_r->getGraph(), $section_r->localName(), $bdrc);
                    if (!$bdrc)
                        add_graph_to_global($section_r->getGraph(), $section_r->localName(), $global_graph_fd);
                }
                $graph_section = new EasyRdf_Graph();
                $section_r = $graph_section->resource($url_semantic_section);
                $section_r->addResource('rdf:type', 'bdo:Instance');
                $section_r->addResource('bdo:partOf', $url_broader_edition);
                $section_r->addResource('bdo:inRootInstance', $url_broader_edition);
                $section_r->addLiteral('bdo:partIndex', $sectionIndex);
                $section_r->addResource('bdo:partType', 'bdr:PartTypeSection');
                $section_r->addLiteral('skos:prefLabel', normalize_lit($current_section, 'bo-x-ewts', $bdrc));
                $section_r->addLiteral('bdo:partTreeIndex', $section_partTreeIndex);
                add_location_section_begin($section_r, $location, $edition_info, $eid);
            }
        }
        $section_part_count = $section_r->countValues('bdo:hasPart');
        $section_r->addResource('bdo:hasPart', $part_r->getUri());
        $part_r->addResource('bdo:partType', 'bdr:PartTypeText');
        $part_r->addLiteral('bdo:partIndex', $section_part_count+1);
        $part_partTreeIndex = $section_partTreeIndex.'.'.sprintf("%02d", $section_part_count+1);
        if ($thisPartTreeIndex != null) {
            $part_partTreeIndex = $thisPartTreeIndex;
        }
        $part_r->addLiteral('bdo:partTreeIndex', $part_partTreeIndex);
        if ($hassections) {
            $part_r->addResource('bdo:partOf', $url_semantic_section);
        } else {
            $part_r->addResource('bdo:partOf', $section_r);
        }
        $part_r->addResource('bdo:inRootInstance', $url_broader_edition);
        add_location_simple($part_r, $location, $edition_info, $eid);
        //add_location($part_r, $location, $edition_info['confinfo']['volumeMap']);
        // foreach ($item->bampo as $bampo) {
        //     $location = get_bampo_loc($bampo->p->__toString(), $fileName, 'rkts_'.$rktsid);
        //     $location['section'] = $current_section;
        //     if (empty($location['volname']))
        //         $location['volname'] = $bvolname;
        // }
        $chapnum = 0;
        $bvolname = '';
        if ($fileName != 'chemdo' && $fileName != 'chemdot')
            $bvolname = $location['bvolname'];
        foreach ($item->chap as $chap) { // iterating on chapters
            if (!$config['migrateChapters']) break;
            $chaptitle = $chap->__toString();
            if (empty($chaptitle))
                continue;
            $chapnum += 1;
            $partnum += 1;
            $chap_url = id_to_url_edition_text_chapter($eid, $rktsid, $chapnum, $config, $partnum, $bdrc);
            $graph_chap = new EasyRdf_Graph();
            $chap_r = $graph_chap->resource($chap_url);
            $chap_r->addResource('rdf:type', 'bdo:Instance');
            $chap_r->addResource('bdo:partType', 'bdr:PartTypeChapter');
            $chap_r->addResource('bdo:partOf', $url_part);
            $chap_r->addResource('bdo:inRootInstance', $url_broader_edition);
            $chap_r->addLiteral('bdo:partIndex', $chapnum);
            $chap_r->addLiteral('bdo:partTreeIndex', $part_partTreeIndex.'.'.sprintf("%02d", $chapnum));
            $part_r->addResource('bdo:hasPart', $chap_url);
            $dotpos = strpos($chaptitle, ". ");
            if ($dotpos < 5) {
                $chaptitle = substr($chaptitle, $dotpos+2);
            } else {
                //report_error($fileName, 'wrong chapter format', 'rkts_'.$rktsid, $chaptitle);
            }
            $lit = normalize_lit($chaptitle, 'bo-x-ewts', $bdrc);
            add_title($chap_r, 'Title', $lit);
            $chap_r->addLiteral('skos:prefLabel', $lit);
            $location = get_chap_loc($chap->p->__toString(), $fileName, 'rkts_'.$rktsid);
            if ($location) {
                $location['section'] = $current_section;
                if ($fileName == 'chamdo' && empty($location['bvolname']))
                    $location['bvolname'] = $bvolname;
                add_location_simple($chap_r, $location, $edition_info, $eid);
            }
            rdf_to_ttl($config, $graph_chap, $chap_r->localName(), $bdrc);
            if (!$bdrc)
                add_graph_to_global($graph_chap, $chap_r->localName(), $global_graph_fd);
        }
        $subitempartnum = 0;
        foreach ($item->subitem as $subitem) { // iterating on chapters
            $subitemlastloc = null;
            $subitempartnum += 1;
            $partTreeIndex = $part_partTreeIndex.'.'.sprintf("%02d", $subitempartnum);
            list($partnum, $subitemlastloc) = edition_item_to_ttl($config, $subitem, $global_graph_fd, $edition_info, $fileName, $subitempartnum, $subitemlastloc, $part_r, $eid, $bdrc, $edition, $url_part, $partTreeIndex);
        }
    } else { # couldn't read loc
        if ($section_r == null) {
            print("no section_r for ".$part_r);
        } else {
            $section_part_count = $section_r->countValues('bdo:hasPart');
            $section_r->addResource('bdo:hasPart', $part_r->getUri());
            $part_r->addResource('bdo:partType', 'bdr:PartTypeText');
            $part_r->addLiteral('bdo:partIndex', $section_part_count+1);
            $part_r->addResource('bdo:partOf', $section_r);
            $part_r->addResource('bdo:inRootInstance', $url_broader_edition);
        }
    }
    foreach ($item->note as $note) { // iterating on chapters
        if ($note == "")
            continue;
        $noteUri = bnode_url("NT", $part_r, $part_r, $note);
        $noteNode = $part_r->getGraph()->resource($noteUri);
        $part_r->addResource('bdo:note', $noteNode);
        $noteNode->add('bdo:noteText', $note);
        $noteNode->addResource('rdf:type', 'bdo:Note');
    }
    add_log_entry($part_r);
    rdf_to_ttl($config, $graph_part, $part_r->localName(), $bdrc);
    if (!$bdrc)
        add_graph_to_global($graph_part, $part_r->localName(), $global_graph_fd);
    return array($partnum, $location) ;
}

function get_url_section_part($sectionName, $editionVolumeMap, $eid, $config, $bdrc=False, $sectionIdxInOutline=1) {
    foreach ($editionVolumeMap as $sectionIdx => $sectionArr) {
        if ($sectionArr['name'] == $sectionName) {
            return id_to_url_edition_section_part($eid, $config, $sectionIdx+1, $sectionIdx+1, $bdrc);
        }
    }
    return id_to_url_edition_section_part($eid, $config, $sectionIdxInOutline, $sectionIdxInOutline, $bdrc);
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
        $sectionUrl = get_url_for_vol_section($editionId, $sectionIdx+1, $config, $bdrc);
        $semantic_section_url = id_to_url_edition_section_part($eid, $config, $sectionIdx+1, $sectionIdx+1, $bdrc);
        $edition_r->addResource('bdo:hasPart', $semantic_section_url);
        // if (!isset($editionVolumeMap['volnumInfo'])) {
        //     $editionVolumeMap['volnumInfo'] = [];
        //     $editionVolumeMap['volnumInfo'][0] = null;
        // }
        // $sectionName = $sectionArr['name'];
        // if (!isset($sectionArr['name'])) {
        //     print_r($sectionArr);
        // }

        // $sectionArr['url'] = $sectionUrl;
        // if (!isset($sectionArr['namesUrlMap'])) {
        //     $sectionArr['namesUrlMap'] = [];
        // }
        // $namesUrlMap = &$sectionArr['namesUrlMap'];
        // $edition_r->addResource('bdo:hasVolumeSection', $sectionUrl);
        // $graph_section = new EasyRdf_Graph();
        // $section_r = $graph_section->resource($sectionUrl);
        // $section_r->add('rdfs:label', $sectionArr['name'], "bo-x-ewts");
        // $section_r->add('bdo:seqNum', $sectionIdx+1);
        // foreach($sectionArr['volumes'] as $volumeIdx => $volumeName) {
        //     $curVolNum += 1;
        //     $volumeUrl = get_url_for_vol($editionId, $curVolNum, $config, $bdrc);
        //     $section_r->add('bdo:VolumeSectionHasVolume', $volumeUrl);
        //     $namesUrlMap[$volumeName] = $volumeUrl;
        //     $editionVolumeMap['volnumInfo'][$curVolNum] = ['url' => $volumeUrl, 'sectionName' => $sectionName];
        //     $graph_volume = new EasyRdf_Graph();
        //     $volume_r = $graph_volume->resource($volumeUrl);
        //     $volume_r->add('bdo:seqNum', $volumeIdx+1);
        //     $volume_r->add('bdo:VolumeSeqNumInWork', $curVolNum);
        //     $volume_r->add('rdfs:label', $volumeName, "bo-x-ewts");
        //     rdf_to_ttl($config, $graph_volume, $volume_r->localName(), $bdrc);
        //     if (!$bdrc)
        //         add_graph_to_global($graph_volume, $volume_r->localName(), $global_graph_fd);
        // }
        // rdf_to_ttl($config, $graph_section, $section_r->localName(), $bdrc);
        // if (!$bdrc)
        //     add_graph_to_global($graph_section, $section_r->localName(), $global_graph_fd);
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

function write_edition_ttl($edition_r, $config, &$edition_info, $global_graph_fd, $xml, $eid=null, $bdrc=False) {
    $graph_edition = $edition_r->getGraph();
    $edition_r->addResource('rdf:type', 'bdo:Instance');
    //$edition_name = $xml->name->__toString();
    //$edition_name .= " ".($tengyur ? "Tengyur" : "Kangyur");
    //$edition_r->addLiteral('skos:prefLabel', $edition_name, 'en');
    $edition_r->addResource('bdo:script', $edition_info['confinfo']['script']);
    $edition_r->addResource('bdo:printMethod', $edition_info['confinfo']['printType']);
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

function editions_to_ttl($config, $xml, $global_graph_fd, $fileName, $bdrc=False, $edition="K") {
    if ($bdrc) {
        foreach ($config[$fileName]['RID'] as $rid) {
            edition_to_ttl($config, $xml, $global_graph_fd, $fileName, $rid, $bdrc, $edition);
        }
    } else {
        edition_to_ttl($config, $xml, $global_graph_fd, $fileName, null, $bdrc, $edition);
    }
}

function edition_to_ttl($config, $xml, $global_graph_fd, $fileName, $eid=null, $bdrc=False, $edition="K") {
    $edition_info = get_base_edition_info($config, $xml, $fileName);
    $eid = $bdrc ? $eid : $edition_info['confinfo']['EID'] ;
    $lastpartnum = $bdrc ? count($edition_info['confinfo']['volumeMap']) : 0;
    $hassections = array_key_exists("hassections", $edition_info['confinfo']) ? !!boolval($edition_info['confinfo']["hassections"]) : true ;
    $graph_edition = new EasyRdf_Graph();
    $eid = $bdrc ? $eid : $edition_info['confinfo']['EID'] ;
    $url_edition = id_to_url_edition($eid, $config, $bdrc);
    $edition_r = $graph_edition->resource($url_edition);
    $lastloc = null;
    $section_r = null;
    if (!$hassections) {
        $section_r = $edition_r;
    }
    foreach($xml->item as $item) {
        list($lastpartnum, $lastloc) = edition_item_to_ttl($config, $item, $global_graph_fd, $edition_info, $fileName, $lastpartnum, $lastloc, $section_r, $eid, $bdrc, $edition, null, null, $hassections);
        //return;
    }
    if ($hassections) {
        if (!$section_r) {
            print("null section_r");
        } else {
        add_location_section_end($section_r, $lastloc, $edition_info, $eid);
        rdf_to_ttl($config, $section_r->getGraph(), $section_r->localName(), $bdrc);
        if (!$bdrc)
            add_graph_to_global($section_r->getGraph(), $section_r->localName(), $global_graph_fd);
        }
    }
    write_edition_ttl($edition_r, $config, $edition_info, $global_graph_fd, $xml, $eid, $bdrc);
}
