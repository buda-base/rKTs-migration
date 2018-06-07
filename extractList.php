#!/usr/bin/env php
<?php

use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/vendor/autoload.php';
require_once "utils.php";

$config = Yaml::parseFile('rkts.yaml');

$gl_rkts_abstract = get_abstract_mapping();

$editions_K = ['derge', 'narthang', 'peking', 'lhasa', 'urga', 'shey', 'cone', 'lithang', 'stog'];
$editions_T = ['tanjurg', 'tanjurn', 'tanjurq', 'tanjurd'];

$res_K = [];
$res_T = [];

function do_one_edition($filename, $tengyur) {
    global $res_K, $res_T;
    $xml = simplexml_load_file('rKTs/'.($tengyur ? 'Tanjur' : 'Kanjur').'/'.$filename.'.xml');
    $res = &$res_K;
    if ($tengyur)
        $res = &$res_T;
    foreach($xml->item as $item) {
        if ($tengyur) {
            $rktsid = $item->rktst;
        } else {
            $rktsid = $item->rkts;
        }
        if ($rktsid == '-')
            return;
        $rktsid = intval($rktsid);
        $ci = substr($item->ref, 1);
        if (!array_key_exists($rktsid, $res))
            $res[$rktsid] = [$filename => []];
        if (!array_key_exists($filename, $res[$rktsid]))
            $res[$rktsid][$filename] = [];
        $res[$rktsid][$filename][] = $ci;
    }
}

foreach ($editions_K as $edition) {
    do_one_edition($edition, false);
}

$fd = fopen("/tmp/correspondences_K.csv", "w");
fwrite($fd, 'abstract,expression,'.join(',', $editions_K)."\n");
foreach ($res_K as $rktsid => $editions) {
    $resarray = [];
    foreach ($editions_K as $edition) {
        if (isset($editions[$edition])) {
            $resarray[$edition] = join('/', $editions[$edition]);
        } else {
            $resarray[$edition] = "";
        }
        
    }
    $arid = id_to_url_abstract($rktsid, $config, true, false);
    $erid = id_to_url_expression($rktsid, $config, true, false);
    fwrite($fd, $arid.','.$erid.','.join(',', $resarray)."\n");
}
fclose($fd);

foreach ($editions_T as $edition) {
    do_one_edition($edition, true);
}

$fd = fopen("/tmp/correspondences_T.csv", "w");
fwrite($fd, 'abstract,expression,'.join(',', $editions_T)."\n");
foreach ($res_T as $rktsid => $editions) {
    $resarray = [];
    foreach ($editions_T as $edition) {
        if (isset($editions[$edition])) {
            $resarray[$edition] = join('/', $editions[$edition]);
        } else {
            $resarray[$edition] = "";
        }
        
    }
    $arid = id_to_url_abstract($rktsid, $config, true, true);
    $brid = id_to_url_expression($rktsid, $config, true, true);
    fwrite($fd, $arid.','.$erid.','.join(',', $resarray)."\n");
}
fclose($fd);
