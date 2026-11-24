<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use Random\Engine\Mt19937;
use Random\Randomizer;

final class ClimatePhenomenonGenerator{
    // @var array<array{0:int,1:int}>
    private const NEIGHBOURS = [
        [-1, -1], [0, -1], [1, -1],
        [-1,  0],          [1,  0],
        [-1,  1], [0,  1], [1,  1]
    ];

    private Randomizer $randomizer;
    private int $nextId = 1;

    /* @param array<string, array<string, ClimateSeasonalMap>> $seasonalMaps
     * @param array<string, array<string, ClimateHumidityMap>> $humidityMaps
     * @return ClimatePhenomenonRegion[] */
	public function generate(
		array $seasonalMaps,
		array $humidityMaps,
		ClimateLandscapeMap $landscape,
		int $seed,
		array $settings = []
	): array{
		$settings = ClimatePhenomenonSettings::normalize($settings);

		$this->randomizer = new Randomizer(new Mt19937($seed));
		$this->nextId = 1;
		$regions = [];
        foreach(["normal", "hot", "cold"] as $yearType){
            foreach(["spring", "summer", "autumn", "winter"] as $season){
                $seasonal = $seasonalMaps[$yearType][$season] ?? null;
                $humidity = $humidityMaps[$yearType][$season] ?? null;
                if(!$seasonal instanceof ClimateSeasonalMap ||
                    !$humidity instanceof ClimateHumidityMap){
                    continue;
                }
                foreach(["rain", "snow", "fog", "storm"] as $phenomenon){
                    $eligible = $this->findEligibleCells(
						$phenomenon,
						$seasonal,
						$humidity,
						$landscape,
						$settings
					);
                    $clusters = $this->cluster($eligible,$seasonal->getSizeX(),$seasonal->getSizeZ());
                    foreach($clusters as $cells){
						if(count($cells) < $settings["minRegionSize"]){
							continue;
						}
                        $regions[] = new ClimatePhenomenonRegion(
                            $this->nextId++,
                            $phenomenon,
                            $season,
                            $yearType,
                            $this->getHemisphere($cells[0]["z"],$seasonal->getSizeZ()),
                            $cells
                        );
                    }
                }
            }
        }
        $this->expandRegions($regions, $landscape, $settings);
        foreach($regions as $region){
            $region->recalculateIntensity();
        }
        return $regions;
    }

    // @return array<string, array{x:int,z:int}>
    private function findEligibleCells(
		string $phenomenon,
		ClimateSeasonalMap $seasonal,
		ClimateHumidityMap $humidity,
		ClimateLandscapeMap $landscape,
		array $settings
	): array{
        $eligible = [];
        for($z = 0; $z < $seasonal->getSizeZ(); ++$z){
            for($x = 0; $x < $seasonal->getSizeX(); ++$x){
                $climate = $seasonal->get($x, $z);
                $moisture = $humidity->get($x, $z);
                $terrain = $landscape->get($x, $z);
                if($climate === null || $moisture === null || $terrain === null){
                    continue;
                }
                $temperature = $climate->getTemperature();
                $wind = $climate->getWindCoefficient();
                $value = $moisture->getHumidity();
				$matches = match($phenomenon){
					"rain" =>
						$value > $settings["humidityRainMin"] &&
						$temperature > $settings["temperatureFreeze"] &&
						$wind < $settings["windRainMax"],

					"snow" =>
						$value > $settings["humiditySnowMin"] &&
						$temperature < $settings["temperatureSnowMax"],

					"fog" =>
						$value > $settings["humidityFogMin"] &&
						$wind < $settings["windFogMax"],

					"storm" =>
						$this->hasNearbySand($landscape, $x, $z) &&
						$value < $settings["humidityStormMax"] &&
						$temperature > $settings["temperatureFreeze"] &&
						$wind > $settings["windStormMin"],

					default => false
				};
                if($matches){
                    $eligible[$this->key($x, $z)] = ["x" => $x,"z" => $z];
                }
            }
        }
        return $eligible;
    }

    /* @param array<string, array{x:int,z:int}> $eligible
     * @return array<array<array{x:int,z:int}>> */
    private function cluster(array $eligible,int $sizeX,int $sizeZ): array{
        $visited = [];
        $clusters = [];
        foreach($eligible as $startKey => $start){
            if(isset($visited[$startKey])){
                continue;
            }
            $queue = [$start];
            $visited[$startKey] = true;
            $cluster = [];
            for($head = 0; $head < count($queue); ++$head){
                $cell = $queue[$head];
                $cluster[] = $cell;
                foreach(self::NEIGHBOURS as [$dx, $dz]){
                    $x = $cell["x"] + $dx;
                    $z = $cell["z"] + $dz;
                    if($x < 0 || $x >= $sizeX || $z < 0 || $z >= $sizeZ){
                        continue;
                    }
                    if($this->getHemisphere($z, $sizeZ) !==
                        $this->getHemisphere($cell["z"], $sizeZ)){
                        continue;
                    }
                    $key = $this->key($x, $z);
                    if(isset($visited[$key]) || !isset($eligible[$key])){
                        continue;
                    }
                    $visited[$key] = true;
                    $queue[] = $eligible[$key];
                }
            }
            $clusters[] = $cluster;
        }
        return $clusters;
    }

	/* Добавляет примерно одну клетку на каждые три клетки кластера.
	 * Не позволяет регионам одного явления в одном сезоне и году
	 * занимать общие сектора.
	 * @param ClimatePhenomenonRegion[] $regions */
	private function expandRegions(
		array $regions,
		ClimateLandscapeMap $landscape,
		array $settings
	): void{
		// @var array<string, true> $occupied
		$occupied = [];
		// Сначала регистрируем ВСЕ исходные клетки всех регионов.
		foreach ($regions as $region) {
			$scope = $region->getPhenomenon() . ":" . $region->getYearType() . ":" . $region->getSeason() . ":";
			foreach ($region->getCells() as $cell) {
				$occupied[$scope . $this->key($cell->getX(), $cell->getZ())] = true;
			}
		}
		// Затем расширяем регионы.
		foreach ($regions as $region) {
			$target = (int) ceil($region->getTotalCells() * $settings["regionExpansionRatio"]);
			$added = 0;
			$scope = $region->getPhenomenon() . ":" . $region->getYearType() . ":" .$region->getSeason() . ":";
			while ($added < $target) {
				$boundary = [];
				foreach ($region->getCells() as $cell) {
					foreach (self::NEIGHBOURS as [$dx, $dz]) {
						$x = $cell->getX() + $dx;
						$z = $cell->getZ() + $dz;
						if ($x < 0 || $x >= $landscape->getSizeX() || $z < 0 || $z >= $landscape->getSizeZ()) {
							continue;
						}

						if ($this->getHemisphere($z, $landscape->getSizeZ())!== $region->getHemisphere()) {
							continue;
						}
						if ($region->hasCell($x, $z)) {
							continue;
						}
						$key = $this->key($x, $z);
						// Проверяем занятость только в рамках того же
						// явления, года и сезона.
						if (isset($occupied[$scope . $key])) {
							continue;
						}
						$boundary[$key] = ["x" => $x,"z" => $z];
					}
				}
				if ($boundary === []) {
					break;
				}
				$candidates = array_values($boundary);
				$selected = $candidates[$this->randomizer->getInt(0, count($candidates) - 1)];
				$x = $selected["x"];
				$z = $selected["z"];
				if (!$region->addCell($x, $z)) {
					break;
				}
				// Сразу резервируем сектор, чтобы следующий регион
				// этой же комбинации не смог его занять.
				$occupied[$scope . $this->key($x, $z)] = true;
				++$added;
			}
		}
	}

    private function hasNearbySand(ClimateLandscapeMap $landscape,int $x,int $z): bool{
        $cell = $landscape->get($x, $z);
        if($cell !== null && $this->isSand($cell->getSurfaceRockId())){
            return true;
        }
        foreach(self::NEIGHBOURS as [$dx, $dz]){
            $neighbour = $landscape->get($x + $dx, $z + $dz);
            if($neighbour !== null &&
                $this->isSand($neighbour->getSurfaceRockId())){
                return true;
            }
        }
        return false;
    }

	private function isSand(?string $rockId): bool{
		if($rockId === null){
			return false;
		}
		$separator = strrpos($rockId, ":");
		$id = strtolower($separator === false ? $rockId : substr($rockId, $separator + 1));
		return str_contains($id, "sand");
	}

    private function getHemisphere(int $z, int $sizeZ): string{return $z < intdiv($sizeZ, 2) ? "north" : "south";}
    private function key(int $x, int $z): string{return $x . ":" . $z;}
}