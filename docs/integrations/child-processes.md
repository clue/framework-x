# Parallel processing with child processes

> ⚠️ **Documentation still under construction**
>
> You're seeing an early draft of the documentation that is still in the works.
> Give feedback to help us prioritize.
> We also welcome [contributors](../getting-started/community.md) to help out!

* Avoid blocking by moving blocking implementation to child process
* Child process I/O for communication
* Multithreading, but isolated processes
* See [reactphp/child-process](https://reactphp.org/child-process/) for underlying APIs
* See [clue/reactphp-pq](https://github.com/clue/reactphp-pq) for higher-level API to automatically wrap blocking functions in an async child process and turn blocking functions into non-blocking [promises](../async/promises.md)
