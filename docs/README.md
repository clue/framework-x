# Framework X documentation

This folder contains the source documentation files for Framework X.

You can see the [website](https://framework-x.clue.engineering/) for the
user-accessible version.

## Contribute

Found a typo or want to contribute?
This folder contains everything you need to help out with the documentation.

We use [mkdocs-material](https://squidfunk.github.io/mkdocs-material/) to
render our documentation to a pretty HTML version.

If you want to contribute to the documentation, it's easiest to just run
this in a Docker container in the project root directory like this:

```bash
$ docker run --rm -it -p 8000:8000 -v ${PWD}:/docs squidfunk/mkdocs-material
```

You can access the documentation via `http://localhost:8000`.
Props to mkdocs, this uses live reloading, so that every change you do while
this Docker container is running should immediately be reflected in your web
browser.

If you want to generate a static HTML folder for deployment, you can again
use a Docker container in the project root directory like this:

```bash
$ docker run --rm -it -v ${PWD}:/docs squidfunk/mkdocs-material build
```

The resulting `build/docs/` should then be deployed behind a web server.
See also the [Framework X website repository](https://github.com/clue/framework-x-website)
for more details about the website itself.

If you want to add a new documentation file and/or change the page order, make sure the [`mkdocs.yml`](../mkdocs.yml)
file in the project root directory contains an up-to-date list of all pages.

Happy hacking!
