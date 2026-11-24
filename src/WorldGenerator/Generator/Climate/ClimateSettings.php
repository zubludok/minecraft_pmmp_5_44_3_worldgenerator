<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateSettings{

    public function __construct(
        private float $polarTemperature,
        private float $equatorTemperature,
        private int $polarShift
    ){
        $this->setPolarTemperature($polarTemperature);
        $this->setEquatorTemperature($equatorTemperature);
        $this->setPolarShift($polarShift);
    }

    public function getPolarTemperature(): float{return $this->polarTemperature;}

    public function setPolarTemperature(float $temperature): void{
        if(!is_finite($temperature)){
            throw new InvalidArgumentException("Equator temperature must be a finite number.");
        }
        $this->polarTemperature = $temperature;
    }

    public function getEquatorTemperature(): float{return $this->equatorTemperature;}

    public function setEquatorTemperature(float $temperature): void{
        if(!is_finite($temperature)){
            throw new InvalidArgumentException("Polar temperature must be a finite number.");
        }
        $this->equatorTemperature = $temperature;
    }

    public function getPolarShift(): int{return $this->polarShift;}
    public function setPolarShift(int $polarShift): void{$this->polarShift = $polarShift;}
}