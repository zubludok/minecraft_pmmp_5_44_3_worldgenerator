<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateSeasonalMap{

    /** @var array<int, array<int, ClimateSeasonalCell>> */
    private array $cells = [];

    public function __construct(
        private int $sizeX,
        private int $sizeZ
    ){}

    public function set(ClimateSeasonalCell $cell): void{$this->cells[$cell->getX()][$cell->getZ()] = $cell;}
    public function get(int $x, int $z): ?ClimateSeasonalCell{return $this->cells[$x][$z] ?? null;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
}