#!/bin/sh
for i in `find . -name '*.js' -or -name '*.module' -or -name '*.php' -or -name '*.po*' -or -name '*.tpl' -or -name '*.txt' -or -name '*.install' -or -name '*.inc' -or -name '*.test'`; do
  echo $i;
  perl -pi -e 's/CiviCRM version .\../CiviCRM version 4.5/' $i;
  perl -pi -e 's/Copyright CiviCRM LLC \(c\) 2004-20../Copyright CiviCRM LLC (c) 2004-2014/' $i;
  perl -pi -e 's/CiviCRM LLC \(c\) 2004-20../CiviCRM LLC (c) 2004-2014/' $i;
done
