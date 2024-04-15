#!/bin/bash

base=${1:-http://localhost:8080/}
base=${base%/}

for i in {1..600}
do
   out=$(curl -v -X PROBE $base/ 2>&1) && exit 0 || echo -n .
   sleep 0.1
done

echo
echo "$out"
exit 1
