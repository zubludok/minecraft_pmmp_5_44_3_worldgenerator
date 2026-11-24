<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimatePhenomenonRegion{

    // @var array<string, ClimatePhenomenonCell>
    private array $cells = [];

    private float $centerX = 0.0;
    private float $centerZ = 0.0;

    // @param array<array{x:int,z:int}> $cells
    public function __construct(
        private int $id,
        private string $phenomenon,
        private string $season,
        private string $yearType,
        private string $hemisphere,
        array $cells
    ){
        foreach($cells as $cell){
            $this->addCell($cell["x"], $cell["z"]);
        }

        $this->recalculateIntensity();
    }

    public function getId(): int{return $this->id;}
    public function getPhenomenon(): string{return $this->phenomenon;}
    public function getSeason(): string{return $this->season;}
    public function getYearType(): string{return $this->yearType;}
    public function getHemisphere(): string{return $this->hemisphere;}
    public function getTotalCells(): int{return count($this->cells);}
    public function getCenterX(): float{return $this->centerX;}
    public function getCenterZ(): float{return $this->centerZ;}

    // @return array<string, ClimatePhenomenonCell>
    public function getCells(): array{return $this->cells;}
    public function hasCell(int $x, int $z): bool{return isset($this->cells[$this->key($x, $z)]);}

    public function addCell(int $x, int $z): bool{
        $key = $this->key($x, $z);

        if(isset($this->cells[$key])){
            return false;
        }

        $this->cells[$key] = new ClimatePhenomenonCell($x, $z, 1.0);
        return true;
    }

    public function recalculateIntensity(): void{
        if($this->cells === []){
            $this->centerX = 0.0;
            $this->centerZ = 0.0;
            return;
        }

        $sumX = 0.0;
        $sumZ = 0.0;

        foreach($this->cells as $cell){
            $sumX += $cell->getX();
            $sumZ += $cell->getZ();
        }

        $count = count($this->cells);
        $this->centerX = $sumX / $count;
        $this->centerZ = $sumZ / $count;

        $distances = [];
        $radius = 0.0;

        foreach($this->cells as $key => $cell){
            $dx = $cell->getX() - $this->centerX;
            $dz = $cell->getZ() - $this->centerZ;
            $distance = sqrt($dx * $dx + $dz * $dz);
            $distances[$key] = $distance;
            $radius = max($radius, $distance);
        }

        foreach($this->cells as $key => $cell){
            $intensity = $radius > 0.0 ? 1.0 - ($distances[$key] / $radius) * 0.7 : 1.0;
            $cell->setIntensity($intensity);
        }
    }

    /** @return array<string, mixed> */
    public function toArray(): array{
        $cells = [];

        foreach($this->cells as $cell){
            $cells[] = $cell->toArray();
        }

        return [
            "id" => $this->id,
            "phenomenon" => $this->phenomenon,
            "season" => $this->season,
            "yearType" => $this->yearType,
            "hemisphere" => $this->hemisphere,
            "totalCells" => count($this->cells),
            "center" => [
                "x" => round($this->centerX, 3),
                "z" => round($this->centerZ, 3)
            ],
            "cells" => $cells
        ];
    }

    private function key(int $x, int $z): string{return $x . ":" . $z;}
}