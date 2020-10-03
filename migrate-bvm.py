import json
from pathlib import Path
import boto3
import gzip
import hashlib
import re
import copy
import glob

S3 = boto3.client('s3')
IL_DIR = "output/il-cache"
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

def migrate_one_file(iilname, fpath, iglname):
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
    res["for-volume"] = volqname
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
        if fname not in ilistfidx:
            print(iglname+"("+idxstr+"): file not in list: "+fname)
        elif "seen" in ilistfidx[fname]:
            print(iglname+"("+idxstr+"): file used twice: "+fname)
        else:
            ilistfidx[fname]["seen"] = True
    for fname, fdata in ilistfidx.items():
        if "seen" not in fdata:
            print(iglname+": file not used: "+fname)
    for idxstr in sortedkeys:
        rkdata = rkjson[idxstr]
        resimgdata = {}
        resimglist.append(resimgdata)
        idx = int(idxstr)
        pagination = rkdata["pagination"]
        if 'd' in pagination:
            add_tag(resimgdata, "T0016")
            # TODO: of previous one?
        resimgdata["pagination"] = {"pgfolios": {"value": pagination}}
        imgdata = rkdata["file"]
        # TODO: make sure that when there's a missing, both sides are missing
        if "missing" in imgdata:
            add_tag(resimgdata, "T0020")
            continue
        dblcolidx = imgdata.find("::")
        if dblcolidx > -1:
            resimgdata["filename"] = imgdata[dblcolidx+2:]
        if "note" in rkdata and rkdata["note"] != "" and rkdata["note"] != "None":
            resimgdata = get_lgstr_arr(rkdata["note"], guess_lt(rkdata["note"]))
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
    for fname in glob.glob('../rKTs/paginations/**/*.json'):
        print("migrating "+fname)
        p = Path(fname)
        iglname = p.stem.startswith('I') and p.stem or 'I'+p.stem
        iilname = 'W'+p.parent.stem
        res = migrate_one_file(iilname, p, iglname)
        if res is None:
            continue
        bvmhash = hashlib.md5(iglname.encode("utf8")).hexdigest()[:2]
        Path(OUTPUT_DIR+'/'+bvmhash).mkdir(exist_ok=True)
        with open(OUTPUT_DIR+'/'+bvmhash+'/'+iglname+'.json', 'w') as json_file:
            json.dump(res, json_file, sort_keys=True, indent=2)

# vol31 = 916
# curl -X PUT -H Content-Type:application/json -T output/pagination/I4CZ75258.json -G https://iiifpres-dev.bdrc.io/bvm/ig:bdr:I4CZ75258

main()