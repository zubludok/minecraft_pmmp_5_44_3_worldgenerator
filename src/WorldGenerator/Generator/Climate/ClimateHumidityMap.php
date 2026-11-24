<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateHumidityMap{

    // @var array<int, array<int, ClimateHumidityCell>>
    private array $cells = [];

    public function __construct(
        private int $sizeX,
        private int $sizeZ,
        private string $yearType,
        private string $season
    ){}

    public function set(ClimateHumidityCell $cell): void{$this->cells[$cell->getX()][$cell->getZ()] = $cell;}
    public function get(int $x, int $z): ?ClimateHumidityCell{return $this->cells[$x][$z] ?? null;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
    public function getYearType(): string{return $this->yearType;}
    public function getSeason(): string{return $this->season;}
}