#  Use this to upgrade the versions table
#  query the current database for versions
#  create a new versions column as an INT

import os
import sys
import re
sys.path.append(os.path.join(os.path.dirname(__file__), 'schematic'))

from schematic import get_settings, say

SETTINGS_DIR = os.path.realpath(os.path.join(os.path.dirname(__file__), \
os.path.pardir, "site/app/config/migrations"))

settings = get_settings(SETTINGS_DIR)

pattern_str = r'(\d+)\.(\d+)\.?(\d+)?\.?(\d+)?([a|b]?)(\d*)(pre)?(\d)?'
pattern     = re.compile(pattern_str)

pattern_plus = re.compile(r'((\d+)\+)')

def translate(version_string):
    # replace .x/.* with .99

    version_string = version_string.replace('.x', '.99')
    version_string = version_string.replace('.*', '.99')

    # replace \d+\+ with $1++
    match = re.search(pattern_plus, version_string)
    if match:
        (old, ver) = match.groups()
        replacement = "%dpre0"%(int(ver)+1)
        version_string = version_string.replace(old, replacement)

    # version_string.replace()
    match = re.match(pattern, version_string)
    if match:
        (major, minor1, minor2, minor3, alpha, alpha_n, pre, pre_n) \
        = match.groups()

        # normalize data
        major  = int(major)
        minor1 = int(minor1)
        if not minor2:
            minor2 = 0
        else:
            minor2 = int(minor2)

        if not minor3:
            minor3 = 0
        else:
            minor3 = int(minor3)

        if alpha == 'a':
            alpha = 0
        elif alpha == 'b':
            alpha = 1
        else:
            alpha = 2

        if alpha_n:
            alpha_n  = int(alpha_n)
        else:
            alpha_n = 0

        if pre == 'pre':
            pre = 0
        else:
            pre = 1

        if pre_n:
            pre_n = int(pre_n)
        else:
            pre_n = 0

        # print (major,minor1,minor2,alpha,alpha_n,pre,pre_n)
        # print version_string
        int_str = "%02d%02d%02d%02d%d%02d%d%02d" \
        % (major, minor1, minor2, minor3, alpha, alpha_n, pre, pre_n)
        
        return int_str
    
if __name__ == '__main__':
    say(settings.db, "ALTER TABLE appversions ADD COLUMN version_int \
    BIGINT UNSIGNED AFTER version;")
    id_versions = say(settings.db, \
    "SELECT id, version FROM appversions WHERE version_int >  3060000001000 ")

    for id_version in id_versions.split('\n'):

        if not id_version:
            continue

        [version_id, version_string] = id_version.split('\t')
        int_str = translate(version_string)

        if int_str:
            say(settings.db, \
            "UPDATE appversions SET version_int = %s WHERE id=%s"\
            % (int_str, version_id))
