<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateDayNightGenerator{

    private const MAX_WIND_SPEED = 30.0;

    // @var array<string, array{int, int}> Почему то используется только 4 соседа, а должно 8
	private const NEIGHBOURS = [
		'north'      => [0, -1],
		'north_east' => [1, -1],
		'east'       => [1, 0],
		'south_east' => [1, 1],
		'south'      => [0, 1],
		'south_west' => [-1, 1],
		'west'       => [-1, 0],
		'north_west' => [-1, -1],
	];

    /* @param array<string, array<string, ClimateSeasonalMap>> $seasonalMaps
     * @return array<string, array<string, array<string, ClimateDayNightMap>>>*/
	public function generate(array $seasonalMaps,ClimateLandscapeMap $landscapeMap,array $settings = []): array{
		$settings = ClimateDayNightSettings::normalize($settings);
		$result = [];
		foreach($seasonalMaps as $year => $seasons){
			foreach($seasons as $season => $seasonalMap){
				$result[$year][$season] = [
					'day' => $this->generateMap($seasonalMap,$landscapeMap,true,$settings),
					'night' => $this->generateMap($seasonalMap,$landscapeMap,false,$settings)
				];
			}
		}
		return $result;
	}

    private function generateMap(ClimateSeasonalMap $source,ClimateLandscapeMap $landscape,bool $day,array $settings): ClimateDayNightMap{
        $sizeX = $source->getSizeX();
        $sizeZ = $source->getSizeZ();
        $result = new ClimateDayNightMap($sizeX, $sizeZ);
        for($z = 0; $z < $sizeZ; ++$z){
            for($x = 0; $x < $sizeX; ++$x){
                $sourceCell = $source->get($x, $z);
                $terrain = $landscape->get($x, $z);
                if($sourceCell === null || $terrain === null){
                    continue;
                }
				$temperature = $this->calculateTemperature($sourceCell->getTemperature(),$terrain,$day,$settings);
				$direction = $this->calculateDirection($sourceCell->getWindDirection(),$x,$z,$landscape,$day,$settings);
                $coefficient = max(0.0,$sourceCell->getWindCoefficient());
                // Коэффициент остаётся безразмерным.
                // Значение 1.5 соответствует максимальным 30 м/с.
				$normalized = min(1.0, $coefficient / 1.5);
				$speed = $normalized * $settings["maxWindSpeed"];
				if($terrain->isMountain()){
					$speed *= $settings["mountainWindFactor"];
				}
				if($terrain->isWater()){
					$speed *= $day ? $settings["waterDayWindFactor"] : $settings["waterNightWindFactor"];
				}else{
					$speed *= $day ? $settings["landDayWindFactor"] : $settings["landNightWindFactor"];}
				$speed = max(0.0,min($settings["maxWindSpeed"], $speed));
                $result->set(new ClimateDayNightCell($x,$z,$temperature,$coefficient,$direction,$speed));
            }
        }

        return $result;
    }

    private function calculateTemperature(float $baseTemperature,ClimateLandscapeCell $terrain,bool $day,array $settings): float{
        // Суточная амплитуда: суша нагревается и остывает сильнее воды.
		$offset = match(true){
			$terrain->isWater() => $day ? $settings["waterDayTemperatureOffset"] : $settings["waterNightTemperatureOffset"],
			$terrain->isMountain() =>$day ? $settings["mountainDayTemperatureOffset"] : $settings["mountainNightTemperatureOffset"],
			default =>$day ? $settings["landDayTemperatureOffset"] : $settings["landNightTemperatureOffset"]
		};
        // Высота и относительное положение добавляют локальную поправку.
        $relativeHeight = max(-1.0,min(1.0, $terrain->getRelativeHeight()));
        $heightAdjustment = -2.0 * $relativeHeight;
        return $baseTemperature + $offset + $heightAdjustment;
    }

    private function calculateDirection(float $baseDirection,int $x,int $z,ClimateLandscapeMap $landscape,bool $day,array $settings): float{
        $angle = deg2rad($baseDirection);
        // Направление ветра обозначает, куда он дует.
        $windX = sin($angle);
        $windZ = -cos($angle);
        $terrain = $landscape->get($x, $z);
        if($terrain === null){
            return $this->normalizeDirection($baseDirection);
        }
        $breezeX = 0.0;
        $breezeZ = 0.0;
        foreach(self::NEIGHBOURS as [$dx, $dz]){
            $neighbour = $landscape->get($x + $dx, $z + $dz);
            if($neighbour === null){
                continue;
            }
            // Дневной бриз: от воды к суше.
            // Ночной бриз: от суши к воде.
            if($terrain->isWater() && $neighbour->isLand()){
				// На водной ячейке вектор направлен к соседней суше днём.
				$factor = $day ? 1.0 : -1.0;
				$breezeX += $dx * $factor;
				$breezeZ += $dz * $factor;
			}elseif($terrain->isLand() && $neighbour->isWater()){
				// На суше дневной ветер направлен от воды к текущей ячейке.
				$factor = $day ? -1.0 : 1.0;
				$breezeX += $dx * $factor;
				$breezeZ += $dz * $factor;
            }
        }
        $breezeLength = hypot($breezeX, $breezeZ);
        if($breezeLength > 0.0){
            $breezeX /= $breezeLength;
            $breezeZ /= $breezeLength;
            // Бриз корректирует основное направление, но не заменяет его.
            $weight = $day ? $settings["dayBreezeWeight"] : $settings["nightBreezeWeight"];
            $windX = $windX * (1.0 - $weight) + $breezeX * $weight;
            $windZ = $windZ * (1.0 - $weight) + $breezeZ * $weight;
        }
        // Рельеф слегка отклоняет поток в сторону более низких соседей.
        $terrainX = 0.0;
        $terrainZ = 0.0;
        foreach(self::NEIGHBOURS as [$dx, $dz]){
            $neighbour = $landscape->get($x + $dx, $z + $dz);
            if($neighbour === null){
                continue;
            }
            $drop = $terrain->getSurfaceHeight() - $neighbour->getSurfaceHeight();
            $terrainX += $dx * $drop;
            $terrainZ += $dz * $drop;
        }
        $terrainLength = hypot($terrainX, $terrainZ);
        if($terrainLength > 0.0){
            $windX += ($terrainX / $terrainLength) * 0.1;
            $windZ += ($terrainZ / $terrainLength) * 0.1;
        }
        if(hypot($windX, $windZ) < 0.000001){
            return $this->normalizeDirection($baseDirection);
        }
        return $this->normalizeDirection(
            rad2deg(atan2($windX, -$windZ))
        );
    }
    private function normalizeDirection(float $direction): float{
        return fmod($direction + 360.0, 360.0);
    }
}