#!/bin/bash

# Very hacky release script to build the dist files and copy them
# to the target production and staging Omeka sites.

set -e

MODE=$1

# source the config file
. ./release-config.sh

if [ "$MODE" == "stage" ]; then
    for site in ${STAGE_SITES[@]} ; do
        for host in ${STAGE_HOSTS[@]} ; do
            echo "Releasing to $site.$STAGE_DOMAIN"
            rsync -avlz \
                --exclude .idea --exclude node_modules --exclude 'release*' --exclude .git --exclude test \
                . $host:/var/www/$site.$STAGE_DOMAIN/plugins/TeiEditions/
        done
    done
elif [ "$MODE" == "prod" ]; then
    for site in ${PROD_SITES[@]} ; do
        for host in ${PROD_HOSTS[@]} ; do
            echo "Releasing to $site.$PROD_DOMAIN"
            rsync -avlz \
                --exclude .idea --exclude node_modules --exclude 'release*' --exclude .git --exclude test \
                . $host:/var/www/$site.$PROD_DOMAIN/plugins/TeiEditions/
        done
    done
else
    echo "No mode given!"
fi
