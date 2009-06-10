#!/usr/bin/perl -wnT 
use LWP 5.66; #
use File::Basename;
use Archive::Zip qw( :ERROR_CODES :CONSTANTS ); 

#
# Script to check for the presence of install.js in Plugin XPIs
# Should be run from the command line with a line like:
#
# ./installck file_containing_list_of_plugin_urls
#
# the n flag in the perl line at top of file will cause the running
# of the script below once for each line of the supplied file.

my $TMP_PREFIX = "tmp";  # prefix we prepend to XPIs when we download them
                                                  # should choose so we don't overwrite anything useful in current dir.
                                                  
my $ua = LWP::UserAgent->new;
my $url = $_;
my $test_failed = 0;
chop($url);


print "\n\nURL is: $url\n";

#
# if possible extract the name of the XPI from the currently supplied URL
#
if ($url =~ m/.*\/(.*.xpi)/) {
    $fname = $TMP_PREFIX.$1; #set up tmp filename we will download to
    print "URL format test .... [PASSED]\n";
} else {
    print "URL format test .... [FAILED]\n";
    print "Remaining tests .... [SKIPPED]\n";  
    next; 
}

#
# try to download the XPI in question
#
my $response = $ua->get($url, ':content_file' => $fname);

print "GET request status ".$response->status_line.", so test .... ";

if($response->is_success)  {
    print "[PASSED]\n";
} else {
    print "[FAILED]\n";
    $test_failed = 1;
}
 
#
# try to read the XPI archive
#
my $xpi = Archive::Zip->new();
 
if($test_failed) {
    print "Read XPI test .... [SKIPPED]\n";
} elsif ($xpi->read( $fname ) == AZ_OK ) {
     print "Read XPI  test .... [PASSED]\n";
} else {
    print "Read XPI  test .... [FAILED]\n"; 
    $test_failed = 1; 
}
 
#
# Check XPI Archive for install.js file
#
if($test_failed) {
    print "Contains install.js check ... [SKIPPED]\n";
} elsif ($xpi->memberNamed("install.js"))  {
    print "Contains install.js check .... [FILE PRESENT]\n";
} else {
    print "Contains install.js check .... [FILE NOT PRESENT]\n"; 
}
 
 #
 # Clean up tmp file
 #
 unlink($fname);