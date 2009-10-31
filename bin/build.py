#!/usr/bin/env python
"""Mozilla Add-ons build script

If you update this script to touch a file in SVN you MUST update clean.sh
to remove that file.  On preview things are run in the following order:
  `./clean.sh` then `svn up` then `./build.py`
"""

__license__ = """\
***** BEGIN LICENSE BLOCK *****
Version: MPL 1.1/GPL 2.0/LGPL 2.1

The contents of this file are subject to the Mozilla Public License Version 
1.1 (the "License"); you may not use this file except in compliance with 
the License. You may obtain a copy of the License at 
http://www.mozilla.org/MPL/

Software distributed under the License is distributed on an "AS IS" basis,
WITHOUT WARRANTY OF ANY KIND, either express or implied. See the License
for the specific language governing rights and limitations under the
License.

The Original Code is the addons.mozilla.org site.

The Initial Developer of the Original Code is
Frederic Wenzel <fwenzel@mozilla.com>.
Portions created by the Initial Developer are Copyright (C) 2008
the Initial Developer. All Rights Reserved.

Contributor(s):
    Brian Krausz <bkrausz@mozilla.com>

Alternatively, the contents of this file may be used under the terms of
either the GNU General Public License Version 2 or later (the "GPL"), or
the GNU Lesser General Public License Version 2.1 or later (the "LGPL"),
in which case the provisions of the GPL or the LGPL are applicable instead
of those above. If you wish to allow use of your version of this file only
under the terms of either the GPL or the LGPL, and not to allow others to
use your version of this file under the terms of the MPL, indicate your
decision by deleting the provisions above and replace them with the notice
and other provisions required by the GPL or the LGPL. If you do not delete
the provisions above, a recipient may use your version of this file under
the terms of any one of the MPL, the GPL or the LGPL.

***** END LICENSE BLOCK *****
"""

import re
import sys, os
from subprocess import Popen, PIPE
import xml.parsers.expat
from tempfile import mkstemp

MINIFY_PRODUCTS = {
    'js': {
        'js/__utm.min.js': [
            { 'file':'js/__utm.js' }
        ],
        'js/jquery.addons.min.js': [
            { 'file':'js/jquery-compressed.js' }, 
            { 'file':'js/addons.js' }
        ],
        'js/amo2009/amo2009.min.js': [
            # This list is repeated in site/app/views/layouts/amo2009.thtml
            { 'file':'js/jquery-compressed.js' }, 
            { 'file':'js/jquery.cookie.js' },
            { 'file':'js/amo2009/global.js' },
            { 'file':'js/amo2009/slimbox2.js' },
            { 'file':'js/amo2009/addons.js' },
            { 'file':'js/amo2009/global-mozilla.js' }
        ]
    },
    'css': {
        'css/style.min.css': [
            { 'file':'css/type.css' },
            { 'file':'css/color.css' },
            { 'file':'css/screen.css', 'prefix':'@media screen, projection {', 'suffix':'}' },
            { 'file':'css/print.css',  'prefix':'@media print {', 'suffix':'}' },
        ],
        'css/amo2009/style.min.css': [
            { 'file':'css/amo2009/main.css' },
            { 'file':'css/amo2009/slimbox2.css' },
            { 'file':'css/amo2009/main-mozilla.css' },
            { 'file':'css/amo2009/legacy.css' },
            { 'file':'css/sugar.css' }
        ]
    }
}

# contants
REVISIONS_PHP_TEMPLATE = """\
<?php
define('REVISION', %d);
define('CSS_REVISION', %d);
define('JS_REVISION', %d);
?>"""


# globals
script_dir = os.path.dirname(sys.argv[0])
java = None # path to Java runtime


class RevisionParser(object):
    """XML parser to get latest SVN revision off 'svn info'"""
    parser = None
    __rev = 0
    
    def __createParser(self):
        """create XML parser object"""
        self.__rev = 0
        self.parser = xml.parsers.expat.ParserCreate()
        self.parser.StartElementHandler = self.__revisionElementParser
    
    def __revisionElementParser(self, name, attrs):
        """Element parser, pulling latest revision out of elements passed to it by expat"""
        if name == "commit":
            self.__rev = attrs['revision']
        
    def getLatestRevision(self, repo):
        """For a given SVN repository, find the latest changed revision
        
        returns the revision number (int), or 0 in the case of error"""
        try:
            self.__createParser()
            svninfo = Popen(["svn", "info", "--xml", repo], stdout=PIPE).communicate()[0]
            self.parser.Parse(svninfo, True)
            return int(self.__rev)
        except:
            return 0
    
    def getMaxRevision(self, repos):
        """From a list of SVN repositories, get their maximum revision"""
        return max([self.getLatestRevision(repo) for repo in repos])


class Minifier(object):
    """Concatenate and minify JS and CSS files"""
    
    def concatFiles(self, destName=None, sourceNames=[]):
        """concatenate some source files into a destination file
        
        Parameters:
        destName -- path of destination file; if None, temporary file is created
        sourceNames -- list of: String (source file path) or Dict {prefix, file, suffix}
        Returns: destination file path or None if no source files provided
        """
        if not sourceNames: return None
        try:
            try:
                destinationFile = open(destName, "w")
            except TypeError: # can't open None
                tempfile = mkstemp()
                destinationFile = os.fdopen(tempfile[0], "w")
                destName = tempfile[1]
            try:
                for source in sourceNames:

                    # source expected to be either a plain string, or a 
                    # dict describing filename / prefix / suffix
                    prefix = suffix = ''
                    if type(source) != dict:
                        sourceFileName = source
                    else:
                        sourceFileName = source['file']
                        if source.has_key('prefix'):
                            prefix = source['prefix']
                        if source.has_key('suffix'):
                            suffix = source['suffix']

                    sourceFile = open(sourceFileName)
                    try:
                        destinationFile.write(prefix)
                        destinationFile.writelines(sourceFile)
                        destinationFile.write(suffix)
                    finally:
                        sourceFile.close()
            finally:
                destinationFile.close()
            return destName
        except Exception, e:
            try:
                os.remove(tempfile[1])
            except:
                pass
            print e
    
    def minify(self, type, source, destination):
        """minify a JS or CSS file
        
        Parameters:
        type -- either 'js' or 'css'
        source -- path of source file
        destination -- path of destination file
        """
        compressor = Popen([java, '-jar', os.path.join(script_dir, '..', 'lib', 'yuicompressor', 'build', 'yuicompressor.jar'),
            '--type', type, source], stdout=PIPE)
        destFile = open(destination, 'w')
        # Fix media queries to look like "(..) and (..)", NOT "(..) and(..)".
        out = re.sub('and\(', 'and (', compressor.stdout.read())
        destFile.writelines(out)
        destFile.close()


def updateRevisions():
    """Find latest revisions for tree, CSS and JS, and write them to revisions.php"""
    print "Updating Revisions"
    
    rp = RevisionParser()
    
    tree_rev = rp.getLatestRevision('https://svn.mozilla.org/addons/trunk/')
    print "Latest Tree Revision:", tree_rev
    
    repo = 'https://svn.mozilla.org/addons/trunk/site/app/webroot/'
    revs = {}
    for rev_type in ('js', 'css'):
        files = []
        for fn, parts in MINIFY_PRODUCTS[rev_type].iteritems():
            for part in parts:
                files.append((type(part) == dict) and part['file'] or part)
        revs[rev_type] = rp.getMaxRevision([ repo + file for file in files ])
        print "%s Revision: %s" % (rev_type, revs[rev_type])
    
    revs_file = os.path.join(script_dir, '..', 'site', 'app', 'config', 'revisions.php')
    try:
        rf = open(revs_file, 'w')
        rf.write(REVISIONS_PHP_TEMPLATE % (tree_rev, revs['css'], revs['js']))
        rf.close()
    except IOError, e:
        print "Error writing revision.php file:", e


def concatAndMinify():
    """concatenate and minify JS and CSS files"""
    minifier = Minifier()
    
    webroot = os.path.join(script_dir, '..', 'site', 'app', 'webroot')

    for product_type, products in MINIFY_PRODUCTS.iteritems():
        for product_fn, product_parts in products.iteritems():

            # Make the final product path absolute.
            product_fn = os.path.join(webroot, *product_fn.split('/'))

            # Make all product part file paths absolute.
            abs_parts = []
            for spec in product_parts:
                spec['file'] = os.path.join(webroot, *spec['file'].split('/'))
                abs_parts.append(spec)

            # Concatenate all the product parts to a temp file.
            print 'Concatenating %s (%s)' % (product_type, product_fn)
            print "\n".join(["    * %s" % x['file'] for x in abs_parts])
            concat_fn = minifier.concatFiles(sourceNames=abs_parts)

            # Minify the temp file for product type, dump into final
            # product path.
            print 'Minifying %s (%s)' % (product_type, product_fn)
            minifier.minify(product_type, concat_fn, product_fn)

            # Clean up the intermediate temp file.
            os.remove(concat_fn)

def compilePo():
    """compile all .po files to Gettext .mo files"""
    print 'Compiling .po Files'
    localedir = os.path.join(script_dir, '..', 'site', 'app', 'locale')
    Popen([os.path.join(localedir, 'compile-mo.sh'), localedir])


def main(argv = None):
    global java
    
    if argv is None:
        argv = sys.argv
    
    try:
        java = argv[1]
    except IndexError:
        java = Popen(["which", "java"], stdout=PIPE).communicate()[0].strip()
    if not java:
        print "Usage: %s path_to_jre" % argv[0]
        sys.exit(1)
    
    #updateRevisions()
    concatAndMinify()
    #compilePo()
    print 'Done.'


if __name__ == "__main__":
    main()
