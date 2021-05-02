# Production deployment

One of the nice properties of X is that it **runs anywhere**, i.e. it works both
behind traditional web server setups as well as in a stand-alone environment.
This makes it easy to get started with existing web application stacks, yet it
provides even more awesome features with its built-in web server.

## Traditional stacks

No matter what existing PHP stack you're using, X runs anywhere.
This means that if you've already used PHP before, X will *just work*.

* nginx with PHP-FPM
* Apache with PHP-FPM, mod_fcgid, mod_cgi or mod_php
* Any other web server using FastCGI to talk to PHP-FPM
* Linux, Mac and Windows operating systems (<abbrev title="Apache, MySQL or MariaDB, PHP, on Linux, Mac or Windows operating systems">LAMP, MAMP, WAMP</abbrev>)

*We've got you covered.*

For example, if you've followed the [quickstart guide](../getting-started/quickstart.md), you can run this using PHP's built-in development web
server for testing purposes like this:

```bash
$ php -S 0.0.0.0:8080 app.php
```

In order to check your web application responds as expected, you can use your favorite webbrowser or command line tool:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

## Built-in web server

But there's more!
Framework X ships its own efficient web server implementation written in pure PHP.
This uses an event-driven architecture to allow you to get the most out of Framework X.
With the built-in web server, we provide a non-blocking implementation that can handle thousands of incoming connections and provide a much better user experience in high-load scenarios.

With no changes required, you can run the built-in web server with the exact same code base on the command line:

```bash
$ php app.php
```

Let's take a look and see this works just like before:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

You may be wondering how fast a pure PHP web server implementation could possibly be.

```
$ ab -n10000 -c10 http://localhost:8080/
…
Concurrency Level:      10
Time taken for tests:   0.991 seconds
Complete requests:      10000
Failed requests:        0
Total transferred:      1090000 bytes
HTML transferred:       130000 bytes
Requests per second:    10095.17 [#/sec] (mean)
Time per request:       0.991 [ms] (mean)
Time per request:       0.099 [ms] (mean, across all concurrent requests)
Transfer rate:          1074.58 [Kbytes/sec] received
```

The answer: Very fast!

If you're going to use this in production, we still recommend running this
behind a reverse proxy such as nginx, HAproxy, etc. for TLS termination
(HTTPS support).

Additionally, you should use service monitoring to make sure the server will
automatically restart after system reboot or failure. Docker containers or
systemd unit files would be common solutions here.
