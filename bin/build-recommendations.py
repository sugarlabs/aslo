import time
import warnings

with warnings.catch_warnings():
    warnings.simplefilter('ignore')
    import MySQLdb as mysql

import recommend
import utils


SELECT = 'SELECT collection_id, addon_id FROM addons_collections ORDER BY collection_id'
INSERT = 'INSERT INTO addon_recommendations VALUES '
DELETE = 'TRUNCATE addon_recommendations'


def map_data(cursor):
    mapping = {}
    cursor.execute(SELECT)
    for collection, addon in cursor.fetchall():
        mapping.setdefault(addon, set()).add(collection)
    return mapping


def main():
    start = time.time()

    read, write = [utils.db_config(s) for s in ('read', 'write')]

    d = map_data(mysql.connect(**read).cursor())

    inserts = []
    recs = recommend.top(recommend.rank_all(d))
    for index, (addon, scores) in enumerate(recs):
        if index % 100 == 0:
            utils.debug('%s: %s (%s)' % (index, addon, time.time() - start))

        inserts.extend((addon, other, score) for other, score in scores)

    with mysql.connect(**write) as cursor:
        cursor.execute('BEGIN')
        cursor.execute(DELETE)
        cursor.execute(INSERT + ','.join('(%s,%s,%s)' % x for x in inserts))
        cursor.execute('COMMIT')

    utils.debug('Total: %s' % (time.time() - start))

if __name__ == '__main__':
    main()
