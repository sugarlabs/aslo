"""
Extracts your database connection info from config.php to define
`db` and `table`, which schematic requires for running migrations.
"""
from os.path import abspath, dirname, join, pardir
import os.path as path
import re


def get(name, text, default=''):
    """Extract something like 'define('name', 'value');'"""
    q = """['"]"""
    def_re = '\s*'.join(['^define\(', q, name, q, ',', q, '([-\w]+)', q, '\);'])
    match = re.search(def_re, text, re.MULTILINE)
    if match:
        return match.group(1)
    else:
        return default

# Expecting to be in config/migrations/settings.py, want to find
# config/config.php.
config_path = join(abspath(dirname(__file__)), pardir, 'config.php')
config = open(config_path).read()

params = {
    'host': get('DB_HOST', config, 'localhost'),
    'port': get('DB_PORT', config, '3306'),
    'user': get('DB_USER', config),
    'password': get('DB_PASS', config),
    'database': get('DB_NAME', config),
}

connection = ' '.join("--%s='%s'" % (k, v) for k, v in params.items() if v)

db = 'mysql %s --silent' % connection
table = 'schema_version'
