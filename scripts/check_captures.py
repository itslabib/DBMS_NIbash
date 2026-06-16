import mysql.connector
cn = mysql.connector.connect(host='localhost', user='root', password='', database='nibash')
c = cn.cursor()
c.execute('SELECT id,camera_id,image_path,created_at FROM cctv_captures ORDER BY id DESC LIMIT 10')
rows = c.fetchall()
for r in rows:
    print(r)
cn.close()
