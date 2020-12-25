# We can define a bunch of fabric tasks to help with Sphinx
#

import os
import sys

ROOT           = os.path.realpath(os.path.join(os.path.dirname('.')))
TMP            = os.path.join(ROOT, 'tmp')
LATEST_SPHINX  = "http://sphinxsearch.com/files/archive/sphinx-0.9.9-rc2.tar.gz"
SPHINX_VERSION = (LATEST_SPHINX.rpartition('/')[2]).rpartition('.tar.')[0]
TARBALL        = os.path.join(TMP,LATEST_SPHINX.rpartition('/')[2])

INSTALL_ROOT     = '/opt/local'

def create_dirs():
    os.system("mkdir -p %s"% TMP)
    os.system("mkdir -p %s" % os.path.join(INSTALL_ROOT, 'data/sphinx'))
    os.system("mkdir -p %s" % os.path.join(INSTALL_ROOT, 'log/searchd'))

def install_sphinx():
    create_dirs()
    print("Obtaining sphinx")
    os.system("wget -P %s %s" % (TMP, LATEST_SPHINX))
    os.system("cd %s; tar zxf %s" % (TMP, TARBALL))
    #make
    os.system("cd %s;./configure --prefix=%s;make;make install"%(os.path.join(TMP, SPHINX_VERSION),INSTALL_ROOT))
