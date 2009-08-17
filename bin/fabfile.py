# We can define a bunch of fabric tasks to help with Sphinx
# 

import os
import sys

ROOT           = os.path.realpath(os.path.join(os.path.dirname('.')))
TMP            = os.path.join(ROOT, 'tmp')
LATEST_SPHINX  = 'http://www.sphinxsearch.com/downloads/sphinx-0.9.9-rc2.tar.gz'
SPHINX_VERSION = (LATEST_SPHINX.rpartition('/')[2]).rpartition('.tar.')[0]
TARBALL        = os.path.join(TMP,LATEST_SPHINX.rpartition('/')[2])

INSTALL_ROOT     = '/opt/local'

def create_dirs():
    local("mkdir -p %s"% TMP)
    local("mkdir -p %s" % os.path.join(INSTALL_ROOT, 'data/sphinx'))
    local("mkdir -p %s" % os.path.join(INSTALL_ROOT, 'log/searchd'))

def install_sphinx():
    create_dirs()
    print("Obtaining sphinx")
    local("wget -P %s %s" % (TMP, LATEST_SPHINX))
    local("cd %s; tar zxf %s" % (TMP, TARBALL))
    #make
    local("cd %s;./configure --prefix=%s;make;make install"%(os.path.join(TMP, SPHINX_VERSION),INSTALL_ROOT))
