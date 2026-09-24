# Templates

Templates let you render HTML or other text content from separate files, keeping presentation logic out of your request handlers.

## Twig

[Twig](https://twig.symfony.com/) is a template language with strong inheritance and extension support. Framework X integrates with it directly.

Install Twig:

```bash
composer require twig/twig
```

Instantiate the Twig `Environment` and pass it to your controller via dependency injection:

```php title="public/index.php"
<?php

require __DIR__ . '/../vendor/autoload.php';

$loader = new Twig\Loader\FilesystemLoader(__DIR__ . '/../templates');
$twig = new Twig\Environment($loader);

$app = new FrameworkX\App(new FrameworkX\Container([
    Twig\Environment::class => $twig,
]));

$app->get('/', Acme\Todo\HomeController::class);
$app->run();
```

Inside a controller, render a template and return it as HTML:

```php title="src/HomeController.php"
<?php

namespace Acme\Todo;

use Twig\Environment;
use React\Http\Message\Response;

class HomeController
{
    public function __construct(private Environment $twig)
    {
    }

    public function __invoke(): Response
    {
        $html = $this->twig->render('home.html.twig', [
            'title' => 'Welcome to Framework X',
        ]);

        return Response::html($html);
    }
}
```

And the template file:

```html title="templates/home.html.twig"
<!DOCTYPE html>
<html>
<head>
    <title>{{ title }}</title>
</head>
<body>
    <h1>{{ title }}</h1>
</body>
</html>
```

## Other template engines

Framework X works with any PHP template engine:

- **[Plates](https://platesphp.com/)** - Simple, markup-based templates without a new language
- **[Latte](https://latte.nette.org/)** - Nette's template engine with a clean syntax
- **[Mustache](https://github.com/bobthecow/mustache.php)** - Logic-less templates
- **[Handlebars](https://github.com/salesforce/handlebars-php)** - Logic-less templates with a familiar syntax

## Avoid blocking filesystem calls

In the built-in server, the `Twig\Environment` instance persists across requests. Templates compile once and are cached in memory. Don't reload templates from the filesystem inside request handlers. Use Twig's caching instead:

```php
// Good: Twig caches compiled templates
$twig = new Twig\Environment($loader, [
    'cache' => __DIR__ . '/../cache/twig',
]);

// Avoid: loading template files on every request
$content = file_get_contents(__DIR__ . '/../templates/home.html');
```

In development, disable caching so template changes show up immediately:

```php
$twig = new Twig\Environment($loader, [
    'cache' => false,
]);
```

## See also

- [Filesystem](filesystem.md) - async filesystem operations for other use cases
- [Response](../api/response.md) - response types and formatting
- [Middleware](../api/middleware.md) - share a template engine across routes
