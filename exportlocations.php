<?php

require_once "utils.php";

$xml = simplexml_load_file('input/tanjurg.xml');

$tengyur = True;

// get_text_loc("rgyud 'grel, pu 317b7-320a5", "", "1");
// get_text_loc("'dul ba, ka 1b1-nga 302a5 (vol. 1-4)", "", "1");

$fd = fopen("/tmp/GT-locations.csv", "w");

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
    $itemfields = [
        $loc['section'],
        $item->tib,
        $rktsid,
        $ci,
        $loc['bvolname'],
        $loc['bpagenum'],
        $loc['bpageside'],
        $loc['blinenum'],
        $loc['evolname'],
        $loc['epagenum'],
        $loc['epageside'],
        $loc['elinenum']
    ];
    fwrite($fd, implode(",",$itemfields)."\n");
}
 
fclose($fd);
