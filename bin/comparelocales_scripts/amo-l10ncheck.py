#!/usr/bin/python 
import sys

if len(sys.argv) < 3:
    print "Usage: %s /path/to/xpi /path/to/comparelocales/repo" % sys.argv[0]
    sys.exit()

# Add the path to the lib files we need
sys.path.append(sys.argv[2] + '/lib')

from mozilla.core.comparelocales import *
import silme.format

silme.format.Manager.register('dtd', 'properties', 'ini', 'inc')

optionpack = CompareInit(inipath = sys.argv[1], 
                         inputtype = 'xpi',
                         returnvalue = 'statistics_json')

compareLocales(optionpack)
