<?php

require_once "utils.php";

$xml = simplexml_load_file('rKTs/Tanjur/tanjurd.xml');

$tengyur = True;

// get_text_loc("rgyud 'grel, pu 317b7-320a5", "", "1");
// get_text_loc("'dul ba, ka 1b1-nga 302a5 (vol. 1-4)", "", "1");

$fd = fopen("/tmp/N-locations.csv", "w");

$fields = [
    "section",
    "nametib",
    "rktsnum",
    "catalognum",
    "bvolname",
    "bpagenum",
    "bpageside",
    "blinenum",
    "evolname",
    "epagenum",
    "epageside",
    "elinenum"
];

fwrite($fd, implode(",",$fields)."\n");

// print_r(get_text_loc('dul ba, tha 1b1-da 333a7 (vol. 10-11)', '', 0));
// return;

$previousevolnum = 0;
foreach($xml->item as $item) {
    if ($tengyur) {
        $rktsid = $item->rktst;
    } else {
        $rktsid = $item->rkts;
    }
    if ($rktsid == '-')
        return;
    $ci = substr($item->ref, 1);
    $locstr = $item->loc;
    if (!$locstr) continue;
    $loc = get_text_loc($locstr, "tanjurd", $rktsid);
    if (!$loc) continue;
    if (!isset($loc['bvolnum'])) {
        print("no bvolnum for ".$ci."\n");
    } else {
        $bvolnum = intval($loc['bvolnum']);
        $evolnum = $bvolnum;
        if (isset($loc['evolnum'])) {
            $evolnum = intval($loc['evolnum']);
        }
        if ($evolnum < $bvolnum) {
            print($ci.": evolnum < bvolnum\n");
        }
        if ($bvolnum < $previousevolnum) {
            print($ci.": bvolnum < previousevolnum\n");
        }
        $previousevolnum = $evolnum;
    }
    $itemfields = [
        $loc['section'],
        $item->tib,
        $rktsid,
        $ci,
        $loc['bvolname'],
        $loc['bpagenum'],
        $loc['bpageside'],
//        $loc['blinenum'],
        $loc['evolname'],
        $loc['epagenum'],
        $loc['epageside'],
 //       $loc['elinenum']
    ];
    fwrite($fd, implode(",",$itemfields)."\n");
}
 
fclose($fd);
