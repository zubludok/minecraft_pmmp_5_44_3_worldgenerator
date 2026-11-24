<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimatePhenomenon{

    /* @param array<string, mixed> $conditions */
    public function __construct(
        private string $type,
        private array $conditions = []
    ){}

    public function getType(): string{return $this->type;}

    /* @return array<string, mixed> */
    public function getConditions(): array{return $this->conditions;}
}