#!/bin/python

import re
import os
import MySQLdb


config_php_path = os.path.join(os.path.dirname(__file__), os.path.pardir, os.path.pardir, 'site', 'app', 'config', 'config.php')
config_php = open(config_php_path).read()

def get_config_define(name):
    q = """['"]"""
    def_re = re.compile('\s*'.join(['^\s*define\(', q, name, q, ',', q, '([-\w.]+)', q, '\);']), re.MULTILINE)
    match = def_re.search(config_php) 

    if match:
        return match.group(1)
    else:
        return ''

DB_HOST = get_config_define("DB_HOST")
DB_USER = get_config_define("DB_USER")
DB_PASS = get_config_define("DB_PASS")
DB_NAME = get_config_define("DB_NAME")

db = MySQLdb.connect(host=get_config_define("DB_HOST"), 
                        user=get_config_define("DB_USER"),
                        passwd=get_config_define("DB_PASS"),
                        db=get_config_define("DB_NAME"))

c = db.cursor()

# In a loop, because this script may update the nickname to a nickname that already exists.
while True:
    uniq_query = """SELECT GROUP_CONCAT(id ORDER BY id ASC) as ids, nickname, count(*) as how_many 
                        FROM users
                        WHERE nickname NOT IN ('', 'Deleted User')
                        GROUP BY nickname HAVING how_many > 1 
                        ORDER BY how_many;"""

    c.execute(uniq_query)

    if c.rowcount < 1:
        break

    update_queries = []
    update_format = "UPDATE users SET nickname = %s where id = %s"
    for row in c:
        update_queries.extend([(update_format, (row[1] + ('_' * (i + 1)), id)) for i, id in enumerate(row[0].split(',')[1:])])

    for u in update_queries:
        print "Updating user id", u[1][1]
        c.execute(*u)

c.close()
db.commit()
db.close()
