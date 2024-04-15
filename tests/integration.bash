#!/bin/bash

base=${1:-http://localhost:8080/}
base=${base%/}
baseWithPort=$(php -r 'echo parse_url($argv[1],PHP_URL_PORT) ? $argv[1] : $argv[1] . ":80";' "$base")

n=0
skipping=false
curl() {
    skipping=false
    out=$($(which curl) "$@" 2>&1);
}
match() {
    [[ $skipping == true ]] && return 0
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && echo -n . || \
        (echo ""; echo "Error in test $n: Unable to \"grep $@\" this output:"; echo "$out"; exit 1) || exit 1
}
notmatch() {
    [[ $skipping == true ]] && return 0
    n=$[$n+1]
    echo "$out" | grep "$@" >/dev/null && \
        (echo ""; echo "Error in test $n: Expected to NOT \"grep $@\" this output:"; echo "$out") && exit 1 || echo -n .
}

skipif() {
    echo "$out" | grep "$@" >/dev/null && echo -n S && skipping=true || return 0
}
skipifnot() {
    echo "$out" | grep "$@" >/dev/null && return 0 || echo -n S && skipping=true
}

# check index endpoint

curl -v $base/
match "HTTP/.* 200"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"

curl -v $base/ -X POST
match "HTTP/.* 405"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

# check unknown endpoints return `404 Not Found`

curl -v $base/unknown
match "HTTP/.* 404"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

curl -v $base/index.php
match "HTTP/.* 404"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

curl -v $base/.htaccess
match "HTTP/.* 404"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

curl -v $base//
match "HTTP/.* 404"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

# check endpoints that intentionally return an error

curl -v $base/error
match "HTTP/.* 500"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"
match "<code>Unable to load error</code>"

curl -v $base/error/null
match "HTTP/.* 500"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

# check async fibers + coroutines + promises

curl -v $base/sleep/fiber
match "HTTP/.* 200"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"

curl -v $base/sleep/coroutine
match "HTTP/.* 200"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"

curl -v $base/sleep/promise
match "HTTP/.* 200"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"

# check URIs with special characters and encoding

curl -v $base/uri
match "HTTP/.* 200"
match "$base/uri"

curl -v $base/uri/
match "HTTP/.* 200"
match "$base/uri/"

curl -v $base/uri/foo
match "HTTP/.* 200"
match "$base/uri/foo"

curl -v $base/uri/foo/bar
match "HTTP/.* 200"
match "$base/uri/foo/bar"

curl -v $base/uri/foo//bar
match "HTTP/.* 200"
match "$base/uri/foo//bar"

curl -v $base/uri/Wham!
match "HTTP/.* 200"
match "$base/uri/Wham!"

curl -v $base/uri/Wham%21
match "HTTP/.* 200"
match "$base/uri/Wham%21"

curl -v $base/uri/AC%2FDC
skipif "HTTP/.* 404" # skip Apache (404 unless `AllowEncodedSlashes NoDecode`)
match "HTTP/.* 200"
match "$base/uri/AC%2FDC"

curl -v $base/uri/bin%00ary
skipif "HTTP/.* 40[04]" # skip nginx (400) and Apache (404)
match "HTTP/.* 200"
match "$base/uri/bin%00ary"

curl -v $base/uri/AC/DC
match "HTTP/.* 200"
match "$base/uri/AC/DC"

curl -v $base/uri/http://example.com:8080/
match "HTTP/.* 200"
match "$base/uri/http://example.com:8080/"

curl -v $base/uri?
match "HTTP/.* 200"
match "$base/uri" # trailing "?" not reported for empty query string

curl -v $base/uri?query
match "HTTP/.* 200"
match "$base/uri?query"

curl -v $base/uri?q=a
match "HTTP/.* 200"
match "$base/uri?q=a"

curl -v $base/uri?q=a!
match "HTTP/.* 200"
match "$base/uri?q=a!"

curl -v $base/uri?q=a%21
match "HTTP/.* 200"
match "$base/uri?q=a%21"

curl -v $base/uri?q=w%C3%B6rd
match "HTTP/.* 200"
match "$base/uri?q=w%C3%B6rd"

curl -v $base/uri?q=+
match "HTTP/.* 200"
match "$base/uri?q=+"

curl -v $base/uri?q=%20
match "HTTP/.* 200"
match "$base/uri?q=%20"

curl -v $base/uri?q=a%2Fb
match "HTTP/.* 200"
match "$base/uri?q=a%2Fb"

curl -v $base/uri?q=a%00b
match "HTTP/.* 200"
match "$base/uri?q=a%00b"

curl -v $base/uri?q=a\&q=b
match "HTTP/.* 200"
match "$base/uri?q=a&q=b"

curl -v $base/uri?q%5B%5D=a\&q%5B%5D=b
match "HTTP/.* 200"
match "$base/uri?q%5B%5D=a\&q%5B%5D=b"

# check query strings with special characters and encoding

curl -v $base/query
match "HTTP/.* 200"
match "{}"

curl -v $base/query?
match "HTTP/.* 200"
match "{}"

curl -v $base/query?query
match "HTTP/.* 200"
match "{\"query\":\"\"}"

curl -v $base/query?q=a
match "HTTP/.* 200"
match "{\"q\":\"a\"\}"

curl -v $base/query?q=a!
match "HTTP/.* 200"
match "{\"q\":\"a!\"\}"

curl -v $base/query?q=a%21
match "HTTP/.* 200"
match "{\"q\":\"a!\"\}"

curl -v $base/query?q=w%C3%B6rd
match "HTTP/.* 200"
match "{\"q\":\"wörd\"\}"

curl -v $base/query?q=a+b
match "HTTP/.* 200"
match "{\"q\":\"a b\"\}"

curl -v $base/query?q=a%20b
match "HTTP/.* 200"
match "{\"q\":\"a b\"\}"

curl -v $base/query?q=a%2Fb
match "HTTP/.* 200"
match "{\"q\":\"a/b\"\}"

curl -v $base/query?q=a%00b
match "HTTP/.* 200"
match "{\"q\":\"a\\\\u0000b\"\}"

curl -v $base/query?q=a\&q=b
match "HTTP/.* 200"
match "{\"q\":\"b\"}"

curl -v $base/query?q%5B%5D=a\&q%5B%5D=b
match "HTTP/.* 200"
match "{\"q\":[[]\"a\",\"b\"[]]}"

# check endpoint accepting single placeholder with special characters and encoding

curl -v $base/users/foo
match "HTTP/.* 200"
match "Hello foo!"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"

curl -v $base/users/w%C3%B6rld
match "HTTP/.* 200"
match "Hello wörld!"

curl -v $base/users/w%F6rld
match "HTTP/.* 200"
match "Hello w�rld!" # demo expects UTF-8 instead of ISO-8859-1

curl -v $base/users/a+b
match "HTTP/.* 200"
match "Hello a+b!"

curl -v $base/users/Wham!
match "HTTP/.* 200"
match "Hello Wham!!"

curl -v $base/users/Wham%21
match "HTTP/.* 200"
match "Hello Wham!!"

curl -v $base/users/AC%2FDC
skipif "HTTP/.* 404" # skip Apache (404 unless `AllowEncodedSlashes NoDecode`)
match "HTTP/.* 200"
match "Hello AC/DC!"

curl -v $base/users/bi%00n
skipif "HTTP/.* 40[04]" # skip nginx (400) and Apache (404)
match "HTTP/.* 200"
match "Hello bi�n!"

curl -v $base/users
match "HTTP/.* 404"

curl -v $base/users/
match "HTTP/.* 404"

curl -v $base/users/a/b
match "HTTP/.* 404"

# check filesystem access

curl -v $base/robots.txt
match "HTTP/.* 200"
match -iP "Content-Type: text/plain[\r\n]"

curl -v $base/source
match -i "Location: /source/"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

curl -v $base/source/
match "HTTP/.* 200"

curl -v $base/source/composer.json
match "HTTP/.* 200"
match -iP "Content-Type: application/json[\r\n]"

curl -v $base/source/public/robots.txt
match "HTTP/.* 200"
match -iP "Content-Type: text/plain[\r\n]"

curl -v $base/source/public/robots.txt/
match -i "Location: ../robots.txt"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

curl -v $base/source/public/robots.txt//
match "HTTP/.* 404"

curl -v $base/source//public/robots.txt
match "HTTP/.* 404"

curl -v $base/source/public
match -i "Location: public/"
match -iP "Content-Type: text/html; charset=utf-8[\r\n]"

curl -v $base/source/invalid
match "HTTP/.* 404"

curl -v $base/source/bin%00ary
match "HTTP/.* 40[40]" # expects 404, but not processed with nginx (400) and Apache (404)

# check different request methods

curl -v $base/method
match "HTTP/.* 200"
match "GET"

curl -v $base/method -I
match "HTTP/.* 200"
match -iP "Content-Length: 5[\r\n]" # HEAD has no response body

curl -v $base/method -X POST
match "HTTP/.* 200"
match "POST"

curl -v $base/method -X PUT
match "HTTP/.* 200"
match "PUT"

curl -v $base/method -X PATCH
match "HTTP/.* 200"
match "PATCH"

curl -v $base/method -X DELETE
match "HTTP/.* 200"
match "DELETE"

curl -v $base/method -X OPTIONS
match "HTTP/.* 200"
match "OPTIONS"

curl -v $base -X OPTIONS --request-target "*" # OPTIONS * HTTP/1.1
skipif "Server: nginx" # skip nginx (400)
match "HTTP/.* 200"

curl -v $base/method/get
match "HTTP/.* 200"
match -iP "Content-Length: 4[\r\n]"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
match -iP "X-Is-Head: false[\r\n]"
match -P "GET$"

curl -v $base/method/get -I
match "HTTP/.* 200"
match -iP "Content-Length: 4[\r\n]"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
match -iP "X-Is-Head: true[\r\n]"

curl -v $base/method/head
match "HTTP/.* 200"
match -iP "Content-Length: 5[\r\n]"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
match -iP "X-Is-Head: false[\r\n]"
match -P "HEAD$"

curl -v $base/method/head -I
match "HTTP/.* 200"
match -iP "Content-Length: 5[\r\n]"
match -iP "Content-Type: text/plain; charset=utf-8[\r\n]"
match -iP "X-Is-Head: true[\r\n]"

# check ETag caching headers

curl -v $base/etag/
match "HTTP/.* 200"
match -iP "Content-Length: 0[\r\n]"
match -iP "Etag: \"_\""

curl -v $base/etag/ -H 'If-None-Match: "_"'
match "HTTP/.* 304"
notmatch -i "Content-Length"
match -iP "Etag: \"_\""

curl -v $base/etag/a
match "HTTP/.* 200"
match -iP "Content-Length: 2[\r\n]"
match -iP "Etag: \"a\""

curl -v $base/etag/a -H 'If-None-Match: "a"'
skipif "Server: Apache" # skip Apache (no Content-Length)
match "HTTP/.* 304"
match -iP "Content-Length: 2[\r\n]"
match -iP "Etag: \"a\""

# check HTTP request headers

curl -v $base/headers -H 'Accept: text/html'
match "HTTP/.* 200"
match "\"Accept\": \"text/html\""

curl -v $base/headers -d 'name=Alice'
match "HTTP/.* 200"
match "\"Content-Type\": \"application/x-www-form-urlencoded\""
match "\"Content-Length\": \"10\""

curl -v $base/headers -u user:pass
match "HTTP/.* 200"
match "\"Authorization\": \"Basic dXNlcjpwYXNz\""

curl -v $base/headers
match "HTTP/.* 200"
notmatch -i "\"Content-Type\""
notmatch -i "\"Content-Length\""

curl -v $base/headers -H User-Agent: -H Accept: -H Host: -10
match "HTTP/.* 200"
match "{}"

curl -v $base/headers -H 'Content-Length: 0'
match "HTTP/.* 200"
match "\"Content-Length\": \"0\""

curl -v $base/headers -H 'Empty;'
match "HTTP/.* 200"
match "\"Empty\": \"\""

curl -v $base/headers -H 'Content-Type;'
skipif "Server: Apache" # skip Apache (discards empty Content-Type)
match "HTTP/.* 200"
match "\"Content-Type\": \"\""

curl -v $base/headers -H 'DNT: 1'
skipif "Server: nginx" # skip nginx which doesn't report original case (DNT->Dnt)
match "HTTP/.* 200"
match "\"DNT\""
notmatch "\"Dnt\""

curl -v $base/headers -H 'V: a' -H 'V: b'
skipif "Server: nginx" # skip nginx (last only) and PHP webserver (first only)
skipifnot "Server:"
match "HTTP/.* 200"
match "\"V\": \"a, b\""

# check HTTP response with multiple cookie headers

curl -v $base/set-cookie
match "HTTP/.* 200"
match "Set-Cookie: 1=1"
match "Set-Cookie: 2=2"

# check rejecting HTTP proxy requests

curl -v --proxy $baseWithPort $base/debug
skipif "Server: nginx" # skip nginx (continues like direct request)
match "HTTP/.* 400"

curl -v --proxy $baseWithPort -p $base/debug
skipif "CONNECT aborted" # skip PHP development server (rejects as "Malformed HTTP request")
match "HTTP/.* 400"

# check HTTP redirects

curl -v $base/location/201
match "HTTP/.* 201"
match "Location: /foobar"

curl -v $base/location/202
match "HTTP/.* 202"
match "Location: /foobar"

curl -v $base/location/301
match "HTTP/.* 301"
match "Location: /foobar"

curl -v $base/location/302
match "HTTP/.* 302"
match "Location: /foobar"

# end

echo "OK ($n)"
