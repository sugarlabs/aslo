#!/bin/bash

export INDEXER='indexer'
export BIN_PATH=`dirname $0`

python $BIN_PATH/prime_sphinx_index.py && $INDEXER --rotate --all