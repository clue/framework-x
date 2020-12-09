#!/bin/bash

base=${1:-http://localhost:8080}

n=0
match() {
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && echo -n . || \
        (echo ""; echo "Error in test $n: Unable to \"grep $@\" this output:"; echo "$out"; exit 1) || exit 1
}

killall php 2>/dev/null
php examples/index.php >/dev/null &
sleep 0.2

out=$(curl -v $base/ 2>&1) &&           match "HTTP/.* 200"
out=$(curl -v $base/test 2>&1) &&       match -i "Location: /"
out=$(curl -v $base/invalid 2>&1) &&    match "HTTP/.* 404"
out=$(curl -v $base/ 2>&1 -X POST) &&   match "HTTP/.* 405"

killall php
php -S localhost:8080 examples/index.php >/dev/null 2>&1 &
sleep 0.2

out=$(curl -v $base/ 2>&1) &&           match "HTTP/.* 200"
out=$(curl -v $base/test 2>&1) &&       match -i "Location: /"
out=$(curl -v $base/invalid 2>&1) &&    match "HTTP/.* 404"
out=$(curl -v $base/ 2>&1 -X POST) &&   match "HTTP/.* 405"

killall php
echo "OK ($n)"
