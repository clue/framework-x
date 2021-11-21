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

*We've got you covered!*

### PHP development web server

For example, if you've followed the [quickstart guide](../getting-started/quickstart.md),
you can run this using PHP's built-in development web server for testing
purposes like this:

```bash
$ php -S 0.0.0.0:8080 public/index.php
```

In order to check your web application responds as expected, you can use your
favorite web browser or command-line tool:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

### nginx

nginx is a high performance web server, load balancer and reverse proxy. In
particular, its high performance and versatility makes it one of the most
popular web servers. It is used everywhere from the smallest projects to the
biggest enterprises.

X supports nginx out of the box. If you've used nginx before to run any PHP
application, using nginx with X is as simple as dropping the project files in
the right directory. Accordingly, this guide assumes you want to process a
number of [dynamic routes](../api/app.md#routing) through X and optionally
include some public assets (such as style sheets and images).

Assuming you've followed the [quickstart guide](../getting-started/quickstart.md),
all you need to do is to point the nginx' [`root`](http://nginx.org/en/docs/http/ngx_http_core_module.html#root)
("docroot") to the `public/` directory of your project. On top of this, you'll need
to instruct nginx to process any dynamic requests through X. This can be
achieved by using an nginx configuration with the following contents:

```
server {
    root /home/alice/projects/acme/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ \.php$ {
        fastcgi_pass localhost:9000;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

> ℹ️ **New to nginx?**
>
> A complete nginx configuration is out of scope for this guide, so we assume
> you already have nginx and PHP with PHP-FPM up and running. In this example,
> we're assuming PHP-FPM is already up and running and listens on `localhost:9000`,
> consult your search engine of choice for basic install instructions. Once this
> is set up, the above guide should be everything you need to then use X.
>
> We recommend using the above nginx configuration as a starting point if you're
> unsure. In this basic form, it instructs nginx to rewrite any requests for
> files that do not exist to your `public/index.php` which then processes any
> requests by checking your [registered routes](../api/app.md#routing).

Once done, you can check your web application responds as expected. Use your
favorite web browser or command-line tool:

```bash
$ curl http://localhost/
Hello wörld!
```

### Apache

The Apache HTTP server (httpd) is one of the most popular web servers. In
particular, it is a very common choice for hosts that run multiple web
applications (such as shared hosting providers) due to its ease of use and
support for dynamic configuration through `.htaccess` files.

X supports Apache out of the box. If you've used Apache before to run any PHP
application, using Apache with X is as simple as dropping the project files in
the right directory. Accordingly, this guide assumes you want to process a
number of [dynamic routes](../api/app.md#routing) through X and optionally
include some public assets (such as style sheets and images).

Assuming you've followed the [quickstart guide](../getting-started/quickstart.md),
all you need to do is to point the Apache's [`DocumentRoot`](http://httpd.apache.org/docs/2.4/de/mod/core.html#documentroot)
("docroot") to the `public/` directory of your project. On top of this, you'll need
to instruct Apache to rewrite dynamic requests so they will be processed by X.
Inside your `public/` directory, create an `.htaccess` file (note the leading `.` which
makes this a hidden file) with the following contents:

```
# public/.htaccess
RewriteEngine On

RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule .* index.php
```

> ℹ️ **New to mod_rewrite?**
>
> We recommend using the above `.htaccess` file as a starting point if you're
> unsure. In this basic form, it instructs Apache to rewrite any requests for
> files that do not exist to your `public/index.php` which then processes any
> requests by checking your [registered routes](../api/app.md#routing). This
> requires the [`mod_rewrite`](https://httpd.apache.org/docs/2.4/mod/mod_rewrite.html)
> Apache module, which should be enabled by default on most platforms. On Ubuntu-
> or Debian-based systems, you may enable it like this:
>
> ```bash
> $ sudo a2enmod rewrite
> ```

Once done, your project directory should now look like this:

```
acme/
├── public/
│   ├── .htaccess
│   └── index.php
├── vendor/
├── composer.json
└── composer.lock
```

If you're not already running an Apache server, you can run your X project with
Apache in a temporary Docker container like this:

```bash
$ docker run -it --rm -p 80:80 -v "$PWD":/srv php:8.0-apache sh -c "rmdir /var/www/html;ln -s /srv/public /var/www/html;ln -s /etc/apache2/mods-available/rewrite.load /etc/apache2/mods-enabled; apache2-foreground"
```

In order to check your web application responds as expected, you can use your
favorite web browser or command-line tool:

```bash
$ curl http://localhost/
Hello wörld!
```

## Built-in web server

But there's more!
Framework X ships its own efficient web server implementation written in pure PHP.
This uses an event-driven architecture to allow you to get the most out of Framework X.
With the built-in web server, we provide a non-blocking implementation that can handle thousands of incoming connections and provide a much better user experience in high-load scenarios.

With no changes required, you can run the built-in web server with the exact same code base on the command line:

```bash
$ php public/index.php
```

Let's take a look and see this works just like before:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

You may be wondering how fast a pure PHP web server implementation could possibly be.
In fact, in benchmarks this setup outperforms any traditional PHP stack by orders of magnitude.
The answer: [Lightning fast!](https://framework-x.clue.engineering/#lightning-fast)

### Listen address

By default, X will listen on `http://127.0.0.1:8080`, i.e. you can connect to it on
the local port `8080`, but you can not connect to it from outside the system it's
running on. This is a common approach when running this behind a reverse proxy
such as nginx, HAproxy, etc. for TLS termination as discussed in the next chapter.

If you want to change the listen address, you can pass an IP and port
combination through the `X_LISTEN` environment variable like this:

```bash
$ X_LISTEN=127.0.0.1:8081 php public/index.php
```

While not usually recommended, you can also expose this to the public by using
the special `0.0.0.0` IPv4 address or `[::]` IPv6 address like this:

```bash
$ X_LISTEN=0.0.0.0:8080 php public/index.php
```

### Systemd

So far, we're manually executing the application server on the command line and
everything works fine for testing purposes. Once we're going to push this to
production, we should use service monitoring to make sure the server will
automatically restart after system reboot or failure.

If we're using an Ubuntu- or Debian-based system, we can use the below
instructions to configure `systemd` to manage our server process with just a
few lines of configuration, which makes it super easy to run X in production.

> ℹ️ **Why systemd?**
>
> There's a large variety of different tools and options to use for service
> monitoring, depending on your particular needs. Among these is `systemd`, which
> is very wide-spread on Linux-based systems and in fact comes preinstalled with
> many of the large distributions. But we love choice. If you prefer different
> tools, you can adjust the following instructions to suite your needs.

First, start by creating a systemd unit file for our application. We can simply
drop the following configuration template into the systemd configuration
directory like this:

```bash
$ sudoedit /etc/systemd/system/acme.service
```

```
[Unit]
Description=ACME server

[Service]
ExecStart=/usr/bin/php /home/alice/projects/acme/public/index.php
User=alice

[Install]
WantedBy=multi-user.target
```

In this example, we're assuming the system user `alice` has followed the
[quickstart example](../getting-started/quickstart.md) and has successfully
installed everything in the `/home/alice/projects/acme` directory. Make sure to
adjust the system user and paths to your application directory and PHP binary
to suite your needs.

Once the new systemd unit file has been put in place, we need to activate the
service unit once like this:

```bash
$ sudo systemctl enable acme.service
```

Finally, we need to instruct systemd to start our new unit:

```bash
$ sudo systemctl start acme.service
```

And that's it already! Systemd now monitors our application server and will
automatically start, stop and restart the server application when needed. You
can check the status at any time like this:

```bash
$ sudo systemctl status acme.service
● acme.service - ACME server
     Loaded: loaded (/etc/systemd/system/acme.service; enabled; vendor preset: enabled)
     Active: active (running)
[…]
```

This should be enough to get you started with systemd. If you want to learn more
about systemd, check out the
[official documentation](https://www.freedesktop.org/software/systemd/man/systemd.service.html).

### Docker containers

> ⚠️ **Documentation still under construction**
>
> You're seeing an early draft of the documentation that is still in the works.
> Give feedback to help us prioritize.
> We also welcome [contributors](../more/community.md) to help out!

### More

If you're going to use this in production, we still recommend running this
behind a reverse proxy such as nginx, HAproxy, etc. for TLS termination
(HTTPS support).
