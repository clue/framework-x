### Controller classes to structure your app

Once everything is up and running, we can take a look at how to best structure
our actual web application.

To get started, it's often easiest to start with simple closure definitions
like the following:

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

$app->get('/users/{name}', function (Psr\Http\Message\ServerRequestInterface $request) {
    return new React\Http\Message\Response(
        200,
        [],
        "Hello " . $request->getAttribute('name') . "!\n"
    );
});

$loop->run();
```

While easy to get started, this will easily get out of hand for more complex
business domains when you have more than a couple of routes registered.

For real-world applications, we highly recommend structuring your application
into invidividual controller classes. This way, we can break up the above
definition into three even simpler files:

```php
# main.php
<?php

require __DIR__ . '/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();
$app = new Frugal\App($loop);

$app->get('/', new Acme\Todo\HelloController());
$app->get('/users/{name}', new Acme\Todo\UserController());

$loop->run();
```

```php
# src/HelloController.php
<?php

namespace Acme\Todo;

use React\Http\Message\Response;

class HelloController
{
    public function __invoke()
    {
        return new Response(
            200,
            [],
            "Hello wörld!\n"
        );
    }
}
```

```php
# src/UserController.php
<?php

namespace Acme\Todo;

use Psr\Http\Message\ServerRequestInterface;
use React\Http\Message\Response;

class UserController
{
    public function __invoke(ServerRequestInterface $request)
    {
        return new Response(
            200,
            [],
            "Hello " . $request->getAttribute('name') . "!\n"
        );
    }
}
```

Doesn't look too complex, right? Now, we only need to tell Composer's autoloader
about our vendor namespace `Acme\\Todo` in the `src/` folder. Make sure to include
the following lines in your `composer.json` file:

```json
{
    "autoload": {
        "psr-4": {
            "Acme\\Todo\\": "src/"
        }
    }
}
```

When we're doing this the first time, we have to update Composer's generated
autoloader classes:

```bash
$ composer dump-autoload
```

Don't worry, that's a one-time setup only. If you're used to working with
Composer, this shouldn't be too surprising. If this sounds new to you, rest
assured this is the only time you have to worry about this, new classes can
simply be added without having to run Composer again.

Again, let's see our web application still works by using your favorite
webbrowser or command line tool:

```bash
$ curl -v http://localhost:8080/
HTTP/1.1 200 OK
…

Hello wörld!
```
