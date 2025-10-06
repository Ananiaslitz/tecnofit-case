<?php

namespace Core\Adapter\Out\Observability;

final class RichEvent
{
    public function __construct(
        public string $name,
        public string $version,
        public string $level,
        public string $message,
        public array  $attrs = [],
        public array  $meta  = [],
        public ?\DateTimeImmutable $ts = null
    ) { $this->ts ??= new \DateTimeImmutable(); }

    public function toArray(): array
    {
        return [
            'ts'      => $this->ts->format('c'),
            'event'   => ['name' => $this->name, 'version' => $this->version, 'level' => $this->level, 'message' => $this->message],
            'attrs'   => $this->attrs,
            'meta'    => $this->meta,
        ];
    }
}
