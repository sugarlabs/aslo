# AMO Search

## Requirements

Installing this requires fabric for python.

You can install this using `easy_install fabric` and that should give you the
'fab' commandline tool.

## SQL

Let's start by importing the sql in data/sql into your remora database. Please
read through this as it is commented carefully. This creates some column
adjustments and requisite views that Sphinx uses to build it's index.

## Install Sphinx

In `fabfile.py` adjust `VAR_DIR` if you'd like to have sphinx look for data and log files in a
directory other than `/var/local/` e.g. `$HOME/var`.

Then run:

    fab install_sphinx

This will build sphinx into your current directory.

## Configure Sphinx

Copy the `sphinx.conf-dist` to `sphinx.conf` and adjust the database settings as necessary:

    type     = mysql
    sql_host = localhost
    sql_user = root
    sql_pass = 
    sql_db   = remora

## Create Sphinx Index

    bin/indexer --all --rotate

## Run Sphinx Server

    bin/searchd

## Assumptions

* One addon can be a member of multiple applications (however unlikely)
** We query for this using a fugly join
