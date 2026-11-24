<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateBaseMapGenerator{

    public const NORMAL = "NORMAL";
    public const HOT = "HOT";
    public const COLD = "COLD";

    /* @return array<string, ClimateBaseMap> */
	public function generate(ClimateLandscapeMap $landscapeMap,float $polarTemperature,float $equatorTemperature,int $polarShift,int $waterLevel,array $settings = []): array{
		$settings = ClimateBaseMapSettings::normalize($settings);
		$tempScale = $settings[ClimateBaseMapSettings::TEMP_SCALE];
		$hotPolarPull = $settings[ClimateBaseMapSettings::MOVE_HOT_POLAR_PULL];
		$hotEquatorLift = $settings[ClimateBaseMapSettings::MOVE_HOT_EQUATOR_LIFT];
		$coldPolarDrop = $settings[ClimateBaseMapSettings::MOVE_COLD_POLAR_DROP];
		$coldEquatorPull = $settings[ClimateBaseMapSettings::MOVE_COLD_EQUATOR_PULL];
		$sizeX = $landscapeMap->getSizeX();
		$sizeZ = $landscapeMap->getSizeZ();
		$this->validate($sizeX,$sizeZ,$polarTemperature,$equatorTemperature);
		error_log(
			"[ClimateDebug] Polar={$polarTemperature}, "
			. "Equator={$equatorTemperature}, "
			. "Shift={$polarShift}, "
			. "Map={$sizeX}x{$sizeZ}"
		);
		$landscapeWindFactor = $settings[ClimateBaseMapSettings::LANDSCAPE_WIND_FACTOR];
		// Обычный год
		$normal = $this->generateMap(
			$landscapeMap,
			$polarTemperature,
			$equatorTemperature,
			$polarShift,
			$waterLevel,
			$landscapeWindFactor
		);
		// Жаркий год
		$hotPolar = $polarTemperature + ($equatorTemperature - $polarTemperature) * $hotPolarPull;
		$hotEquator = $equatorTemperature + ($tempScale - $equatorTemperature) * $hotEquatorLift;
		$hot = $this->generateMap(
			$landscapeMap,
			$hotPolar,
			$hotEquator,
			$polarShift,
			$waterLevel,
			$landscapeWindFactor
		);

		// Холодный год
		$coldPolar = $polarTemperature - ($polarTemperature - $equatorTemperature) * $coldPolarDrop;
		$coldEquator = $equatorTemperature - ($equatorTemperature - $polarTemperature) * $coldEquatorPull;
		$cold = $this->generateMap(
			$landscapeMap,
			$coldPolar,
			$coldEquator,
			$polarShift,
			$waterLevel,
			$landscapeWindFactor
		);
		return ['normal' => $normal,'hot' => $hot,'cold' => $cold];
    }

    private function generateMap(ClimateLandscapeMap $landscapeMap,float $polarTemperature,float $equatorTemperature,int $polarShift,int $waterLevel,float $landscapeWindFactor): ClimateBaseMap{
        $sizeX = $landscapeMap->getSizeX();
        $sizeZ = $landscapeMap->getSizeZ();
        $map = new ClimateBaseMap($sizeX, $sizeZ);
        // Экватор занимает два центральных ряда.
        $northEquatorZ = intdiv($sizeZ, 2) - 1;
        $southEquatorZ = intdiv($sizeZ, 2);
        // Полюса всегда остаются на крайних рядах карты.
        $northGradientEnd = max(1,$northEquatorZ + min(0, $polarShift));
        $southGradientEnd = min($sizeZ - 2,$southEquatorZ + max(0, $polarShift));
        for($z = 0; $z < $sizeZ; $z++){
            $temperature = $this->getTemperature(
                $z,
                $sizeZ,
                $northEquatorZ,
                $southEquatorZ,
                $northGradientEnd,
                $southGradientEnd,
                $polarTemperature,
                $equatorTemperature
            );
            $latitude = $this->getLatitude(
                $z,
                $sizeZ,
                $northEquatorZ,
                $southEquatorZ
            );
            // НКСВ: 1 на экваторе, 0 на полюсах.
            $baseWindCoefficient = 1.0 - abs($latitude);
            for($x = 0; $x < $sizeX; $x++){
                $landscape = $landscapeMap->get($x, $z);
                $landscapeCoefficient = 0.0;
                if($landscape !== null){
                    $heightAboveWater = max(0,$landscape->getSurfaceHeight() - $waterLevel);
                    $landscapeCoefficient = $heightAboveWater * $landscapeWindFactor;
                }
                $windCoefficient = max(0.0,min(1.0,$baseWindCoefficient + $landscapeCoefficient));
                $map->set(new ClimateBaseCell($x,$z,$temperature,$windCoefficient));
            }
        }
        return $map;
    }

	private function getTemperature(int $z,int $sizeZ,int $northEquatorZ,int $southEquatorZ,int $northGradientEnd,int $southGradientEnd,float $polarTemperature,float $equatorTemperature): float{
		if($z <= $northEquatorZ){
			$gradientLength = $northGradientEnd;
			if($z >= $gradientLength){
				return $equatorTemperature;
			}
			$position = $z / $gradientLength;
			return $polarTemperature + ($equatorTemperature - $polarTemperature) * $position;
		}
		if($z <= $southGradientEnd){
			return $equatorTemperature;
		}
		$gradientLength = ($sizeZ - 1) - $southGradientEnd;
		if($gradientLength <= 0){
			return $polarTemperature;
		}
		$distanceFromEquator = $z - $southGradientEnd;
		$position = $distanceFromEquator / $gradientLength;
		return $equatorTemperature - ($equatorTemperature - $polarTemperature) * max(0.0, min(1.0, $position));
	}

    private function getLatitude(int $z,int $sizeZ,int $northEquatorZ,int $southEquatorZ): float{
        if($z <= $northEquatorZ){
            $distance = $northEquatorZ - $z;
            $maxDistance = max(1, $northEquatorZ);
            return -min(1.0, $distance / $maxDistance);
        }
        $distance = $z - $southEquatorZ;
        $maxDistance = max(1,($sizeZ - 1) - $southEquatorZ);
        return min(1.0, $distance / $maxDistance);
    }

    private function validate(int $sizeX,int $sizeZ,float $polarTemperature,float $equatorTemperature): void{
		if($sizeX < 8 || $sizeZ < 8 || $sizeX % 2 !== 0 || $sizeZ % 2 !== 0){
            throw new InvalidArgumentException("The dimensions of the climate map must be even and at least 10x10.");
        }
        if(!is_finite($polarTemperature) || !is_finite($equatorTemperature)){
            throw new InvalidArgumentException("Climate temperatures must be finite numbers.");
        }
    }
}