<?php

namespace Dviluk\LaravelSimpleCrud\Contracts;

interface ErrorResponseInterface
{
    public function getMessage();
    public function getResponse(): string;
    public function getStatus(): int;
    public function getExtra(): array;
}
