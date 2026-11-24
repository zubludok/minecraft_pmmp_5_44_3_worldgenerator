<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class WindState{

    public function __construct(
        private float $direction,
        private float $strength
    ){
        if($direction < 0.0 || $direction >= 360.0){
            throw new InvalidArgumentException("Wind direction must be finite and between 0 and 360 degrees.");
        }
        if($strength < 0.0 || $strength > 10.0){
            throw new InvalidArgumentException("Wind strength must be finite and between 0 and 10.");
        }
    }

    public function getDirection(): float{return $this->direction;}
    public function getStrength(): float{return $this->strength;}
}