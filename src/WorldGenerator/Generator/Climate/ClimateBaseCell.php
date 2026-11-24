<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateBaseCell{

    public function __construct(
        private int $x,
        private int $z,
        private float $temperature,
        private float $windCoefficient
    ){}

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}

    public function getTemperature(): float{return $this->temperature;}
    public function setTemperature(float $value): void{$this->temperature=$value;}

    public function getWindCoefficient(): float{return $this->windCoefficient;}
    public function setWindCoefficient(float $value): void{$this->windCoefficient=max(0.0,min(1.0,$value));}
}