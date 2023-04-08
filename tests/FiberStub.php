<?php

if (!class_exists(Fiber::class)) {
    class Fiber
    {
        /** @var callable */
        private $callback;

        /** @var mixed */
        private $return;

        /** @var bool */
        private $started = false;

        /** @var bool */
        private $terminated = false;

        /** @var bool */
        private static $halt = false;

        /** @var ?Fiber */
        private static $suspended = null;

        public function __construct(callable $callback)
        {
            $this->callback = $callback;
        }

        /**
         * @param mixed ...$args
         * @return mixed
         * @throws \Throwable
         */
        public function start(...$args)
        {
            if ($this->started) {
                throw new \FiberError();
            }
            $this->started = true;

            if (self::$halt) {
                assert(self::$suspended === null);
                self::$suspended = $this;
                return null;
            }

            try {
                return $this->return = ($this->callback)(...$args);
            } finally {
                $this->terminated = true;
            }
        }

        /**
         * @param mixed $value
         * @return mixed
         * @throws \BadMethodCallException
         */
        public function resume($value = null)
        {
            throw new \BadMethodCallException();
        }

        /**
         * @param Throwable $exception
         * @return mixed
         * @throws \BadMethodCallException
         */
        public function throw(Throwable $exception)
        {
            throw new \BadMethodCallException();
        }

        /**
         * @return mixed
         * @throws FiberError
         */
        public function getReturn()
        {
            if (!$this->terminated) {
                throw new \FiberError();
            }

            return $this->return;
        }

        public function isStarted(): bool
        {
            return $this->started;
        }

        public function isSuspended(): bool
        {
            return false;
        }

        public function isRunning(): bool
        {
            return $this->started && !$this->terminated;
        }

        public function isTerminated(): bool
        {
            return $this->terminated;
        }

        /**
         * @param mixed $value
         * @return mixed
         * @throws \Throwable
         */
        public static function suspend($value = null)
        {
            throw new \BadMethodCallException();
        }

        public static function getCurrent(): ?Fiber
        {
            return null;
        }

        /**
         * @internal
         */
        public static function mockSuspend(): void
        {
            assert(self::$halt === false);
            self::$halt = true;
        }

        /**
         * @internal
         * @throws void
         */
        public static function mockResume(): void
        {
            assert(self::$halt === true);
            assert(self::$suspended instanceof self);

            $fiber = self::$suspended;
            assert($fiber->started);
            assert(!$fiber->terminated);

            self::$halt = false;
            self::$suspended = null;

            /** @throws void */
            $fiber->return = ($fiber->callback)();
            $fiber->terminated = true;
        }
    }

    final class FiberError extends Error {

    }
}
