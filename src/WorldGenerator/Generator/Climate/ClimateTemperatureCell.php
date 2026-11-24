<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateTemperatureCell{

    public function __construct(
        private int $x,
        private int $z,
        private float $temperature
    ){}

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getTemperature(): float{return $this->temperature;}
}