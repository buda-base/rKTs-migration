from mysql.connector import connect, Error


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
    with open("rKTs/Kernel/rkts_sql.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["K"])
    with open("rKTs/Kernel/rktst_sql.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["T"])
    with open("rKTs/Kernel/rktsg_sql.xml", "w") as text_file:
        text_file.write('<?xml version="1.0" encoding="UTF-8"?><outline><name>rKTs</name><note>exported from SQL</note>%s</outline>' % rkts_xml_strs["G"])
    print("xmllint --format rKTs/Kernel/rkts_sql.xml | sponge rKTs/Kernel/rkts_sql.xml")
    print("xmllint --format rKTs/Kernel/rktst_sql.xml | sponge rKTs/Kernel/rktst_sql.xml")
    print("xmllint --format rKTs/Kernel/rktsg_sql.xml | sponge rKTs/Kernel/rktsg_sql.xml")

def main():
    export_kernel()

main()
