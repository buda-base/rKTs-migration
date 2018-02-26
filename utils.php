<?php

// companion to migrate.php

require_once __DIR__ . '/vendor/autoload.php';

function catalogue_index_xml_to_rdf($index) {
    return substr($index, 1);
}

function id_to_str($id) {
    $id_int = intval($id);
    return sprintf("%05d", $id_int);
}

function chapnum_to_str($id) {
    $id_int = intval($id);
    return sprintf("%03d", $id_int);
}

// 7a -> 7A
function rdf_ci_to_url($id) {
    if (substr($id, -1) == 'a') {
        $id_int = intval(substr($id, 0, -1));
        return sprintf("%04d", $id_int).'A';
    } 
    $id_int = intval($id);
    return sprintf("%04d", $id_int);
}

function id_to_url_abstract($rktsid, $config, $bdrc=False) {
    $paramName = ($bdrc ? 'bdrc' : 'rKTs').'AbstractUrlFormat';
    return str_replace('%GID', id_to_str($rktsid), $config[$paramName]);
}

function id_to_url_expression($rktsid, $config, $bdrc=False) {
    $paramName = ($bdrc ? 'bdrc' : 'rKTs').'ExpressionUrlFormat';
    return str_replace('%GID', id_to_str($rktsid), $config[$paramName]);
}

function id_to_url_edition($eid, $config, $bdrc=False) {
    $paramName = ($bdrc ? 'bdrc' : 'rKTs').'EditionUrlFormat';
    return str_replace('%EID', $eid, $config[$paramName]);
}

function id_to_url_edition_text($eid, $ci, $config, $partnum, $bdrc=False) {
    // ci is catalogue index, should be unique in the edition
    if ($bdrc) {
        $estr = str_replace('%EID', $eid, $config['bdrcTextUrlFormat']);
        return str_replace('%PNUM', id_to_str($partnum), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['rKTsTextUrlFormat']);
        return str_replace('%GID', rdf_ci_to_url($ci), $estr);
    }
}

function id_to_url_edition_section_part($eid, $config, $partnum, $sectionNum, $bdrc=False) {
    if ($bdrc) {
        $estr = str_replace('%EID', $eid, $config['bdrcTextUrlFormat']);
        return str_replace('%PNUM', id_to_str($partnum), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['rKTsSectionPartUrlFormat']);
        return str_replace('%SNUM', chapnum_to_str($sectionNum), $estr);
    }
}

function id_to_url_edition_text_chapter($eid, $ci, $chapnum, $config, $partnum, $bdrc=False) {
    if ($bdrc) {
        $estr = str_replace('%EID', $eid, $config['bdrcTextUrlFormat']);
        return str_replace('%PNUM', id_to_str($partnum), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['rKTsChapterUrlFormat']);
        $txtstr = str_replace('%GID', rdf_ci_to_url($ci), $estr);
        return str_replace('%CID', chapnum_to_str($chapnum), $txtstr);
    }
}

function get_url_for_vol($eid, $volumenumber, $config, $bdrc=False) {
    if ($bdrc) {
        $estr = str_replace('%EID', $eid, $config['bdrcVolumeUrlFormat']);
        return str_replace('%VNUM', chapnum_to_str($volumenumber), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['rKTsVolumeUrlFormat']);
        return str_replace('%VNUM', chapnum_to_str($volumenumber), $estr);
    }
}

function get_url_for_vol_section($eid, $volumesectionnumber, $config, $bdrc=False) {
    if ($bdrc) {
        $estr = str_replace('%EID', $eid, $config['bdrcVolumeSectionUrlFormat']);
        return str_replace('%VSNUM', chapnum_to_str($volumesectionnumber), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['rKTsVolumeSectionUrlFormat']);
        return str_replace('%VSNUM', chapnum_to_str($volumesectionnumber), $estr);
    }
}

function url_for_vol($section, $volumeName, $volumeMapWithUrls) {
    foreach ($volumeMapWithUrls as $sectionIdx => $sectionArr) {
        if ($sectionArr['name'] == $section) {
            if (!isset($sectionArr['namesUrlMap'][$volumeName])) {
                print("cannot find ".$volumeName." in ".$sectionArr['name']."\n");
            }
            return $sectionArr['namesUrlMap'][$volumeName];
        }
    }
    return null;
}

function add_location($resource, $location, $volumeMapWithUrls) {
    $locationNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workLocation', $locationNode);
    $locationNode->add('bdo:workLocationFolio', $location['bpagenum']);
    $locationNode->add('bdo:workLocationLine', $location['blinenum']);
    $locationNode->add('bdo:workLocationSide', $location['bpageside']);
    if (isset($location['epagenum'])) {
        $locationNode->add('bdo:workLocationEndFolio', $location['epagenum']);
        $locationNode->add('bdo:workLocationEndSide', $location['epageside']);
    }
    if (isset($location['elinenum'])) {
        $locationNode->add('bdo:workLocationEndLine', $location['elinenum']);
    }
    $locationNode->addResource('bdo:workLocationSchema', 'bdr:FolioNumberingScheme');
    $section = $location['section'];
    $locationNode->addResource('bdo:workLocationVolume', url_for_vol($section, $location['bvolname'], $volumeMapWithUrls));
    if (isset($location['evolname']) && !empty($location['evolname']) && $location['bvolname'] != $location['evolname']) {
        $locationNode->addResource('bdo:workLocationVolumeEnd', url_for_vol($section, $location['evolname'], $volumeMapWithUrls));
    }
}

function add_location_to_section($resource, $sectionName, $volumeMapWithUrls) {
    $locationNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workLocation', $locationNode);
    foreach ($volumeMapWithUrls as $sectionIdx => $sectionArr) {
        if ($sectionArr['name'] == $section) {
            $bvolumeName = $sectionArr['volumes'][0];
            $evolumeName = $sectionArr['volumes'][count($sectionArr['volumes'])-1];
            $locationNode->addResource('bdo:workLocationVolume', url_for_vol($sectionName, $bvolname, $volumeMapWithUrls));
            if ($bvolumeName != $evolumename) {
                $locationNode->addResource('bdo:workLocationVolumeEnd', url_for_vol($section, $evolumename, $volumeMapWithUrls));
            }
            break;
        }
    }
}

function add_title($resource, $type, $lit) {
    $titleNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workTitle', $titleNode);
    $titleNode->add('rdfs:label', $lit);
    $titleNode->addResource('rdf:type', 'bdo:'.$type);
}

function report_error($file, $type, $id, $message) {
    error_log($file.':'.$id.':'.$type.': '.$message);
}

$allowed_vol_letters = ["ka", "kha", "ga", "nga", "ca", "cha", "ja", "nya", "ta", "tha", "da", "na", "pa", "pha", "ba", "ma", "a", "wa", "za", "zha", "'a", "dza", "tsha", "tsa", "ya", "ra", "sha", "ha", "aM", "aH", "e", "waM", "sa", "la", "shrI", "ki", "khi", "gi", "ngi", "ci", "chi", "ji" ];

$pattern_small_loc = '/(?P<pagenum>\d+)(?P<ab>[ab])(?P<linenum>\d+)?/';
$pattern_loc = '/^(?P<section>[^,]+), (?P<bvolname>[^ ]+) (?P<bpageline>[0-9ab]+)(?:\-((?P<evolname>[^ ]+) )?(?P<epageline>[0-9ab]+))? \(vol\. (?P<bvolnum>\d+)(?:-(?P<evolnum>\d+))?\)$/';
$pattern_bampo_chap_loc = '/^(?:(?P<bvolname>[^ ]+) )?(?P<bpageline>[0-9ab]+)(?:\-((?P<evolname>[^ ]+) )?(?P<epageline>[0-9ab]+))?$/';

$volumeMap = [];
$currentSection = null;

function get_text_loc($str, $fileName, $id) {
    global $allowed_vol_letters, $pattern_loc, $pattern_small_loc;
    // ex: 'dul ba, ka 1b1-nga 302a5 (vol. 1-4)
    $matches = [];
    preg_match($pattern_loc, $str, $matches);
    if (empty($matches)) {
        report_error($fileName, 'invalid_loc', $id, 'cannot understand string "'.$str.'"');
        return [];
    }
    if (!in_array($matches['bvolname'], $allowed_vol_letters)) {
        report_error($fileName, 'invalid_loc', $id, 'in "'.$str.'", invalid volume number "'.$matches['bvolname'].'"');
    }
    if (!empty($matches['evolname']) && !in_array($matches['evolname'], $allowed_vol_letters)) {
        report_error($fileName, 'invalid_loc', $id, 'in "'.$str.'", invalid volume number "'.$matches['evolname'].'"');
    }
    set_pageline($matches, $str, $fileName, $id);
    return $matches;
}

function set_pageline(&$matches, $str, $fileName, $id) {
    global $pattern_small_loc, $volumeMap, $currentSection;
    if (!empty($matches['bvolname'])) {
        $volName = $matches['bvolname'];
        if (!empty($matches['section'])) {
            $currentSection = $matches['section'];
            if (!isset($volumeMap[$currentSection]))
                $volumeMap[$currentSection] = [];
        }
        if (!in_array($volName, $volumeMap[$currentSection])) {
            $volumeMap[$currentSection][] = $volName;
        }
    }
    $matches_bpageline = [];
    preg_match($pattern_small_loc, $matches['bpageline'], $matches_bpageline);
    if (empty($matches_bpageline)) {
        report_error($fileName, 'invalid_loc', $id, 'cannot understand pagenum in string "'.$str.'"');
        return $matches;
    }
    $matches['bpagenum'] = intval($matches_bpageline['pagenum']);
    $matches['bpageside'] = $matches_bpageline['ab'];
    if (isset($matches_bpageline['linenum']))
        $matches['blinenum'] = intval($matches_bpageline['linenum']);
    if (isset($matches['epageline']) && !empty($matches['epageline'])) {
        $matches_epageline = [];
        preg_match($pattern_small_loc, $matches['epageline'], $matches_epageline);
        if (empty($matches_epageline)) {
            report_error($fileName, 'invalid_loc', $id, 'cannot understand pagenum in string "'.$str.'"');
            return $matches;
        }
        $matches['epagenum'] = intval($matches_epageline['pagenum']);
        $matches['epageside'] = $matches_epageline['ab'];
        if (isset($matches_epageline['linenum']))
            $matches['elinenum'] = intval($matches_epageline['linenum']);
    }
}

function get_bampo_loc($str, $fileName, $id) {
    global $allowed_vol_letters, $pattern_bampo_chap_loc, $pattern_small_loc;
    // ex: 'dul ba, ka 1b1-nga 302a5 (vol. 1-4)
    $matches = [];
    preg_match($pattern_bampo_chap_loc, $str, $matches);
    if (empty($matches)) {
        report_error($fileName, 'invalid_bampo_loc', $id, 'cannot understand bampo->p string "'.$str.'"');
        return [];
    }
    if (!empty($matches['bvolname']) && !in_array($matches['bvolname'], $allowed_vol_letters)) {
        report_error($fileName, 'invalid_loc', $id, 'in "'.$str.'" (bampo loc), invalid volume number "'.$matches['b volname'].'"');
    }
    set_pageline($matches, $str, $fileName, $id);
    return $matches;
}

function get_chap_loc($str, $fileName, $id) {
    global $allowed_vol_letters, $pattern_bampo_chap_loc, $pattern_small_loc;
    // ex: 'dul ba, ka 1b1-nga 302a5 (vol. 1-4)
    $matches = [];
    preg_match($pattern_bampo_chap_loc, $str, $matches);
    if (empty($matches)) {
        report_error($fileName, 'invalid_chap_loc', $id, 'cannot understand chap->p string "'.$str.'"');
        return [];
    }
    if (!empty($matches['bvolname']) && !in_array($matches['bvolname'], $allowed_vol_letters)) {
        report_error($fileName, 'invalid_loc', $id, 'in "'.$str.'" (chap loc), invalid volume number "'.$matches['bvolname'].'"');
    }
    set_pageline($matches, $str, $fileName, $id);
    return $matches;
}

// print_r(get_bampo_loc("ga 107a7-116a5", "fileName", "id"));
// print_r(get_chap_loc("ga 107a7", "fileName", "id"));
// print_r(get_text_loc("'dul ba, ka 1b1-nga 302a5 (vol. 1-4)", "fileName", "id"));
// print_r(get_text_loc("gzugs, wam 245a4-247a7 (vol. 102)", "fileName", "id"));
//print_r(get_text_loc("rgyud, ja 39b7 (vol. 83)"));

function add_label($resource, $type, $lit) {
//    $resource
}

$name_to_bcp = [
    'tibetan' => 'bo-x-ewts',
    'sktuni' => 'sa-Deva',
    'sanskrit' => 'sa-x-iats',
    'mongolian' => 'cmg-x-poppe-simpl',
    'mnguni' => 'cmg-Mong',
    'skttrans' => 'sa-x-ewts'
];

function normalize_lit($title, $langtag, $bdrc=False) {
    // bdrc uses sa-x-iast for Sanskrit, and cmg-Mong for Mongolian (transliterations are sloppy it seems)
    // if ($bdrc && $langtag=="cmg-x-poppe-simpl") {
    //     $init=["c", "j", "sh", "g"];
    //     $repl=["č", "ǰ", "š", "γ"];
    //     $title=str_replace($init, $repl, $title);
    //     $langtag = "cmg-x-poppe";
    // }
    if ($bdrc && ($langtag == "cmg-x-poppe-simpl" || $langtag == "sa-Deva"))
        return null;
    return EasyRdf_Literal::create($title, $langtag);
}

function add_log_entry($resource) {
    // $logNode = $resource->getGraph()->newBNode();
    // $resource->addResource('adm:logEntry', $logNode);
    // $logNode->addLiteral('adm:logDate', new DateTime());
    // $logNode->addLiteral('adm:logMessage', 'migrated from xml', 'en');
}

require_once "Nquads.php";

$nquads = new Nquads();

function add_graph_to_global($graph, $localname, $global_fd) {
    global $nquads;
    $output = $nquads->serialise($graph);
    // this is really crappy but it seems there is no other option
    // in easyrdf...
    $output = str_replace('_:genid', '_:genid'.$localname, $output);
    fwrite($global_fd, $output);
}

$turtle = EasyRdf_Format::getFormat('turtle');

function rdf_to_ttl($config, $graph, $basename, $bdrc=False) {
    global $turtle;
    $output = $graph->serialise($turtle);
    $subdir = $bdrc ? 'bdrc' : 'rKTs' ;
    $filename = $config['opts']->getOption('output-dir').'/'.$subdir.'/'.$basename.'.ttl';
    file_put_contents($filename, $output);
}
