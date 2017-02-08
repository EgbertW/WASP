#!/bin/bash

ME=`dirname $0`
LOC=`realpath $ME/..`

PHPUNIT=`which phpunit`
if [ "$PHPUNIT" == "" ]
then
    echo "Please make sure the phpunit command is in the path"
fi

pushd $LOC
echo "Running from $LOC"

$PHPUNIT --bootstrap core/lib/wasp/autoload/autoloader.class.php core/test

for module in `ls modules`
do
    if [ -d "modules/$module/test" ]
    then
        echo "Running tests for module $module"
        $PHPUNIT --bootstrap sys/init.php modules/$module/test
    fi
done
