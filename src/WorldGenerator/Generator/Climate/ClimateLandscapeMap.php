<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateLandscapeMap{

    private int $sizeX;
    private int $sizeZ;

    // @var ClimateLandscapeCell[][]
    private array $cells=[];

    public function __construct(int $sizeX,int $sizeZ){
        $this->sizeX=$sizeX;
        $this->sizeZ=$sizeZ;
    }

    public function set(ClimateLandscapeCell $cell): void{$this->cells[$cell->getX()][$cell->getZ()]=$cell;}
    public function get(int $x,int $z): ?ClimateLandscapeCell{return $this->cells[$x][$z] ?? null;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}

    // @return ClimateLandscapeCell[]
    public function getNeighbours(int $x, int $z): array{
        $result = [];
        foreach([
            [-1, -1], [0, -1], [1, -1],
            [-1,  0],          [1,  0],
            [-1,  1], [0,  1], [1,  1],
        ] as [$dx, $dz]){
            $cell = $this->get($x + $dx, $z + $dz);
            if($cell !== null){
                $result[] = $cell;
            }
        }
        return $result;
    }

}