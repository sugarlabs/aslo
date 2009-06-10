#!/bin/sh

mkdir -p chrome;
[ -f bandwagon.xpi ] && rm bandwagon.xpi;
[ -f chrome/bandwagon.jar ] && rm chrome/bandwagon.jar;
zip -r0 bandwagon.jar content locale skin -x \*.svn/* -x \*.zip -x \*.db -x \*.xcf -x \*.\*~ -x \*.DS_Store;
mv bandwagon.jar chrome/
zip -r9 bandwagon.xpi chrome.manifest install.rdf defaults/ chrome/ components/ -x \*.svn/* -x \*.DS_Store;
printf "build finished.\n";
