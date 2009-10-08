import json
import subprocess
import warnings

with warnings.catch_warnings():
    warnings.simplefilter('ignore')
    import MySQLdb as mysql


CONNECTION = 'php connection.php %s'
SELECT_DEBUG = 'SELECT value FROM config WHERE config.key="cron_debug_enabled"'


def call(cmd):
    p = subprocess.Popen(cmd, shell=True, stdout=subprocess.PIPE)
    return p.communicate()[0]


def db_config(name):
    """Get the config parameters for the ``name`` db as a dict."""
    d = json.loads(call(CONNECTION % name))

    d['passwd'] = d.pop('password')
    d['db'] = d.pop('database')

    # The keys in d are unicode in Python 2.6 but mysql-python < 1.2.3c1
    # wants *strings*.  Should be fixed when py3k support is added.
    return dict((str(k), v) for k, v in d.items())


def alchemy_config(name, dbtype='mysql'):
    """Get a SQLAlchemy connection string for the ``name`` db."""
    params = db_config(name)
    params['dbtype'] = dbtype
    return '{dbtype}://{user}:{passwd}@{host}/{db}'.format(**params)


class Debug(object):
    """
    Callable that only prints debug messages if cron_debug_enabled is True in
    the config table.
    """

    def __init__(self):
        self.debug = None

    def set_debug(self):
        with mysql.connect(**db_config('read')) as cursor:
            if cursor.execute(SELECT_DEBUG) and cursor.fetchone()[0] == '1':
                self.debug = True
            else:
                self.debug = False

    def __call__(self, msg):
        if self.debug is None:
            self.set_debug()
        if self.debug:
            print msg

debug = Debug()
