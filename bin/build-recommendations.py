import json
import subprocess
import time

import MySQLdb as mysql

import recommend


CONNECTION = 'php connection.php %s'
SELECT = 'SELECT collection_id, addon_id FROM addons_collections ORDER BY collection_id'
INSERT = 'INSERT INTO addon_recommendations VALUES '
DELETE = 'TRUNCATE addon_recommendations'


def call(cmd):
    p = subprocess.Popen(cmd, shell=True, stdout=subprocess.PIPE)
    return p.communicate()[0]


def db_config(d):
    d['passwd'] = d.pop('password')
    d['db'] = d.pop('database')

    # The keys in d are unicode in Python 2.6 but mysql-python < 1.2.3c1
    # wants *strings*.  Should be fixed when py3k support is added.
    return dict((str(k), v) for k, v in d.items())


def map_data(cursor):
    mapping = {}
    cursor.execute(SELECT)
    for collection, addon in cursor.fetchall():
        mapping.setdefault(addon, set()).add(collection)
    return mapping


def main():
    start = time.time()

    read, write = [db_config(json.loads(call(CONNECTION % s)))
                   for s in ('read', 'write')]

    d = map_data(mysql.connect(**read).cursor())

    inserts = []
    recs = recommend.top(recommend.rank_all(d))
    for index, (addon, scores) in enumerate(recs):
        inserts.extend((addon, other, score) for other, score in scores)

    with mysql.connect(**write) as cursor:
        cursor.execute('BEGIN')
        cursor.execute(DELETE)
        cursor.execute(INSERT + ','.join('(%s,%s,%s)' % x for x in inserts))
        cursor.execute('COMMIT')


if __name__ == '__main__':
    main()
