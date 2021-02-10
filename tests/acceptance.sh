#!/bin/bash

base=${1:-http://localhost:8080}

n=0
match() {
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && echo -n . || \
        (echo ""; echo "Error in test $n: Unable to \"grep $@\" this output:"; echo "$out"; exit 1) || exit 1
}

skipif() {
    echo "$out" | grep "$@" >/dev/null && echo -n S && return 1 || return 0
}

out=$(curl -v $base/ 2>&1);         match "HTTP/.* 200" && match -iv "Content-Type:"
out=$(curl -v $base/test 2>&1);     match -i "Location: /" && match -iP "Content-Type: text/html[\r\n]"
out=$(curl -v $base/invalid 2>&1);  match "HTTP/.* 404" && match -iP "Content-Type: text/html[\r\n]"
out=$(curl -v $base// 2>&1);        match "HTTP/.* 404"
out=$(curl -v $base/ 2>&1 -X POST); match "HTTP/.* 405"

out=$(curl -v $base/uri 2>&1);                          match "HTTP/.* 200" && match "$base/uri"
out=$(curl -v $base/uri/ 2>&1);                         match "HTTP/.* 200" && match "$base/uri/"
out=$(curl -v $base/uri/foo 2>&1);                      match "HTTP/.* 200" && match "$base/uri/foo"
out=$(curl -v $base/uri/foo/bar 2>&1);                  match "HTTP/.* 200" && match "$base/uri/foo/bar"
out=$(curl -v $base/uri/foo//bar 2>&1);                 match "HTTP/.* 200" && match "$base/uri/foo//bar"
out=$(curl -v $base/uri/Wham! 2>&1);                    match "HTTP/.* 200" && match "$base/uri/Wham!"
out=$(curl -v $base/uri/Wham%21 2>&1);                  match "HTTP/.* 200" && match "$base/uri/Wham%21"
out=$(curl -v $base/uri/AC%2FDC 2>&1);                  skipif "HTTP/.* 404"    && match "HTTP/.* 200" && match "$base/uri/AC%2FDC" # skip Apache (404 unless `AllowEncodedSlashes NoDecode`)
out=$(curl -v $base/uri/bin%00ary 2>&1);                skipif "HTTP/.* 40[04]" && match "HTTP/.* 200" && match "$base/uri/bin%00ary" # skip nginx (400) and Apache (404)
out=$(curl -v $base/uri/AC/DC 2>&1);                    match "HTTP/.* 200" && match "$base/uri/AC/DC"
out=$(curl -v $base/uri? 2>&1);                         match "HTTP/.* 200" && match "$base/uri" # trailing "?" not reported for empty query string
out=$(curl -v $base/uri?query 2>&1);                    match "HTTP/.* 200" && match "$base/uri?query"
out=$(curl -v $base/uri?q=a 2>&1);                      match "HTTP/.* 200" && match "$base/uri?q=a"
out=$(curl -v $base/uri?q=a! 2>&1);                     match "HTTP/.* 200" && match "$base/uri?q=a!"
out=$(curl -v $base/uri?q=a%21 2>&1);                   match "HTTP/.* 200" && match "$base/uri?q=a%21"
out=$(curl -v $base/uri?q=w%C3%B6rd 2>&1);              match "HTTP/.* 200" && match "$base/uri?q=w%C3%B6rd"
out=$(curl -v $base/uri?q=+ 2>&1);                      match "HTTP/.* 200" && match "$base/uri?q=+"
out=$(curl -v $base/uri?q=%20 2>&1);                    match "HTTP/.* 200" && match "$base/uri?q=%20"
out=$(curl -v $base/uri?q=a%2Fb 2>&1);                  match "HTTP/.* 200" && match "$base/uri?q=a%2Fb"
out=$(curl -v $base/uri?q=a%00b 2>&1);                  match "HTTP/.* 200" && match "$base/uri?q=a%00b"
out=$(curl -v $base/uri?q=a\&q=b 2>&1);                 match "HTTP/.* 200" && match "$base/uri?q=a&q=b"
out=$(curl -v $base/uri?q%5B%5D=a\&q%5B%5D=b 2>&1);     match "HTTP/.* 200" && match "$base/uri?q%5B%5D=a\&q%5B%5D=b"

out=$(curl -v $base/query 2>&1);                        match "HTTP/.* 200" && match "{}"
out=$(curl -v $base/query? 2>&1);                       match "HTTP/.* 200" && match "{}"
out=$(curl -v $base/query?query 2>&1);                  match "HTTP/.* 200" && match "{\"query\":\"\"}"
out=$(curl -v $base/query?q=a 2>&1);                    match "HTTP/.* 200" && match "{\"q\":\"a\"\}"
out=$(curl -v $base/query?q=a! 2>&1);                   match "HTTP/.* 200" && match "{\"q\":\"a!\"\}"
out=$(curl -v $base/query?q=a%21 2>&1);                 match "HTTP/.* 200" && match "{\"q\":\"a!\"\}"
out=$(curl -v $base/query?q=w%C3%B6rd 2>&1);            match "HTTP/.* 200" && match "{\"q\":\"wörd\"\}"
out=$(curl -v $base/query?q=a+b 2>&1);                  match "HTTP/.* 200" && match "{\"q\":\"a b\"\}"
out=$(curl -v $base/query?q=a%20b 2>&1);                match "HTTP/.* 200" && match "{\"q\":\"a b\"\}"
out=$(curl -v $base/query?q=a%2Fb 2>&1);                match "HTTP/.* 200" && match "{\"q\":\"a/b\"\}"
out=$(curl -v $base/query?q=a%00b 2>&1);                match "HTTP/.* 200" && match "{\"q\":\"a\\\\u0000b\"\}"
out=$(curl -v $base/query?q=a\&q=b 2>&1);               match "HTTP/.* 200" && match "{\"q\":\"b\"}"
out=$(curl -v $base/query?q%5B%5D=a\&q%5B%5D=b 2>&1);   match "HTTP/.* 200" && match "{\"q\":[[]\"a\",\"b\"[]]}"

out=$(curl -v $base/users/foo 2>&1);                    match "HTTP/.* 200" && match "Hello foo!" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
out=$(curl -v $base/users/w%C3%B6rld 2>&1);             match "HTTP/.* 200" && match "Hello wörld!"
out=$(curl -v $base/users/w%F6rld 2>&1);                match "HTTP/.* 200" && match "Hello w�rld!" # demo expects UTF-8 instead of ISO-8859-1
out=$(curl -v $base/users/a+b 2>&1);                    match "HTTP/.* 200" && match "Hello a+b!"
out=$(curl -v $base/users/Wham! 2>&1);                  match "HTTP/.* 200" && match "Hello Wham!!"
out=$(curl -v $base/users/Wham%21 2>&1);                match "HTTP/.* 200" && match "Hello Wham!!"
out=$(curl -v $base/users/AC%2FDC 2>&1);                skipif "HTTP/.* 404"    && match "HTTP/.* 200" && match "Hello AC/DC!" # skip Apache (404 unless `AllowEncodedSlashes NoDecode`)
out=$(curl -v $base/users/bi%00n 2>&1);                 skipif "HTTP/.* 40[04]" && match "HTTP/.* 200" && match "Hello bi�n!" # skip nginx (400) and Apache (404) 

out=$(curl -v $base/users 2>&1);     match "HTTP/.* 404"
out=$(curl -v $base/users/ 2>&1);    match "HTTP/.* 404"
out=$(curl -v $base/users/a/b 2>&1); match "HTTP/.* 404"

echo "OK ($n)"
