<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimatePhenomenonCell{

    public function __construct(
        private int $x,
        private int $z,
        private float $intensity
    ){}

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getIntensity(): float{return $this->intensity;}

    public function setIntensity(float $intensity): void{$this->intensity = max(0.3, min(1.0, $intensity));}

    // @return array{x:int,z:int,intensity:float}
    public function toArray(): array{
        return ["x" => $this->x,"z" => $this->z,"intensity" => round($this->intensity, 3)];
    }
}