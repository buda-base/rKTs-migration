from mysql.connector import connect, Error
from xml.sax.saxutils import escape


def export_kernel():
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
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
    with open("rKTs/sql_export/rkts_sql.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["K"])
    with open("rKTs/sql_export/rktst_sql.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["T"])
    with open("rKTs/sql_export/rktsg_sql.xml", "w") as text_file:
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

def get_locations_xml(ref):
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
    query = "select setid, vol, sec, debp, debf, debl, finp, finf, finl from locations where ref='%s'" % ref
    cursor.execute(query)
    res = ''
    for row in cursor:
        res += '<loc><set>%s</set><json>%s</json><psection>%s</psection><p>%s%s%d-%s%s%d</p></loc>' % (row[0], row[1], row[2], row[3], row[4], row[5], row[6], row[7], row[8])
    return res

def export_catalogs():
    sc = connect( host="localhost", user="rkts", password="rkts", database="rkts")
    cursor = sc.cursor()
    query = "select coll, ref, rktsTyp, rkts, tib, skt, coloph, colophTitle, margin from catalog where rktsTyp = 'T' order by ref asc"
    cursor.execute(query)
    res = {}
    for row in cursor:
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
    #export_kernel()
    export_catalogs()
    #export_nlm()

main()
