<?php

// companion to migrate.php

require_once __DIR__ . '/vendor/autoload.php';

function catalogue_index_xml_to_rdf($index, $edition_info, $tengyur) {
    $edlen = strlen($edition_info['confinfo']['EID']);
    if ($tengyur)
        $edlen -= 1; // Tengyur editions end with an additional T
    $index = substr($index, $edlen);
    $index = str_replace(["(", ".", ","], "-", $index);
    $index = str_replace(")", "", $index);
    return $index;
}

function id_to_str($id) {
    $id_int = 0;
    if (ctype_digit($id)) {
        $id_int = intval($id);
        return sprintf("%04d", $id_int);
    } else {
        $intlen = strspn($id , "0123456789");
        $id_int = intval(substr($id, 0, $intlen));
        $id_int_str = sprintf("%04d", $id_int);
        return $id_int_str.substr($id, $intlen);
    }
}

function chapnum_to_str($id) {
    $id_int = intval($id);
    return sprintf("%03d", $id_int);
}

// 7a -> 7A
function rdf_ci_to_url($id) {
    $id_int = 0;
    if (ctype_digit($id)) {
        $id_int = intval($id);
        return sprintf("%04d", $id_int);
    } else {
        $intlen = strspn($id , "0123456789");
        $id_int = intval(substr($id, 0, $intlen));
        $id_int_str = sprintf("%04d", $id_int);
        return $id_int_str.strtoupper(substr($id, $intlen));
    }
}

function id_to_url_abstract($rktsid, $config, $bdrc=False, $tengyur=False) {
    global $gl_rkts_abstract;
    $idwithletter = ($tengyur ? 'T' : 'K').$rktsid;
    // if the same text has different translations, we attach all the translations to the same abstract text:
    if ($bdrc && isset($config['SameTextDifferentTranslation'][$idwithletter])) {
        $idwithletter = $config['SameTextDifferentTranslation'][$idwithletter];
        $rktsid = substr($idwithletter, 1);
        $tengyur = ($idwithletter[0] == 'T');
    }
    if ($bdrc && isset($gl_rkts_abstract[$idwithletter])) {
        return 'http://purl.bdrc.io/resource/'.$gl_rkts_abstract[$idwithletter];
    }
    $paramName = ($bdrc ? 'bdrc' : 'rKTs').'AbstractUrlFormat'.($tengyur ? 'Ten' : 'Kan');
    return str_replace('%GID', id_to_str($rktsid), $config[$paramName]);
}

function id_to_url_expression($rktsid, $config, $bdrc=False, $tengyur=False) {
    // for bdrc, when the exact same text is in both Kangyur and Tengyur, we just take the Tengyur ID for the URL
    if ($bdrc && !$tengyur && array_key_exists(intval($rktsid), $config['KTMapping'])) {
        $rktsid = $config['KTMapping'][intval($rktsid)];
        $tengyur = true;
    }
    $paramName = ($bdrc ? 'bdrc' : 'rKTs').'ExpressionUrlFormat'.($tengyur ? 'Ten' : 'Kan');
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
        return str_replace('%GID', rdf_ci_to_url($ci), $estr);
        //return str_replace('%PNUM', id_to_str($partnum), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['rKTsTextUrlFormat']);
        return str_replace('%GID', rdf_ci_to_url($ci), $estr);
    }
}

function id_to_url_edition_section_part($eid, $config, $partnum, $sectionNum, $bdrc=False) {
    if ($bdrc) {
        $estr = str_replace('%EID', $eid, $config['bdrcSectionUrlFormat']);
        return str_replace('%SNUM', id_to_str($partnum), $estr);
    } else {
        $estr = str_replace('%EID', $eid, $config['bdrcSectionUrlFormat']);
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

function url_for_vol_name($section, $volumeName, $volumeMapWithUrls) {
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

function url_for_vol_num($volumenum, $volumeMapWithUrls) {
    $res = $volumeMapWithUrls['volnumInfo'][intval($volumenum)]['url'];
    if ($res ==null) {
        print("cannot find url for volume ".$volumenum);
    }
    return $res;
}

function folio_side_to_pagenum($folionum, $side, $volnum, $edition_info) {
    if ($side == null || empty($side))
        return $folionum;
    $toadd = 0;
    if ($side == 'b')
        $toadd=1;
    $volnum = intval($volnum);
    $folionum = intval($folionum);
    $onea = $edition_info['confinfo']['volumeBdrcPageFirstFolioDefault'];
    if (in_array('volumeBdrcPageFirstFolio', $edition_info['confinfo'])) {
        $map = $edition_info['confinfo']['volumeBdrcPageFirstFolio'];
        if (in_array($volnum, $map)) {
            $onea = intval($map[$volnum]);
        }
    }
    $imagenum = 2*($folionum-1)+$onea+$toadd;
    return $imagenum;
}

function add_location_simple($resource, $location, $edition_info, $eid) {
    if (!isset($location['bvolnum']))
        return;
    $locationNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workLocation', $locationNode);
    $locationNode->add('bdo:workLocationVolume', intval($location['bvolnum']));
    $locationNode->addResource('bdo:workLocationWork', "http://purl.bdrc.io/resource/".$eid);
    $evolnum = $location['bvolnum'];
    if (isset($location['evolnum']) && !empty($location['evolnum']) && $location['bvolnum'] != $location['evolnum']) {
        $evolnum = $location['evolnum'];
        $locationNode->add('bdo:workLocationEndVolume', intval($location['evolnum']));
    }
    if (isset($location['blinenum'])) {
        $locationNode->add('bdo:workLocationLine', $location['blinenum']);
    }
    if (isset($location['elinenum'])) {
        $locationNode->add('bdo:workLocationEndLine', $location['elinenum']);
    }
    $bpagenum = folio_side_to_pagenum($location['bpagenum'], $location['bpageside'], $location['bvolnum'], $edition_info);
    $locationNode->add('bdo:workLocationPage', $bpagenum);
    if (isset($location['epagenum'])) {
        $epagenum = folio_side_to_pagenum($location['epagenum'], $location['epageside'], $evolnum, $edition_info);
        $locationNode->add('bdo:workLocationEndPage', $epagenum);
    }
}

// used when adding a location to a section
// location is the location of the first text of the section
function add_location_section_begin($resource, $location, $edition_info, $eid) {
    if (!isset($location['bvolnum'])) {
        report_error($eid, 'invalid_sec_loc', $resource->getUri(), 'cannot indicate begin location');
        return;
    }
    $locationNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workLocation', $locationNode);
    $locationNode->add('bdo:workLocationVolume', intval($location['bvolnum']));
    $locationNode->addResource('bdo:workLocationWork', "http://purl.bdrc.io/resource/".$eid);
    $bpagenum = folio_side_to_pagenum($location['bpagenum'], $location['bpageside'], $location['bvolnum'], $edition_info);
    $locationNode->add('bdo:workLocationPage', $bpagenum);
}

// same as before except that here location is the location of the last text of the section
// location is the location of the first text of the section
function add_location_section_end($resource, $location, $edition_info, $eid) {
    if (!isset($location['bvolnum']) && !isset($location['evolnum'])) {
        report_error($eid, 'invalid_sec_loc', $resource->getUri(), 'cannot indicate end location');
        return;
    }
    $locationNode = $resource->getResource('bdo:workLocation');
    if ($locationNode == null) {
        report_error($eid, 'invalid_sec_loc', $resource->getUri(), 'no indication of beginning location');
        return;
    }
    $evolnum = $location['bvolnum'];
    if (isset($location['evolnum']) && !empty($location['evolnum'])) {
        $locationNode->add('bdo:workLocationEndVolume', intval($location['evolnum']));
        $evolnum = $location['evolnum'];
    } else {
        $locationNode->add('bdo:workLocationEndVolume', intval($location['bvolnum']));
    }
    if (isset($location['epagenum'])) {
        $epagenum = folio_side_to_pagenum($location['epagenum'], $location['epageside'], $evolnum, $edition_info);
        $locationNode->add('bdo:workLocationEndPage', $epagenum);
    }
}

function add_location($resource, $location, $volumeMapWithUrls) {
    $locationNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workLocation', $locationNode);
    if (isset($location['bvolnum'])) {
        // chemdo style
        $locationNode->addResource('bdo:workLocationSchema', 'bdr:PageNumberingScheme');
        $locationNode->add('bdo:workLocationPage', $location['bpagenum']);
        if (isset($location['epagenum'])) {
            $locationNode->add('bdo:workLocationEndPage', $location['epagenum']);
        }
        if (isset($location['evolnum']) && !empty($location['evolnum']) && $location['bvolnum'] != $location['evolnum']) {
            $locationNode->addResource('bdo:workLocationVolumeEnd', url_for_vol_num($location['evolnum'], $volumeMapWithUrls));
        }
        $volurl = url_for_vol_num($location['bvolnum'], $volumeMapWithUrls);
        if ($volurl)
            $locationNode->addResource('bdo:workLocationVolume', $volurl);
        else
            print_r($location);
        return;
    }
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
    $locationNode->addResource('bdo:workLocationVolume', url_for_vol_name($section, $location['bvolname'], $volumeMapWithUrls));
    if (isset($location['evolname']) && !empty($location['evolname']) && $location['bvolname'] != $location['evolname']) {
        $locationNode->addResource('bdo:workLocationVolumeEnd', url_for_vol_name($section, $location['evolname'], $volumeMapWithUrls));
    }
}

function add_location_to_section($resource, $sectionName, $volumeMapWithUrls) {
    $locationNode = $resource->getGraph()->newBNode();
    $resource->addResource('bdo:workLocation', $locationNode);
    foreach ($volumeMapWithUrls as $sectionIdx => $sectionArr) {
        if ($sectionArr['name'] == $section) {
            $bvolumeName = $sectionArr['volumes'][0];
            $evolumeName = $sectionArr['volumes'][count($sectionArr['volumes'])-1];
            $locationNode->addResource('bdo:workLocationVolume', url_for_vol_name($sectionName, $bvolname, $volumeMapWithUrls));
            if ($bvolumeName != $evolumename) {
                $locationNode->addResource('bdo:workLocationVolumeEnd', url_for_vol_name($section, $evolumename, $volumeMapWithUrls));
            }
            break;
        }
    }
}

function add_title($resource, $type, $lit, $sameTypeNode=null) {
    $titleNode = $sameTypeNode;
    if ($titleNode == null) {
        $titleNode = $resource->getGraph()->newBNode();
        $resource->addResource('bdo:workTitle', $titleNode);
    }
    $titleNode->add('rdfs:label', $lit);
    $titleNode->addResource('rdf:type', 'bdo:'.$type);
    return $titleNode;
}

function report_error($file, $type, $id, $message) {
    error_log($file.':'.$id.':'.$type.': '.$message);
}

$allowed_vol_letters = ["ka", "kha", "ga", "nga", "ca", "cha", "ja", "nya", "ta", "tha", "da", "na", "pa", "pha", "ba", "ma", "a", "wa", "za", "zha", "'a", "dza", "tsha", "tsa", "ya", "ra", "sha", "ha", "aM", "aH", "e", "waM", "sa", "la", "shrI", "ki", "khi", "gi", "ngi", "ci", "chi", "ji", "nyi", "ti", "thi", "di", "ni", "pi", "phi", "bi", "mi", "tsi", "tshi", "dzi", "wi", "zhi", "zi", "'i", "yi", "ri", "li", "shi", "si", "i", "ku", "khu", "gu", "ngu", "cu", "chu", "ju", "nyu", "tu", "thu", "du", "nu", "pu", "phu", "bu", "mu", "tsu", "tshu", "hi", "dzu", "wu", "zhu", "'u", "ru", "lu", "shu", "su", "hu", "u", "ke", "ge", "nge", "ce", "che", "je", "te", "de", "pe", "phe", "tshe", "dze", "we", "zhe", "ze", "ye", "re", "le", "she", "se", "he", "ko", "ngo", "co", "jo", "nyo", "to", "tho", "no", "po", "zu", "yu", "A", "khe", "nye", "the", "ne", "tse", "'e", "kho", "go", "cho", "do", "pho", "bo", "mo" ];

$pattern_small_loc = '/(?P<pagenum>\d+)(?P<ab>[ab])(?P<linenum>\d+)?/';
$pattern_loc = '/^(?P<section>[^,]+), (?P<bvolname>[^ ]+) (?P<bpageline>[0-9ab]+)(?:\-((?P<evolname>[^ ]+) )?(?P<epageline>[0-9ab]+))?(?: \(vol\. (?P<bvolnum>\d+)(?:-(?P<evolnum>\d+))?)?/';
$pattern_bampo_chap_loc = '/^(?:(?P<bvolname>[^ ]+) )?(?P<bpageline>[0-9ab]+)(?:\-((?P<evolname>[^ ]+) )?(?P<epageline>[0-9ab]+))?$/';

$pattern_loc_simple = '/^(?P<bvolnum>\d+)\.(?P<bpagenum>\d+) ?- ?(?P<evolnum>\d+)\.(?P<epagenum>\d+)$/';
$pattern_loc_simple_small = '/^(?:(?P<bvolnum>\d+)\.)?(?P<bpagenum>\d+)(?:-(?:(?P<evolnum>\d+)\.)?(?P<epagenum>\d+))?$/';

$volumeMap = [];
$currentSection = null;

function get_text_loc($str, $fileName, $id) {
    global $allowed_vol_letters, $pattern_loc, $pattern_small_loc, $pattern_loc_simple;
    // ex: 'dul ba, ka 1b1-nga 302a5 (vol. 1-4)
    $matches = [];
    if ($fileName == "chemdo") {
        preg_match($pattern_loc_simple, $str, $matches);    
    } else {
        preg_match($pattern_loc, $str, $matches);
    }
    if (empty($matches)) {
        report_error($fileName, 'invalid_loc', $id, 'cannot understand string "'.$str.'"');
        return [];
    }
    if ($fileName == "chemdo")
        return $matches;
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
    global $allowed_vol_letters, $pattern_bampo_chap_loc, $pattern_small_loc, $pattern_loc_simple_small;
    // ex: 'dul ba, ka 1b1-nga 302a5 (vol. 1-4)
    $matches = [];
    if ($fileName == "chemdo") {
        preg_match($pattern_loc_simple_small, $str, $matches);
    } else {
        preg_match($pattern_bampo_chap_loc, $str, $matches);
    }
    if (empty($matches)) {
        report_error($fileName, 'invalid_bampo_loc', $id, 'cannot understand bampo->p string "'.$str.'"');
        return [];
    }
    if ($fileName != "chemdo" && !empty($matches['bvolname']) && !in_array($matches['bvolname'], $allowed_vol_letters)) {
        report_error($fileName, 'invalid_loc', $id, 'in "'.$str.'" (bampo loc), invalid volume number "'.$matches['b volname'].'"');
    }
    if ($fileName != "chemdo")
        set_pageline($matches, $str, $fileName, $id);
    return $matches;
}

function get_chap_loc($str, $fileName, $id) {
    global $allowed_vol_letters, $pattern_bampo_chap_loc, $pattern_small_loc, $pattern_loc_simple_small;
    // ex: 'dul ba, ka 1b1-nga 302a5 (vol. 1-4)
    $matches = [];
    if ($fileName == "chemdo") {
        preg_match($pattern_loc_simple_small, $str, $matches);    
    } else {
        preg_match($pattern_bampo_chap_loc, $str, $matches);
    }
    if (empty($matches)) {
        report_error($fileName, 'invalid_chap_loc', $id, 'cannot understand chap->p string "'.$str.'"');
        return [];
    }
    if ($fileName != "chemdo" && !empty($matches['bvolname']) && !in_array($matches['bvolname'], $allowed_vol_letters)) {
        report_error($fileName, 'invalid_loc', $id, 'in "'.$str.'" (chap loc), invalid volume number "'.$matches['bvolname'].'"');
    }
    if ($fileName != "chemdo")
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
    'tib' => 'bo-x-ewts',
    'sktuni' => 'sa-Deva',
    'sanskrit' => 'sa-x-iast',
    'skt' => 'sa-x-iast',
    'mongolian' => 'cmg-x-poppe-simpl',
    'mng' => 'cmg-x-poppe-simpl',
    'mnguni' => 'cmg-Mong',
    'skttrans' => 'sa-x-ewts'
];

function add_shad($tibstr) {
    // we suppose that there is no space at the end
    // copied from 
    $tibstrlen = strlen($tibstr);
    if ($tibstrlen < 2)
        return $tibstr;
    $last = mb_substr($tibstr, -1);
    if ($last == 'a' || $last == 'i' || $last == 'e' || $last == 'o')
        $last = mb_substr($tibstr, -2, 1);
    if ($tibstrlen > 2 && $last == 'g' && mb_substr($tibstr, -3, 1) == 'n')
        return $tibstr." /";
    if ($last == 'g' || $last == 'k' || ($tibstrlen == 3 && $last == 'h' && mb_substr($tibstr, -3, 1) == 's') || ($tibstrlen > 3 && $last == 'h' && mb_substr($tibstr, -3, 1) == 's' && mb_substr($tibstr, -4, 1) != 't'))
        return $tibstr;
    if ($last < 'A' || $last > 'z' || ($last > 'Z' && $last < 'a'))  // string doesn't end with tibetan letter
        return $tibstr;
    return $tibstr."/";
}

// print(add_shad("a ga")."\n");
// print(add_shad("a sho")."\n");
// print(add_shad("a ki")."\n");
// print(add_shad("a gu")."\n");
// print(add_shad("a nga")."\n");
// print(add_shad("a ngu")."\n");
// print(add_shad("a ngi")."\n");
// print(add_shad("a tsho")."\n");

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
    if ($bdrc && $langtag == "bo-x-ewts") {
        $title = add_shad($title);
    }
    return EasyRdf_Literal::create($title, $langtag);
}

function add_log_entry($resource) {
    $logNode = $resource->getGraph()->newBNode();
    $resource->addResource('adm:logEntry', $logNode);
    $logNode->addLiteral('adm:logDate', new DateTime());
    $logNode->addLiteral('adm:logMessage', 'migrated from rKTs data', 'en');
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

function get_rkts_props() {
    $res = [];
    $filename = "rkts-actors.csv";
    $handle = fopen($filename, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $triples = explode(',', $line);
            if (!isset($res[$triples[0]]))
                $res[$triples[0]] = [];
            if (!isset($res[$triples[0]][$triples[1]]))
                $res[$triples[0]][$triples[1]] = [];
            $res[$triples[0]][$triples[1]][] = $triples[2];
        }
        fclose($handle);
    } else {
        print("error opening ".$filename);
    }
    return $res; 
}

function get_abstract_mapping() {
    $res = [];
    $filename = "abstract-rkts.csv";
    $handle = fopen($filename, "r");
    if ($handle) {
        while (($line = fgets($handle)) !== false) {
            $map = explode(',', $line);
            if (!empty($map[1]) && strpos($map[1], '?') === false) {
                $res[trim($map[1])] = trim($map[0]);
            }
        }
        fclose($handle);
    } else {
        print("error opening ".$filename);
    }
    return $res; 
}
