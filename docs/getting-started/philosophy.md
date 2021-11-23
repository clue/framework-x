# Our philosophy

## What drives us

Framework X is so much more than *yet another framework*. Here's what drives
us and how we make decisions for the framework.

* **make easy things easy & hard things possible**

    Making easy things easy is one of our leading mottos. If making the hard thing
    possible involves making the easy thing hard, we would rather focus on the easy
    thing.

* **From quick prototyping RAD to production environments in hours**

    Get started in minutes with a <abbrev title="Rapid Application Development">RAD</abbrev> prototype!
    With X, you can get from prototypes to production in hours, not weeks.

* **Batteries included, but swappable**

    X provides everything you need to get started. We use a very composable
    architecture, so all the parts are swappable in case you need custom
    integrations.

* **Reuse where applicable, but accept some duplication**

    Code reuse is great! But if applying <abbrev title="Don't repeat yourself">DRY</abbrev>
    involves too many abstractions, we would rather accept some duplication. We value simplicity
    as a core design principle.

* **Long-term support (LTS) and careful upgrade paths**

    We're committed to providing long-term support (LTS) options and providing a smooth upgrade
    path between versions. We want to be the rock-solid foundation that you can build on top of.

* **Promote best practices, but don't enforce certain style**

    We like <abbrev title="Domain-Driven Design">DDD</abbrev>, <abbrev title="Test-Driven Development">TDD</abbrev>,
    and more. If you don't, that's fine, we like choice. While we encourage following best
    practices and try to give recommendations, we don't enforce a certain style.

* **Runs anywhere**

    We support the latest versions but we only require PHP 7.1+ for maximum compatibility to
    ensure X runs anywhere. From shared hosting to cloud-native!

* **Open and inclusive community**

    Framework X is so much more than the sum of its parts. In particular, see our awesome [community](community.md).

## Architecture

* **HTTP request response semantics**

    Framework X is all about handling [HTTP requests](../api/request.md) and sending back
    [ HTTP responses](../api/response.md).

* **PHP runs everywhere**

    We know, PHP has its quirks. But it also provides a unique opportunity with its huge ecosystem
    that allows you to run X literally anywhere.

* **shared-nothing execution (optional)**

    We support PHP's default shared-nothing execution model when running with
    [traditional stacks](../best-practices/deployment.md#traditional-stacks).

* **built-in web server (optional)**

    If you're ready, get even more awesome features with its
    [built-in web server](../best-practices/deployment.md#built-in-web-server).

* **Async PHP**

    We're standing on the shoulders of giants. Thank you [ReactPHP](https://reactphp.org/) for
    providing an awesome foundation!
