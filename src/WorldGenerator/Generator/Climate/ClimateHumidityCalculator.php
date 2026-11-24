<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateHumidityCalculator{

    private const TEMP_FREEZE = 0.0;
    private const TEMP_HOT = 30.0;
    private const HUMID_H_REF = 80.0;
    private const WIND_DRAIN_RATE = 0.3;

    private const NEIGHBOURS = [
        [-1, -1], [0, -1], [1, -1],
        [-1,  0],          [1,  0],
        [-1,  1], [0,  1], [1,  1]
    ];

    public function generate(ClimateLandscapeMap $landscape,ClimateSeasonalMap $seasonal,float $northHumidity,float $southHumidity,int $waterLevel,string $yearType,string $season): ClimateHumidityMap{
        $this->validateHumidity($northHumidity, "north");
        $this->validateHumidity($southHumidity, "south");
        $sizeX = $landscape->getSizeX();
        $sizeZ = $landscape->getSizeZ();
        $map = new ClimateHumidityMap($sizeX,$sizeZ,$yearType,$season);
        // Экватор находится между центральными рядами при чётном размере Z.
        $equatorZ = ($sizeZ - 1) / 2.0;
        $polarDistance = max(1.0, $equatorZ);
        for($z = 0; $z < $sizeZ; ++$z){
            for($x = 0; $x < $sizeX; ++$x){
                $land = $landscape->get($x, $z);
                $climate = $seasonal->get($x, $z);
                if($land === null || $climate === null){
                    continue;
                }
                $latitude = ($z - $equatorZ) / $polarDistance;
                $latitude = max(-1.0, min(1.0, $latitude));
                // На экваторе нечётной карты берём среднее двух настроек.
                if(abs($latitude) < 1.0E-9){
					$hemisphereHumidity = ($northHumidity + $southHumidity) / 2.0;
                }elseif($latitude < 0.0){
                    $hemisphereHumidity = $northHumidity;
                }else{
                    $hemisphereHumidity = $southHumidity;
                }
                $baseHumidity = 1.0 - abs($latitude) * 0.5;
                $baseHumidity *= 1.0 + $hemisphereHumidity * 0.5;
                $temperature = $climate->getTemperature();
                $tempFactor = $this->clamp(($temperature - self::TEMP_FREEZE)/ (self::TEMP_HOT - self::TEMP_FREEZE),0.0,1.0);
                $waterCount = 0;
                $windwardSum = 0.0;
                $windDirection = $climate->getWindDirection();
                foreach(self::NEIGHBOURS as [$dx, $dz]){
                    $neighbour = $landscape->get($x + $dx, $z + $dz);
                    if($neighbour === null || !$neighbour->isWater()){
                        continue;
                    }
                    ++$waterCount;
                    // Направление от текущей клетки к воде.
                    $waterDirection = rad2deg(atan2($dx, -$dz));
                    $waterDirection = fmod($waterDirection + 360.0,360.0);
                    $relativeDirection = fmod($waterDirection - $windDirection + 360.0,360.0);
                    $windwardSum += (1.0 + cos(deg2rad($relativeDirection - 180.0))) / 2.0;
                }

                $waterFactor = $waterCount / 8.0;
                $windwardFactor = $windwardSum / 8.0;
                $heightAboveWater = max(0.0,$land->getSurfaceHeight() - $waterLevel);
                $heightPenalty = $this->clamp($heightAboveWater / self::HUMID_H_REF,0.0,1.0);
                $reliefFactor = 1.0 - $heightPenalty * 0.5;
                $windDrain = $this->clamp(1.0 - $climate->getWindCoefficient() * self::WIND_DRAIN_RATE,0.3,1.0);
                $humidity = $baseHumidity * $tempFactor * ($waterFactor + $windwardFactor) * $reliefFactor * $windDrain;
                $humidity = $this->clamp($humidity, 0.0, 1.0);
                $map->set(new ClimateHumidityCell($x,$z,$humidity));
            }
        }
        return $map;
    }

    private function validateHumidity(float $humidity,string $hemisphere): void{
        if(!is_finite($humidity) || $humidity < -1.0 || $humidity > 1.0){
            throw new InvalidArgumentException(ucfirst($hemisphere) . " humidity must be finite and in range -1..1.");
        }
    }

    private function clamp(float $value,float $min,float $max): float{return max($min, min($max, $value));}
}