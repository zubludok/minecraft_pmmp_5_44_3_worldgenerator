<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateBaseMap{

    /** @var array<int,array<int,ClimateBaseCell>> */
    private array $cells=[];

    public function __construct(
        private int $sizeX,
        private int $sizeZ
    ){}

    public function set(ClimateBaseCell $cell): void{$this->cells[$cell->getX()][$cell->getZ()]=$cell;}
    public function get(int $x,int $z): ?ClimateBaseCell{return $this->cells[$x][$z]??null;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
}