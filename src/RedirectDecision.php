<?php

namespace Noo\CraftLocaleRedirect;

final readonly class RedirectDecision
{
    public function __construct(
        public string $url,
        public int $statusCode,
        public bool $varyByLocale,
    ) {}
}
