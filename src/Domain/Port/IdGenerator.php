<?php

namespace Core\Domain\Port;

interface IdGenerator { public function uuid(): string; }