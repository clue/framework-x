# Quickstart in 5 minutes

Getting started with X is easy!
You can use this example to get started with a new `app.php` file:

```php
<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$app = new Frugal\App($loop);

$app->get('/', function () {
    return new React\Http\Message\Response(
        200,
        [],
        "Hello wörld!\n"
    );
});

$loop->run();
```

That's it already!
The next step is now to install its dependencies and serve this web application.
