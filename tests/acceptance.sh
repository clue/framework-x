#!/bin/bash

base=${1:-http://localhost:8080}
baseWithPort=$(php -r 'echo parse_url($argv[1],PHP_URL_PORT) ? $argv[1] : $argv[1] . ":80";' "$base")

n=0
match() {
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && echo -n . || \
        (echo ""; echo "Error in test $n: Unable to \"grep $@\" this output:"; echo "$out"; exit 1) || exit 1
}
notmatch() {
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && \
        (echo ""; echo "Error in test $n: Expected to NOT \"grep $@\" this output:"; echo "$out") && exit 1 || echo -n .
}

skipif() {
    echo "$out" | grep "$@" >/dev/null && echo -n S && return 1 || return 0
}

out=$(curl -v $base/ 2>&1);         match "HTTP/.* 200" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
out=$(curl -v $base/invalid 2>&1);  match "HTTP/.* 404" && match -iP "Content-Type: text/html; charset=utf-8[\r\n]"
out=$(curl -v $base// 2>&1);        match "HTTP/.* 404"
out=$(curl -v $base/ 2>&1 -X POST); match "HTTP/.* 405"
out=$(curl -v $base/error 2>&1);        match "HTTP/.* 500" && match -iP "Content-Type: text/html; charset=utf-8[\r\n]" && match "<code>Unable to load error</code>"
out=$(curl -v $base/error/null 2>&1);   match "HTTP/.* 500" && match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

out=$(curl -v $base/sleep/fiber 2>&1);      match "HTTP/.* 200" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
out=$(curl -v $base/sleep/coroutine 2>&1);  match "HTTP/.* 200" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
out=$(curl -v $base/sleep/promise 2>&1);    match "HTTP/.* 200" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"

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
out=$(curl -v $base/uri/http://example.com:8080/ 2>&1); match "HTTP/.* 200" && match "$base/uri/http://example.com:8080/"
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

out=$(curl -v $base/LICENSE 2>&1);              match "HTTP/.* 200" && match -iP "Content-Type: text/plain[\r\n]"
out=$(curl -v $base/source 2>&1);               match -i "Location: /source/" && match -iP "Content-Type: text/html; charset=utf-8[\r\n]"
out=$(curl -v $base/source/ 2>&1);              match "HTTP/.* 200"
out=$(curl -v $base/source/composer.json 2>&1); match "HTTP/.* 200" && match -iP "Content-Type: application/json[\r\n]"
out=$(curl -v $base/source/LICENSE 2>&1);       match "HTTP/.* 200" && match -iP "Content-Type: text/plain[\r\n]"
out=$(curl -v $base/source/LICENSE/ 2>&1);      match -i "Location: ../LICENSE" && match -iP "Content-Type: text/html; charset=utf-8[\r\n]"
out=$(curl -v $base/source/LICENSE// 2>&1);     match "HTTP/.* 404"
out=$(curl -v $base/source//LICENSE 2>&1);      match "HTTP/.* 404"
out=$(curl -v $base/source/tests 2>&1);         match -i "Location: tests/" && match -iP "Content-Type: text/html; charset=utf-8[\r\n]"
out=$(curl -v $base/source/invalid 2>&1);       match "HTTP/.* 404"
out=$(curl -v $base/source/bin%00ary 2>&1);     match "HTTP/.* 40[40]" # expects 404, but not processed with nginx (400) and Apache (404)

out=$(curl -v $base/method 2>&1);               match "HTTP/.* 200" && match "GET"
out=$(curl -v $base/method -I 2>&1);            match "HTTP/.* 200" && match -iP "Content-Length: 5[\r\n]" # HEAD has no response body
out=$(curl -v $base/method -X POST 2>&1);       match "HTTP/.* 200" && match "POST"
out=$(curl -v $base/method -X PUT 2>&1);        match "HTTP/.* 200" && match "PUT"
out=$(curl -v $base/method -X PATCH 2>&1);      match "HTTP/.* 200" && match "PATCH"
out=$(curl -v $base/method -X DELETE 2>&1);     match "HTTP/.* 200" && match "DELETE"
out=$(curl -v $base/method -X OPTIONS 2>&1);    match "HTTP/.* 200" && match "OPTIONS"
out=$(curl -v $base -X OPTIONS --request-target "*" 2>&1);  skipif "Server: nginx" && match "HTTP/.* 200" # skip nginx (400)

out=$(curl -v $base/method/get 2>&1);           match "HTTP/.* 200" && match -iP "Content-Length: 4[\r\n]" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]" && match -iP "X-Is-Head: false[\r\n]" && match -P "GET$"
out=$(curl -v $base/method/get -I 2>&1);        match "HTTP/.* 200" && match -iP "Content-Length: 4[\r\n]" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]" && match -iP "X-Is-Head: true[\r\n]"
out=$(curl -v $base/method/head 2>&1);          match "HTTP/.* 200" && match -iP "Content-Length: 5[\r\n]" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]" && match -iP "X-Is-Head: false[\r\n]" && match -P "HEAD$"
out=$(curl -v $base/method/head -I 2>&1);       match "HTTP/.* 200" && match -iP "Content-Length: 5[\r\n]" && match -iP "Content-Type: text/plain; charset=utf-8[\r\n]" && match -iP "X-Is-Head: true[\r\n]"

out=$(curl -v $base/etag/ 2>&1);                            match "HTTP/.* 200" && match -iP "Content-Length: 0[\r\n]" && match -iP "Etag: \"_\""
out=$(curl -v $base/etag/ -H 'If-None-Match: "_"' 2>&1);    match "HTTP/.* 304" && notmatch -i "Content-Length" && match -iP "Etag: \"_\""
out=$(curl -v $base/etag/a 2>&1);                           match "HTTP/.* 200" && match -iP "Content-Length: 2[\r\n]" && match -iP "Etag: \"a\""
out=$(curl -v $base/etag/a -H 'If-None-Match: "a"' 2>&1);   skipif "Server: Apache" && match "HTTP/.* 304" && match -iP "Content-Length: 2[\r\n]" && match -iP "Etag: \"a\"" # skip Apache (no Content-Length)

out=$(curl -v $base/headers -H 'Accept: text/html' 2>&1);   match "HTTP/.* 200" && match "\"Accept\": \"text/html\""
out=$(curl -v $base/headers -d 'name=Alice' 2>&1);          match "HTTP/.* 200" && match "\"Content-Type\": \"application/x-www-form-urlencoded\"" && match "\"Content-Length\": \"10\""
out=$(curl -v $base/headers -u user:pass 2>&1);             match "HTTP/.* 200" && match "\"Authorization\": \"Basic dXNlcjpwYXNz\""
out=$(curl -v $base/headers 2>&1);                          match "HTTP/.* 200" && notmatch -i "\"Content-Type\"" && notmatch -i "\"Content-Length\""
out=$(curl -v $base/headers -H User-Agent: -H Accept: -H Host: -10 2>&1);   match "HTTP/.* 200" && match "{}"
out=$(curl -v $base/headers -H 'Content-Length: 0' 2>&1);   match "HTTP/.* 200" && match "\"Content-Length\": \"0\""
out=$(curl -v $base/headers -H 'Empty;' 2>&1);              match "HTTP/.* 200" && match "\"Empty\": \"\""
out=$(curl -v $base/headers -H 'Content-Type;' 2>&1);       skipif "Server: Apache" && match "HTTP/.* 200" && match "\"Content-Type\": \"\"" # skip Apache (discards empty Content-Type)
out=$(curl -v $base/headers -H 'DNT: 1' 2>&1);              skipif "Server: nginx" && match "HTTP/.* 200" && match "\"DNT\"" && notmatch "\"Dnt\"" # skip nginx which doesn't report original case (DNT->Dnt)
out=$(curl -v $base/headers -H 'V: a' -H 'V: b' 2>&1);      skipif "Server: nginx" && skipif -v "Server:" && match "HTTP/.* 200" && match "\"V\": \"a, b\"" # skip nginx (last only) and PHP webserver (first only)

out=$(curl -v --proxy $baseWithPort $base/debug 2>&1);      skipif "Server: nginx" && match "HTTP/.* 400" # skip nginx (continues like direct request)
out=$(curl -v --proxy $baseWithPort -p $base/debug 2>&1);   skipif "CONNECT aborted" && match "HTTP/.* 400" # skip PHP development server (rejects as "Malformed HTTP request")

echo "OK ($n)"
