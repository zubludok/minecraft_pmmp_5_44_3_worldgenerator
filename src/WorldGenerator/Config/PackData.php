<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

final class PackData{
	public function toArray(): array{return[];}
	public static function fromArray(array $data): self{return new self();}
}