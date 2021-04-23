# Deployment

One of the nice properties of this project is that is works both behind
traditional web server setups as well as in a stand-alone environment.

For example, you can run the above example using PHP's built-in webserver for
testing purposes like this:

```bash
$ php -S 0.0.0.0:8080 app.php
```

You can now use your favorite webbrowser or command line tool to check your web
application responds as expected:

```bash
$ curl -v http://localhost:8080/
HTTP/1.1 200 OK
…

Hello wörld!
```

Runs everywhere:

* Built-in webserver
* nginx with PHP-FPM
* Apache with mod_fcgid and PHP-FPM
* Apache with mod_php
* PHP's development webserver

> TODO: Show different deployment scenarios
