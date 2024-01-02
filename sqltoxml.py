#from mysql.connector import connect
from xml.sax.saxutils import escape
from mariadb import connect
import csv

IMGGROUPTOVNUM = {}
def read_imggrouptovnum():
    with open('volume_numbers.csv', newline='') as csvfile:
        reader = csv.reader(csvfile)
        for row in reader:
            IMGGROUPTOVNUM[row[0]] = row[1]

def export_kernel():
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
    #query = "select * from Relations"
    #cursor.execute(query)
    #for row in cursor:
    #    print(row)
    query = "select rktsTyp, rkts, tib, skt, mng from rkts order by rkts asc"
    cursor.execute(query)
    rkts_xml_strs = {"K": "", "G": "", "T": ""}
    for row in cursor:
        if row[0] in rkts_xml_strs:
            letter = "" if row[0] == "K" else row[0].lower()
            if row[2] or row[3] or row[4]:
                rkts_xml_strs[row[0]] += "<item><rkts%s>%d</rkts%s>" % (letter, row[1], letter)
                if row[2]:
                    rkts_xml_strs[row[0]] += "<tib>%s</tib>" % (row[2])
                if row[3]:
                    rkts_xml_strs[row[0]] += "<skt>%s</skt>" % (row[3])
                if row[4]:
                    rkts_xml_strs[row[0]] += "<mng>%s</mng>" % (row[4])
                rkts_xml_strs[row[0]] += "</item>"
    with open("rKTs/sql_export/rkts.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["K"])
    with open("rKTs/sql_export/rktst.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["T"])
    with open("rKTs/sql_export/rktsg.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["G"])

def export_nlm():
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
    query = "select rktsTyp, rkts, refNLM from NLM where rkts != 0"
    cursor.execute(query)
    res = {}
    for row in cursor:
        res[row[2]] = row[0]+str(row[1])
    with open("nlm.csv", "w") as text_file:
        for nlmid, rkts in res.items():
            text_file.write("\"%s\",\"%s\"\n" % (nlmid, rkts))

VOLUMERENAME = {
    "1BLX886": "1ER876",
    "1BLX887": "1ER877",
    "1BLX888": "1ER878",
    "1BLX889": "1ER879",
    "1BLX890": "1ER880",
    "1BLX891": "1ER881",
    "1BLX892": "1ER882",
    "1BLX893": "1ER883",
    "1BLX894": "1ER884",
    "1BLX895": "1ER885",
    "1BLX896": "1ER886",
    "1BLX897": "1ER887",
    "1BLX898": "1ER888",
    "1BLX899": "1ER889",
    "1BLX900": "1ER890",
    "1BLX901": "1ER891",
    "1BLX902": "1ER892",
    "1BLX903": "1ER893",
    "1BLX904": "1ER894",
    "1BLX905": "1ER895",
    "1BLX906": "1ER896",
    "1BLX907": "1ER897",
    "1BLX908": "1ER898",
    "1BLX909": "1ER899",
    "1BLX910": "1ER900",
    "1BLX911": "1ER901",
    "1BLX912": "1ER902",
    "1BLX913": "1ER903",
    "1BLX914": "1ER904",
    "1BLX915": "1ER905",
    "1BLX916": "1ER906",
    "1BLX917": "1ER907",
    "1BLX918": "1ER908",
    "1BLX919": "1ER909"
}

def get_locations_xml(ref):
    if ref is None:
        return ''
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
    query = "select setid, vol, sec, debp, debf, debl, finp, finf, finl from locations where ref='%s'" % ref
    cursor.execute(query)
    res = ''
    for row in cursor:
        json = row[1]
        if json in VOLUMERENAME:
            json = VOLUMERENAME[json]
        imggroup = json
        if not json.startswith("I"):
            imggroup = "I"+json
        vnum = ""
        if imggroup not in IMGGROUPTOVNUM:
            print("missing volume number for json "+json)
        else:
            vnum = IMGGROUPTOVNUM[imggroup]
        res += '<loc><set>%s</set><json>%s</json><voln>%s</voln><psection>%s</psection><p>%s%s%d-%s%s%d</p></loc>' % (row[0], row[1], vnum, row[2], row[3], row[4], row[5], row[6], row[7], row[8])
    cursor.close()
    sc.close()
    return res

def export_catalogs():
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
    query = "select coll, ref, rktsTyp, rkts, tib, skt, coloph, colophTitle, margin, rnb from catalog where coll = 'Gcd' or coll = 'Bd' or coll = 'Ba' or coll = 'Gaz' or coll = 'Gbm' or coll = 'Gbr' or coll = 'Gdg' or coll = 'Ggk' or coll = 'Gkh' or coll = 'Go' or coll = 'Gtn' or coll = 'He' or coll = 'Hg' or coll = 'Hi' or coll = 'Ng' or coll = 'R' or coll = 'Ty' order by rnb asc"
    cursor.execute(query)
    rows = cursor.fetchall()
    cursor.close()
    sc.close()
    res = {}
    for row in rows:
        if row[0] not in res:
            res[row[0]] = '<?xml version="1.0" encoding="UTF-8"?><outline>'
        location_str = get_locations_xml(row[1])
        rkts_str = ""
        if row[3]:
            rkts_str = "%s%d" % (row[2], row[3])
        res[row[0]] += "<item><rkts>%s</rkts><ref>%s</ref>%s<tib>%s</tib><skttrans>%s</skttrans><note></note><coloph>%s</coloph><coltitle>%s</coltitle><margin>%s</margin></item>" % (rkts_str, row[1], location_str, escape(row[4]), escape(row[5]), escape(row[6]), escape(row[7]), escape(row[8]))
    for coll, coll_str in res.items():
        with open("rKTs/sql_export/%s.xml" % coll, "w") as text_file:
            text_file.write(coll_str+"</outline>")

def main():
    read_imggrouptovnum()
    #export_kernel()
    export_catalogs()
    #export_nlm()

main()
