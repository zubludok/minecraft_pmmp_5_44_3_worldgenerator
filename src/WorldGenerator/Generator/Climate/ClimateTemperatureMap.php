<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateTemperatureMap{

    private int $sizeX;
    private int $sizeZ;

    // @var array<int, array<int, ClimateTemperatureCell|null>>
    private array $cells = [];
    public function __construct(
		int $sizeX, 
		int $sizeZ
	){
		$this->sizeX = $sizeX;
		$this->sizeZ = $sizeZ;
	}
	
    public function set(ClimateTemperatureCell $cell): void{$this->cells[$cell->getX()][$cell->getZ()] = $cell;}
    public function get(int $x, int $z): ?ClimateTemperatureCell{return $this->cells[$x][$z] ?? null;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
}