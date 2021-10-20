import json
from pathlib import Path
import boto3
import gzip
import hashlib
import re
import copy
import glob

S3 = boto3.client('s3')
IL_DIR = "il-cache"
BVM_BOILERPLATE = {}
ESUKHIA_ATTR_RIDS = ["W1GS66030", "W1KG13126", "W22704", "W23702", "W23703", "W2KG209989"]
PG_RE = re.compile(r"^(?P<folionum>x|\d+)(?P<duplind>'*)(?P<side>[ab])(?P<certaintyind>\??)(?P<detailind>\(d\d*\))?")
OUTPUT_DIR = "../buda-volume-manifests"

# TODO:
# - check errors:
#   * (rien) 1b
#   * 1b 1b (and all repeats)
#   * missing just one side
#   * missing from image list
#   * add tags for scan request
# - changes

NATIVERANGES = [
    {"range": [0x0900, 0x097F], "lt": "sa-Deva"},
    {"range": [0x0F00, 0x0FFF], "lt": "bo"},
    {"range": [0x0400, 0x045F], "lt": "ru"},
    {"range": [0x2E80, 0x2EFF], "lt": "zh-Hani"},
    {"range": [0x3000, 0x303F], "lt": "zh-Hani"},
    {"range": [0x3200, 0x9FFF], "lt": "zh-Hani"},
    {"range": [0xF900, 0xFAFF], "lt": "zh-Hani"},
    {"range": [0x20000, 0x2CEAF], "lt": "zh-Hani"},
    {"range": [0x0900, 0x097F], "lt": "zh-Hani"}
]

def guessFromRange(o):
    for r in NATIVERANGES:
        if (o > r['range'][0] and o < r['range'][1]):
            return r['lt'];
    return None

def guess_lt(s, default="en"):
    if s.endswith("/"):
        return "bo-x-ewts"
    if any(c in s for c in "ṀṃṂāĀīĪūŪṛṚṝṜḷḶḹḸḥḤṅṄñÑṭṬḍḌṇṆśŚṣṢḻḺ"):
        return "sa-x-iast"
    fromr = guessFromRange(ord(s[0]))
    if fromr is not None:
        return fromr
    return default

def strcmp(s1,s2):
    return (s1 > s2) - (s1 < s2)

def comparepg(pg1,pg2):
    if pg1 == pg2:
        return 0
    match1 = PG_RE.match(pg1)
    match2 = PG_RE.match(pg2)
    if match1 is None or match2 is None:
        return 0
    if match1.group("folionum") == 'x' or match2.group("folionum") == 'x':
        return 0
    fdiff = int(match1.group("folionum")) - int(match2.group("folionum"))
    if fdiff != 0:
        return fdiff
    duplcmp = strcmp(match1.group("duplind"), match2.group("duplind"))
    if duplcmp != 0:
        return duplcmp
    duplcmp = strcmp(match1.group("side"), match2.group("side"))
    if duplcmp != 0:
        return duplcmp
    return 0

def fix_one_file(iilname, fpath, iglname):
    if iilname in ESUKHIA_ATTR_RIDS:
        return
    rkjson = None
    try:
        with open(fpath) as json_file:
            rkjson = json.load(json_file)
    except Exception as e:
        print("can't open "+str(fpath)+" "+str(e))
        return None
    volqname = "bdr:"+iglname
    ilist = get_img_list(iilname, iglname)
    ilistfidx = {}
    previousi = 0
    regularfilelist = True
    imgidx = 0
    nbimages = len(ilist)
    for iinfo in ilist:
        fname = iinfo["filename"]
        ilistfidx[fname] = {"idx": imgidx}
        imgidx += 1
        if fname.endswith("json"):
            nbimages -= 1
            continue
        dotidx = fname.rfind('.')
        if dotidx == -1:
            print(iglname+": strange filename: "+fname)
            previousi = 0
            regularfilelist = False
        else:
            potentialnum = fname[dotidx-4:dotidx]
            try:
                i = int(potentialnum)
                if previousi != 0 and i != previousi + 1:
                    print(iglname+": non-contiguous imagelist"+fname)
                    regularfilelist = False
                previousi = i
            except:
                print(iglname+": strange filename: "+fname)
                previousi = 0
                regularfilelist = False
    if regularfilelist:
        return
    print("fixing "+str(fpath))
    # first some error checking and gathering the various sections
    sortedkeys = sorted(rkjson.keys(), key=lambda x: int(x))
    nbimagesused = 0
    diff = None
    haschanges = False
    for idxstr in sortedkeys:
        rkdata = rkjson[idxstr]
        imgdata = rkdata["file"]
        if imgdata == "missing":
            continue
        nbimagesused += 1
        dblcolidx = imgdata.find("::")
        if dblcolidx < 0:
            print(iglname+"("+idxstr+"): can't understand "+imgdata)
        fname = imgdata[dblcolidx+2:]
        # in some cases the first images have been adjusted, so we ignore shifts in the image list before image number 4:
        #if int(idxstr) < 5:
        #    continue
        dotidx = fname.find(".")
        fnamenums = fname[dotidx-4:dotidx]
        fnamenum = None
        try:
            fnamenum = int(fnamenums)
        except:
            print(iglname+"("+idxstr+"): can't find number of "+fname)
            continue
        if diff is None:
            if fname not in ilistfidx:
                print(iglname+"("+idxstr+"): file not in list: "+fname)
                continue
            fileidx = ilistfidx[fname]["idx"]
            diff = fnamenum - fileidx
            print("computing a diff of "+str(diff))
            continue
        if fnamenum-diff >= len(ilist):
            print(iglname+"("+idxstr+"):using too many files: "+fname)
            continue
        theoreticalfname = ilist[fnamenum-diff]["filename"]
        if theoreticalfname != fname:
            #print(iglname+"("+idxstr+"): renaming : "+fname+" into "+theoreticalfname)
            newimgdata = imgdata[:dblcolidx+2]+theoreticalfname
            rkdata["file"] = newimgdata
            haschanges = True
    if haschanges:
        print("writing "+iglname+".fixed.json")
        write_rkts_json(iglname+".fixed.json", rkjson)
    else:
        print("no change, a bit odd...")

def write_rkts_json(fname, rkjson):
    with open(fname, 'w') as json_file:
        json_file.write("{\n")
        first = True
        sortedkeys = sorted(rkjson.keys(), key=lambda x: int(x))
        for idxstr in sortedkeys:
            if not first:
                json_file.write(",\n")
            rkdata = rkjson[idxstr]
            json_file.write('"%s":{"pagination":"%s","psection":"%s","file":"%s"}' % (idxstr,rkdata["pagination"],rkdata["psection"],rkdata["file"]))
            first = False
        json_file.write('\n}')

def migrate_one_file(iilname, fpath, iglname):
    print("migrating "+str(fpath))
    res = copy.deepcopy(BVM_BOILERPLATE)
    rkjson = None
    try:
        with open(fpath) as json_file:
            rkjson = json.load(json_file)
    except Exception as e:
        print("can't open "+str(fpath)+" "+str(e))
        return None
    volqname = "bdr:"+iglname
    ilist = get_img_list(iilname, iglname)
    ilistfidx = {}
    previousi = 0
    for iinfo in ilist:
        fname = iinfo["filename"]
        ilistfidx[fname] = {}
        dotidx = fname.rfind('.')
        if dotidx == -1:
            print(iglname+": strange filename: "+fname)
            previousi = 0
        else:
            potentialnum = fname[dotidx-4:dotidx]
            try:
                i = int(potentialnum)
                if previousi != 0 and i != previousi + 1:
                    print(iglname+": non-contiguous imagelist"+fname)
                previousi = i
            except:
                print(iglname+": strange filename: "+fname)
                previousi = 0
    res["imggroup"] = volqname
    if iilname in ESUKHIA_ATTR_RIDS:
        res["attribution"] = get_lgstr_arr("Data provided by Esukhia under the CC0 license", "en")
    else:
        res["attribution"] = get_lgstr_arr("Data provided by rKTs under the CC0 license", "en")
    resimglist = res["view"]["view1"]["imagelist"]
    lastpg = None
    beforelastpg = None
    psections = []
    seenpg = {}
    lastpg = ""
    # first some error checking and gathering the various sections
    sortedkeys = sorted(rkjson.keys(), key=lambda x: int(x))
    if len(sortedkeys) == 0:
        return
    for idxstr in sortedkeys:
        rkdata = rkjson[idxstr]
        ps = "psection" in rkdata and rkdata["psection"] or ""
        if ps not in psections:
            psections.append(ps)
            seenpg[ps] = []
        pg = rkdata["pagination"]
        match = PG_RE.match(pg)
        if not match:
            print(iglname+"("+idxstr+"): "+pg+" looks invalid")
        elif lastpg and comparepg(lastpg, pg) > -1:
            print(iglname+"("+idxstr+"): "+pg+" before "+lastpg)
        if pg in seenpg[ps]:
            print(iglname+"("+idxstr+"): possible duplicate in "+iglname+": "+pg)
        seenpg[ps].append(pg)
        lastpg = pg
        imgdata = rkdata["file"]
        if imgdata == "missing":
            continue
        dblcolidx = imgdata.find("::")
        if dblcolidx < 0:
            print(iglname+"("+idxstr+"): can't understand "+imgdata)
        fname = imgdata[dblcolidx+2:]
        igname = imgdata[4:dblcolidx]
        if igname != iglname:
            print(iglname+"("+idxstr+"): file not in imagegroup: "+imgdata)
        elif fname not in ilistfidx:
            print(iglname+"("+idxstr+"): file not in list: "+fname)
        elif "seen" in ilistfidx[fname]:
            print(iglname+"("+idxstr+"): file used twice: "+fname)
        else:
            ilistfidx[fname]["seen"] = True
    if len(psections) > 1:
        res["sections"] = []
        for ps in psections:
            res["sections"].append({"id": ps,"name":{"@value": ps, "@language": "bo-x-ewts"}})
    insertafter = {}
    lastseen = None
    lastfname = None
    afterfirstseen = False
    for i, iinfo in enumerate(ilist):
        fname = iinfo["filename"]
        lastfname = fname
        fdata = ilistfidx[fname]
        if "seen" not in fdata:
            print(iglname+": file not used: "+fname)
            # adding first files to the bvm if they're not in the list
            if not afterfirstseen:
                resimgdata = {}
                resimglist.append(resimgdata)
                resimgdata["filename"] = fname
                # a bit risque but it seems to work
                if i < 3:
                    add_tag(resimgdata, "T0005")
                    resimgdata["hidden"] = True
            else:
                if lastseen not in insertafter:
                    insertafter[lastseen] = []
                insertafter[lastseen].append(fname)
        else:
            afterfirstseen = True
            lastseen = fname

    # removing the final entry so that we display the final images:
    finalimages = []
    if lastseen == lastfname and lastseen in insertafter:
        finalimage = insertafter[lastseen]
        insertafter[lastseen] = None

    for i, idxstr  in enumerate(sortedkeys):
        rkdata = rkjson[idxstr]
        resimgdata = {}
        resimglist.append(resimgdata)
        idx = int(idxstr)
        pagination = rkdata["pagination"]
        if 'd' in pagination:
            add_tag(resimgdata, "T0016")
            # TODO: of previous one?
        resimgdata["pagination"] = {"pgfolios": {"value": pagination}}
        if "psection" in rkdata and rkdata["psection"] and len(psections) > 1:
            resimgdata["pagination"]["pgfolios"]["section"] = rkdata["psection"]
        imgdata = rkdata["file"]
        # TODO: make sure that when there's a missing, both sides are missing
        if "missing" in imgdata:
            add_tag(resimgdata, "T0020")
            continue
        dblcolidx = imgdata.find("::")
        if dblcolidx > -1:
            fname = imgdata[dblcolidx+2:]
            resimgdata["filename"] = fname
            igname = imgdata[4:dblcolidx]
            if igname != iglname:
                resimgdata["imggroup"] = igname
            if fname in insertafter:
                for afterfname in insertafter[fname]:
                    resimgdata2 = {}
                    resimglist.append(resimgdata2)
                    resimgdata2["filename"] = afterfname
                    resimgdata2["hidden"] = True
        if "note" in rkdata and rkdata["note"] != "" and rkdata["note"] != "None":
            resimgdata = get_lgstr_arr(rkdata["note"], guess_lt(rkdata["note"]))
    for fname in finalimages:
        resimgdata = {}
        resimglist.append(resimgdata)
        resimgdata["filename"] = fname
    return res

def add_tag(imgdata, tagid):
    if "tags" not in imgdata:
        imgdata["tags"] = []
    imgdata["tags"].append(tagid)

def get_lgstr(value, lang):
    return {"@value": value, "@language": lang}

def get_lgstr_arr(value, lang):
    return [get_lgstr(value, lang)]

def dl_image_list(iilname, iglocalname, filename):
    iilname = iilname
    hashbucket = hashlib.md5(iilname.encode("utf8")).hexdigest()[:2]
    pre, rest = iglocalname[0], iglocalname[1:]
    if pre == 'I' and rest.isdigit() and len(rest) == 4:
        suffix = rest
    else:
        suffix = iglocalname
    key = "Works/"+hashbucket+"/"+iilname+"/images/"+iilname+"-"+suffix+"/dimensions.json"
    print("download "+key+" into "+filename)
    S3.download_file('archive.tbrc.org', key, filename)

def get_img_list(iilname, iglocalname):
    fname = IL_DIR+"/"+iglocalname+".json.gz"
    if not Path(fname).exists():
        Path(IL_DIR).mkdir(exist_ok=True)
        dl_image_list(iilname, iglocalname, fname)
    with gzip.open(fname, "r") as json_file:
        return json.load(json_file)

def main():
    global BVM_BOILERPLATE
    Path(OUTPUT_DIR).mkdir(exist_ok=True)
    with open('bvm-boilerplate.json') as json_file:
        BVM_BOILERPLATE = json.load(json_file)
    #for fname in ["../rKTs/paginations/3CN20612/3CN20712.json"]: #glob.glob('../rKTs/paginations/**/*.json'):
    for fname in glob.glob('rKTs/Collections/**/**/*.json'):
        if "sets" in fname or "vol" in fname:
            continue
        p = Path(fname)
        iglname = p.stem.startswith('I') and p.stem or 'I'+p.stem
        iilname = 'W'+p.parent.stem
        #res = fix_one_file(iilname, p, iglname)
        res = migrate_one_file(iilname, p, iglname)
        if res is None:
            continue
        bvmhash = hashlib.md5(iglname.encode("utf8")).hexdigest()[:2]
        print(iglname+":"+bvmhash)
        Path(OUTPUT_DIR+'/'+bvmhash).mkdir(exist_ok=True)
        with open(OUTPUT_DIR+'/'+bvmhash+'/'+iglname+'.json', 'w') as json_file:
            try:
                json.dump(res, json_file, indent=1, sort_keys=True, separators=(",",":"))
            except:
                print(res)
                return

# vol31 = 916
# curl -X PUT -H Content-Type:application/json -T output/pagination/I4CZ75258.json -G https://iiifpres-dev.bdrc.io/bvm/ig:bdr:I4CZ75258

#for i in range(1,138):
    #print('"%d":{"pagination":"%s%s","psection":"\'dul ba","file":"bdr:I1KG9871::I1KG98710%0.3d.tif"},' % (i,int((i+1)/2),i%2 and "a" or "b",i+2))
    #print('"%d":{"pagination":"%s%s","psection":"dkar chag","file":"bdr:I1KG9976::I1KG9976%0.3d.jpg"},' % (i,int((i+1)/2),i%2 and "a" or "b", i+2))

main()
