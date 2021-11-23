# Queueing

> ⚠️ **Documentation still under construction**
>
> You're seeing an early draft of the documentation that is still in the works.
> Give feedback to help us prioritize.
> We also welcome [contributors](../getting-started/community.md) to help out!

* Common requirement to offload work from frontend to background workers
* Major queue vendors supported already
    * [BunnyPHP](https://github.com/jakubkulhan/bunny) for AMQP (RabbitMQ)
    * [Redis](https://github.com/clue/reactphp-redis) with blocking lists and streams
    * Experimental [STOMP](https://github.com/friends-of-reactphp/stomp) support for RabbitMQ, Apollo, ActiveMQ, etc.
* Future optionally built-in queueing support with no external dependencies, but swappable
