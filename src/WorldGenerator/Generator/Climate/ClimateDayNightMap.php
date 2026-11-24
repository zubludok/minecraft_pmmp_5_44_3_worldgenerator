<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateDayNightMap{

    // @var array<int, array<int, ClimateDayNightCell>>
    private array $cells = [];

    public function __construct(
        private int $sizeX,
        private int $sizeZ
    ){}

    public function set(ClimateDayNightCell $cell): void{$this->cells[$cell->getZ()][$cell->getX()] = $cell;}
    public function get(int $x, int $z): ?ClimateDayNightCell{return $this->cells[$z][$x] ?? null;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
}