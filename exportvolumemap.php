<?php

require_once "utils.php";

$xml = simplexml_load_file('rKTs/Kanjur/narthang.xml');

$tengyur = True;

$res = [];

$previoussec = "";
$sectionArr = null;
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
    $loc = get_text_loc($locstr, "", $rktsid);
    if (!$loc) continue;
    if (!isset($loc['section'])) {
        //print("no section for ".$ci."\n");
    } else {
        $section = $loc['section'];
        if ($section != $previoussec) {
            print($ci." change of section: ".$previoussec." -> ".$section."\n");
            if ($sectionArr != null)
                array_push($res, $sectionArr);
            $sectionArr = ["name" => $loc['section'], "volumes" => [$loc['bvolname']]];
        }
        if (!in_array($loc['bvolname'], $sectionArr["volumes"])) {
            array_push($sectionArr["volumes"], $loc['bvolname']);
        }
        if (isset($loc['evolname']) && !empty($loc['evolname']) && !in_array($loc['evolname'], $sectionArr["volumes"])) {
            array_push($sectionArr["volumes"], $loc['evolname']);   
        }
        $previoussec = $section;
        //print($loc['section'].", ".$loc['bvolname']."\n");
    }
}
array_push($res, $sectionArr);

print("  volumeMap: [\n");
foreach($res as $sectionArr) {
    print("    {name: \"".$sectionArr["name"]."\", volumes: [");
    $first = true;
    foreach($sectionArr["volumes"] as $vol) {
        if (!$first)
            print(', ');
        $first = false;
        print('"'.$vol.'"');
    }
    print("]},\n");
}
print("    ]\n");
