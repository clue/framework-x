# Installation

First manually change your `composer.json` to include these lines:

```json
{
    …,
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/clue-engineering/frugal"
        }
    ]
}
```

> TODO: Change package name and register on Packagist.

Then, simply install:

```bash
$ composer require clue/frugal:dev-main
```

> TODO: Tagged release.
