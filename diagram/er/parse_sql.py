import re
import json

with open(r'c:\xampp\htdocs\Nibash\nibash_sql.sql', 'r', encoding='utf-8') as f:
    sql = f.read()

# Match CREATE TABLE `tablename` ( ... )
blocks = re.findall(r'CREATE TABLE(?: IF NOT EXISTS)?\s+`([^`]+)`\s*\((.*?)\)\s*(?:ENGINE|;)', sql, re.IGNORECASE | re.DOTALL)

schema = {}
for t, c_str in blocks:
    cols = []
    lines = c_str.split('\n')
    for l in lines:
        l = l.strip()
        if l.startswith('`'):
            m = re.match(r'`([^`]+)`', l)
            if m:
                cols.append({'Field': m.group(1), 'Key': 'PRI' if 'AUTO_INCREMENT' in l.upper() else ''})
        elif l.upper().startswith('PRIMARY KEY'):
            m = re.search(r'`([^`]+)`', l)
            if m:
                for c in cols:
                    if c['Field'] == m.group(1):
                        c['Key'] = 'PRI'
    schema[t] = cols

with open(r'c:\xampp\htdocs\Nibash\diagram\er\schema_data.js', 'w', encoding='utf-8') as f:
    f.write('const fullSchema = ' + json.dumps(schema, indent=4) + ';\n')

print(f"Parsed {len(schema)} tables.")
