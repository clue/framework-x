#!/bin/bash

base=${1:-http://localhost:8080}

n=0
match() {
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && echo -n . || \
        (echo ""; echo "Error in test $n: Unable to \"grep $@\" this output:"; echo "$out"; exit 1) || exit 1
}

out=$(curl -v $base/ 2>&1);          match "HTTP/.* 200"
out=$(curl -v $base/test 2>&1);      match -i "Location: /"
out=$(curl -v $base/invalid 2>&1);   match "HTTP/.* 404"
out=$(curl -v $base/uri 2>&1);       match "HTTP/.* 200" && match "$base/uri"
out=$(curl -v $base// 2>&1);         match "HTTP/.* 404"
out=$(curl -v $base/ 2>&1 -X POST);  match "HTTP/.* 405"
out=$(curl -v $base/users/foo 2>&1); match "HTTP/.* 200" && match "Hello foo!"
out=$(curl -v $base/users 2>&1);     match "HTTP/.* 404"
out=$(curl -v $base/users/ 2>&1);    match "HTTP/.* 404"
out=$(curl -v $base/users/a/b 2>&1); match "HTTP/.* 404"

echo "OK ($n)"
