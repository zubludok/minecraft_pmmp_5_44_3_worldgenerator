<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateTemperatureGradientGenerator{

    /* Создаёт базовую температурную карту.
     * polarShift:
     *   0  — симметричный градиент;
     *  <0  — расширение тёплой зоны на север;
     *  >0  — расширение тёплой зоны на юг.
     * Географический экватор остаётся в двух центральных рядах. */
    public function generate(
        int $sizeX,
        int $sizeZ,
        float $polarTemperature,
        float $equatorTemperature,
        int $polarShift
    ): ClimateTemperatureMap{
        $this->validate($sizeX,$sizeZ,$polarTemperature,$equatorTemperature);
        $map = new ClimateTemperatureMap($sizeX, $sizeZ);
        $northEquatorZ = intdiv($sizeZ, 2) - 1;
        $southEquatorZ = intdiv($sizeZ, 2);
        $northGradientEnd = $northEquatorZ;
        $southGradientEnd = $southEquatorZ;
        if($polarShift < 0){
            $northGradientEnd = max(0,$northEquatorZ + $polarShift);
        }elseif($polarShift > 0){
            $southGradientEnd = min($sizeZ - 1,$southEquatorZ + $polarShift);
        }
        for($z = 0; $z < $sizeZ; $z++){
            if($z < $northEquatorZ){
                $temperature = $this->interpolate($polarTemperature,$equatorTemperature,$z,$northGradientEnd);
            }elseif($z === $northEquatorZ || $z === $southEquatorZ){
                $temperature = $equatorTemperature;
            }else{
                $gradientLength = ($sizeZ - 1) - $southGradientEnd;
                if($z <= $southGradientEnd){
                    $temperature = $equatorTemperature;
                }elseif($gradientLength <= 0){
                    $temperature = $polarTemperature;
                }else{
                    $position = ($z - $southGradientEnd) / $gradientLength;
                    $position = max(0.0, min(1.0, $position));
                    $temperature = $equatorTemperature + ($polarTemperature - $equatorTemperature) * $position;
                }
            }
            for($x = 0; $x < $sizeX; $x++){
                $map->set(new ClimateTemperatureCell($x,$z,$temperature));
            }
        }
        return $map;
    }

    private function interpolate(
        float $polarTemperature,
        float $equatorTemperature,
        int $z,
        int $gradientEnd
    ): float{
        if($z >= $gradientEnd){
            return $equatorTemperature;
        }
        if($gradientEnd <= 0){
            return $polarTemperature;
        }
        $position = $z / $gradientEnd;
        $position = max(0.0, min(1.0, $position));
        return $polarTemperature + ($equatorTemperature - $polarTemperature) * $position;
    }

    private function validate(
        int $sizeX,
        int $sizeZ,
        float $polarTemperature,
        float $equatorTemperature
    ): void{
        if($sizeX < 10 || $sizeZ < 10 || $sizeX % 2 !== 0 || $sizeZ % 2 !== 0){
            throw new InvalidArgumentException("The dimensions of the climate map must be even and at least 10 x 10.");
        }
        if(!is_finite($polarTemperature) || !is_finite($equatorTemperature)){
            throw new InvalidArgumentException("Climate temperatures must be finite numbers.");
        }
    }
}