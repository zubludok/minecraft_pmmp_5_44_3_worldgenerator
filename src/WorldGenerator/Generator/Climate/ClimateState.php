<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateState{

    public function __construct(
        private float $temperature,
        private WindState $wind
    ){}

    public function getTemperature(): float{return $this->temperature;}
    public function getWind(): WindState{return $this->wind;}
}