import mysql.connector
cn = mysql.connector.connect(host='localhost', user='root', password='', database='nibash')
c = cn.cursor()
try:
    c.execute("ALTER TABLE cctv_captures ADD COLUMN face_hash VARCHAR(64) DEFAULT NULL;")
    print('OK')
except Exception as e:
    print('ERROR', e)
finally:
    cn.close()
