<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateSeasonalCell{

    public function __construct(
        private int $x,
        private int $z,
        private float $temperature,
        private float $windCoefficient,
        private float $windDirection
    ){}

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getTemperature(): float{return $this->temperature;}
    public function getWindCoefficient(): float{return $this->windCoefficient;}
    public function getWindDirection(): float{return $this->windDirection;}
}