<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateHumidityCell{

    public function __construct(
        private int $x,
        private int $z,
        private float $humidity
    ){}

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getHumidity(): float{return $this->humidity;}
}