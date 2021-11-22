# Controller classes to structure your app

When starting with X, it's often easiest to start with simple closure definitions like suggested in the [quickstart guide](../getting-started/quickstart.md).

As a next step, let's take a look at how this structure can be improved with controller classes.
This is especially useful once you leave the prototyping phase and want to find the best structure for a production-ready setup.

To get started, let's take a look at the following simple closure definitions:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

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

$app->run();
```

While easy to get started, it's also easy to see how this will get out of hand for more complex
business domains when you have more than a couple of routes registered.

For real-world applications, we highly recommend structuring your application
into invidividual controller classes. This way, we can break up the above
definition into three even simpler files:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$app = new FrameworkX\App();

$app->get('/', new Acme\Todo\HelloController());
$app->get('/users/{name}', new Acme\Todo\UserController());

$app->run();
```

```php title="src/HelloController.php"
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

```php title="src/UserController.php"
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
about our vendor namespace `Acme\Todo` in the `src/` folder. Make sure to include
the following lines in your `composer.json` file:

```json title="composer.json"
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

> ℹ️ **New to Composer?**
>
> Don't worry, that's a one-time setup only. If you're used to working with
Composer, this shouldn't be too surprising. If this sounds new to you, rest
assured this is the only time you have to worry about this, new classes can
simply be added without having to run Composer again.

Again, let's see our web application still works by using your favorite
webbrowser or command line tool:

```bash
$ curl http://localhost:8080/
Hello wörld!
```

If everything works as expected, we can continue with writing our first tests to automate this.
