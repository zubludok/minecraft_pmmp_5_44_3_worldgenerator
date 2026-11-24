<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Debug;

use RuntimeException;
use WorldGenerator\Generator\Climate\ClimateBaseMap;
use WorldGenerator\Generator\Climate\ClimateLandscapeMap;
use WorldGenerator\Generator\Climate\ClimateDayNightMap;
use WorldGenerator\Generator\Climate\ClimateHumidityMap;
use WorldGenerator\Generator\Climate\ClimatePhenomenonRegion;

final class ClimateDebugExporter{

    private string $directory;

    public function __construct(string $worldPath){
        $this->directory = $worldPath. DIRECTORY_SEPARATOR . "debug". DIRECTORY_SEPARATOR . "climate";
        if(!is_dir($this->directory) &&
            !mkdir($this->directory, 0777, true) &&
            !is_dir($this->directory)){
            throw new RuntimeException("Cannot create climate debug directory: " . $this->directory);
        }
    }

    public function exportLandscape(ClimateLandscapeMap $map): void{
        $rows = [];
        $typeRows = [];
        $heightRows = [];
        for($z = 0; $z < $map->getSizeZ(); $z++){
            $row = [];
            $typeRow = "";
            $heightRow = [];
            for($x = 0; $x < $map->getSizeX(); $x++){
                $cell = $map->get($x, $z);
                if($cell === null){
                    $row[] = null;
                    $typeRow .= "?";
                    $heightRow[] = null;
                    continue;
                }
                $row[] = [
                    "x" => $cell->getX(),
                    "z" => $cell->getZ(),
                    "surfaceHeight" => $cell->getSurfaceHeight(),
                    "water" => $cell->isWater(),
                    "mountain" => $cell->isMountain(),
                    "relativeHeight" => round($cell->getRelativeHeight(), 2),
                    "heightDifference" => round($cell->getHeightDifference(), 2),
                    "surfaceRock" => $cell->getSurfaceRockId(),
                    "climateType" => $cell->getClimateType()
                ];
                $typeRow .= $cell->getClimateType();
                $heightRow[] = $this->formatHeight($cell->getSurfaceHeight());
            }
            $rows[] = $row;
            $typeRows[] = $typeRow;
            $heightRows[] = implode("\t", array_map(
                static fn($value): string =>
                    $value === null ? "?" : (string)$value,
                $heightRow
            ));
        }

        $this->writeJson("landscape.json", [
            "type" => "climate_landscape",
            "sizeX" => $map->getSizeX(),
            "sizeZ" => $map->getSizeZ(),
            "cells" => $rows
        ]);
        $this->writeText(
            "landscape.txt",
            "CLIMATE LANDSCAPE\n"
            . "Size: {$map->getSizeX()} x {$map->getSizeZ()}\n"
            . "Legend: W=water, L=land, M=mountain, ?=missing\n\n"
            . implode(PHP_EOL, $typeRows)
        );
        $this->writeText(
            "landscape_height.txt",
            "SURFACE HEIGHT\n"
            . "Values are separated by tabs; rows run north to south.\n\n"
            . implode(PHP_EOL, $heightRows)
        );
    }

    // @param array<string, ClimateBaseMap> $maps 
    public function exportBaseMaps(array $maps): void{
        foreach($maps as $name => $map){
            $name = strtolower($name);
            $this->exportBaseMap($name, $map);
        }
    }

    private function exportBaseMap(string $name,ClimateBaseMap $map): void{
        $rows = [];
        $temperatureRows = [];
        $windRows = [];
        for($z = 0; $z < $map->getSizeZ(); $z++){
            $row = [];
            $temperatureRow = [];
            $windRow = [];
            for($x = 0; $x < $map->getSizeX(); $x++){
                $cell = $map->get($x, $z);
                if($cell === null){
                    $row[] = null;
                    $temperatureRow[] = "?";
                    $windRow[] = "?";
                    continue;
                }
				$temperature = round($cell->getTemperature(), 2);
				$wind = $cell->getWindCoefficient();
                $row[] = [
                    "x" => $cell->getX(),
                    "z" => $cell->getZ(),
                    "temperature" => $temperature,
                    "windCoefficient" => $wind
                ];
                $temperatureRow[] = number_format($temperature, 2, ".", "");
                $windRow[] = $this->formatWind($wind);
            }
            $rows[] = $row;
            $temperatureRows[] = implode("\t", $temperatureRow);
            $windRows[] = implode("\t", $windRow);
        }
        $this->writeJson("base_{$name}.json", [
            "type" => "climate_base",
            "climate" => strtoupper($name),
            "sizeX" => $map->getSizeX(),
            "sizeZ" => $map->getSizeZ(),
            "cells" => $rows
        ]);
        $this->writeText(
            "base_{$name}_temperature.txt",
            strtoupper($name) . " TEMPERATURE (C)\n"
            . "Rows run north to south; values separated by tabs.\n\n"
            . implode(PHP_EOL, $temperatureRows)
        );
        $this->writeText(
            "base_{$name}_wind.txt",
            strtoupper($name) . " WIND COEFFICIENT\n"
            . "Range: 0..1; rows run north to south.\n\n"
            . implode(PHP_EOL, $windRows)
        );
    }    
	
	// Экспортирует порядок обхода секторов циклоном.
    // @param array<array{x:int,z:int}> $route
    public function exportCycloneRoute(array $route,int $sizeX,int $sizeZ): void{
        $cells = array_fill(0,$sizeZ,array_fill(0, $sizeX, null));
        $routeData = [];
        foreach($route as $index => $position){
            $x = $position["x"];
            $z = $position["z"];
            $order = $index + 1;
            if($x < 0 || $x >= $sizeX || $z < 0 || $z >= $sizeZ){
                throw new RuntimeException("Cyclone route contains an out-of-bounds sector: {$x}, {$z}.");
            }
            // Если сектор посещён повторно, сохраняем последний проход.
            $cells[$z][$x] = $order;
            $routeData[] = ["order" => $order,"x" => $x,"z" => $z];
        }
        $rows = [];
        for($z = 0; $z < $sizeZ; $z++){
            $values = [];
            for($x = 0; $x < $sizeX; $x++){
                $value = $cells[$z][$x];
                $values[] = $value === null ? "?" : str_pad((string) $value, 4, " ", STR_PAD_LEFT);
            }
            $rows[] = implode(" ", $values);
        }
        $this->writeJson("cyclone_route.json", [
            "type" => "cyclone_route",
            "sizeX" => $sizeX,
            "sizeZ" => $sizeZ,
            "route" => $routeData
        ]);
        $this->writeText(
            "cyclone_route.txt",
            "CYCLONE ROUTE\n"
            . "Each number is the visit order of a sector.\n"
            . "Rows run north to south.\n\n"
            . implode(PHP_EOL, $rows)
        );
    }
	
    // @param array<string, array<string, ClimateSeasonalMap>> $maps
	public function exportSeasonalMaps(array $maps): void{
		foreach($maps as $state => $seasons){
			foreach($seasons as $season => $map){
				$rows = [];
				$temperatureRows = [];
				$windRows = [];
				$directionRows = [];

				$temperatureValues = [];
				$windValues = [];
				$directionValues = [];

				for($z = 0; $z < $map->getSizeZ(); $z++){
					$row = [];
					$temperatureRow = [];
					$windRow = [];
					$directionRow = [];

					$temperatureValueRow = [];
					$windValueRow = [];
					$directionValueRow = [];

					for($x = 0; $x < $map->getSizeX(); $x++){
						$cell = $map->get($x, $z);

						if($cell === null){
							$row[] = null;
							$temperatureRow[] = "?";
							$windRow[] = "?";
							$directionRow[] = "?";

							$temperatureValueRow[] = null;
							$windValueRow[] = null;
							$directionValueRow[] = null;
							continue;
						}

						$temperature = round($cell->getTemperature(), 2);
						$wind = round($cell->getWindCoefficient(), 3);
						$direction = round($cell->getWindDirection(), 2);

						$row[] = [
							"x" => $cell->getX(),
							"z" => $cell->getZ(),
							"temperature" => $temperature,
							"windCoefficient" => $wind,
							"windDirection" => $direction
						];

						$temperatureRow[] = number_format($temperature, 2, ".", "");
						$windRow[] = number_format($wind, 3, ".", "");
						$directionRow[] = $this->formatWindDirection($direction);

						// Структурированные значения для программного чтения.
						$temperatureValueRow[] = $temperature;
						$windValueRow[] = $wind;
						$directionValueRow[] = $direction;
					}

					$rows[] = $row;
					$temperatureRows[] = implode("\t", $temperatureRow);
					$windRows[] = implode("\t", $windRow);
					$directionRows[] = implode("\t", $directionRow);

					$temperatureValues[] = $temperatureValueRow;
					$windValues[] = $windValueRow;
					$directionValues[] = $directionValueRow;
				}

				$state = strtolower($state);
				$season = strtolower($season);
				$prefix = "seasonal_{$state}_{$season}";

				// Общая карта со всеми параметрами.
				$this->writeJson($prefix . ".json", [
					"type" => "climate_seasonal",
					"state" => $state,
					"season" => $season,
					"sizeX" => $map->getSizeX(),
					"sizeZ" => $map->getSizeZ(),
					"cells" => $rows
				]);

				// Отдельные JSON-карты для DebugViewer и будущих систем.
				$this->writeJson($prefix . "_temperature.json", [
					"type" => "climate_seasonal_temperature",
					"state" => $state,
					"season" => $season,
					"sizeX" => $map->getSizeX(),
					"sizeZ" => $map->getSizeZ(),
					"values" => $temperatureValues
				]);

				$this->writeJson($prefix . "_wind.json", [
					"type" => "climate_seasonal_wind",
					"state" => $state,
					"season" => $season,
					"sizeX" => $map->getSizeX(),
					"sizeZ" => $map->getSizeZ(),
					"values" => $windValues
				]);

				$this->writeJson($prefix . "_direction.json", [
					"type" => "climate_seasonal_direction",
					"state" => $state,
					"season" => $season,
					"sizeX" => $map->getSizeX(),
					"sizeZ" => $map->getSizeZ(),
					"values" => $directionValues,
					"unit" => "degrees",
					"range" => [0, 360]
				]);

				// Человекочитаемые TXT оставляем.
				$this->writeText(
					$prefix . "_temperature.txt",
					strtoupper($state) . " / " . strtoupper($season)
					. " TEMPERATURE (C)\n"
					. "Rows run north to south.\n\n"
					. implode(PHP_EOL, $temperatureRows)
				);

				$this->writeText(
					$prefix . "_wind.txt",
					strtoupper($state) . " / " . strtoupper($season)
					. " WIND COEFFICIENT\n"
					. "Coefficient, not m/s.\n\n"
					. implode(PHP_EOL, $windRows)
				);

				$this->writeText(
					$prefix . "_direction.txt",
					strtoupper($state) . " / " . strtoupper($season)
					. " WIND DIRECTION (ARROWS)\n"
					. "↑=N, ↗=NE, →=E, ↘=SE, ↓=S, ↙=SW, ←=W, ↖=NW.\n\n"
					. implode(PHP_EOL, $directionRows)
				);
			}
		}
	}
	
	public function exportSeasonalChains(array $chainsBySeason,int $sizeX,int $sizeZ): void{
		foreach($chainsBySeason as $season => $hemispheres){
			$visits = array_fill(0,$sizeZ,array_fill(0, $sizeX, 0));
			$lastWriter = array_fill(0,$sizeZ,array_fill(0, $sizeX, 0));
			$chains = [];
			$chainIndex = 0;
			// Порядок совпадает с порядком обработки в ClimateCyclone.
			foreach(["north", "south"] as $hemisphere){
				foreach(["equatorial", "polar"] as $zone){
					foreach($hemispheres[$hemisphere][$zone] ?? [] as $chain){
						$chainIndex++;
						$cells = [];
						foreach($chain as $step => $point){
							$x = $point["x"];
							$z = $point["z"];
							if($x < 0 || $x >= $sizeX || $z < 0 || $z >= $sizeZ){
								continue;
							}
							$visits[$z][$x]++;
							$lastWriter[$z][$x] = $chainIndex;
							$cells[] = ["step" => $step,"x" => $x,"z" => $z];
						}
						$chains[] = [
							"id" => $chainIndex,
							"hemisphere" => $hemisphere,
							"zone" => $zone,
							"cells" => $cells
						];
					}
				}
			}
			$overwriteCounts = [];
			$lastWriterMap = [];
			$countRows = [];
			$writerRows = [];
			for($z = 0; $z < $sizeZ; $z++){
				$countRow = [];
				$writerRow = [];
				for($x = 0; $x < $sizeX; $x++){
					$count = $visits[$z][$x];
					$writer = $lastWriter[$z][$x];
					$countRow[] = $count;
					$writerRow[] = $writer;
					if($count > 0){
						$overwriteCounts[] = [
							"x" => $x,
							"z" => $z,
							"visits" => $count,
							"overwrites" => max(0, $count - 1),
							"lastWriterChainId" => $writer
						];
					}
				}
				$countRows[] = implode("\t", $countRow);
				$writerRows[] = implode("\t", $writerRow);
			}
			$prefix = "chains_" . strtolower($season);
			$this->writeJson($prefix . ".json", [
				"season" => $season,
				"sizeX" => $sizeX,
				"sizeZ" => $sizeZ,
				"chainCount" => count($chains),
				"processingOrder" => "north.equatorial, north.polar, south.equatorial, south.polar",
				"overwriteRule" => "Last processed chain wins",
				"chains" => $chains,
				"cellVisits" => $overwriteCounts
			]);
			$this->writeText(
				$prefix . "_visits.txt",
				"Cell visit count; 0 means untouched" . PHP_EOL .
				implode(PHP_EOL, $countRows)
			);
			$this->writeText(
				$prefix . "_last_writer.txt",
				"ID of the last chain writing each cell; 0 means untouched" . PHP_EOL .
				implode(PHP_EOL, $writerRows)
			);
		}
	}

    private function writeJson(string $file, array $data): void{
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT
            | JSON_UNESCAPED_UNICODE
            | JSON_UNESCAPED_SLASHES
            | JSON_THROW_ON_ERROR
        );
        $this->writeText($file, $json);
    }

    private function writeText(string $file, string $contents): void{
        $path = $this->directory . DIRECTORY_SEPARATOR . $file;
        if(file_put_contents($path, $contents . PHP_EOL) === false){
            throw new RuntimeException("Cannot write climate debug file: " . $path);
        }
    }
	private function formatWindDirection(float $direction): string{
		$arrows = [
			"↑", // Север
			"↗", // Северо-восток
			"→", // Восток
			"↘", // Юго-восток
			"↓", // Юг
			"↙", // Юго-запад
			"←", // Запад
			"↖"  // Северо-запад
		];
		$index = (int) round(fmod($direction + 360.0, 360.0) / 45.0) % 8;
		return $arrows[$index];
	}

	// Ветер: 0.000
	private function formatWind(float $value): string{return number_format($value, 3, ".", "");}
	// Высота: 00.00
	private function formatHeight(float $value): string{return sprintf("%05.2f", $value);}
	
	// @param array<string, array<string, array<string, ClimateDayNightMap>>> $maps
	public function exportDayNightMaps(array $maps): void{
		foreach($maps as $year => $seasons){
			foreach($seasons as $season => $times){
				foreach($times as $time => $map){
					$rows = [];
					$temperatureRows = [];
					$windRows = [];
					$directionRows = [];
					
					for($z = 0; $z < $map->getSizeZ(); ++$z){
						$row = [];
						$temperatureRow = [];
						$windRow = [];
						$directionRow = [];

						for($x = 0; $x < $map->getSizeX(); ++$x){
							$cell = $map->get($x, $z);

							if($cell === null){
								$row[] = null;
								$temperatureRow[] = '?';
								$windRow[] = '?';
								$directionRow[] = '?';
								continue;
							}

							$temperature = round($cell->getTemperature(), 2);
							$coefficient = round($cell->getWindCoefficient(), 3);
							$direction = round($cell->getWindDirection(), 2);
							$speed = round($cell->getWindSpeed(), 2);

							$row[] = [
								'x' => $x,
								'z' => $z,
								'temperature' => $temperature,
								'windCoefficient' => $coefficient,
								'windDirection' => $direction,
								'windSpeedMps' => $speed
							];

							$temperatureRow[] = number_format($temperature, 2, '.', '');
							$windRow[] = number_format($speed, 2, '.', '');
							$directionRow[] = $this->formatWindDirection($direction);
						}

						$rows[] = $row;
						$temperatureRows[] = implode("\t", $temperatureRow);
						$windRows[] = implode("\t", $windRow);
						$directionRows[] = implode("\t", $directionRow);
					}

					$prefix = sprintf(
						'daynight_%s_%s_%s',
						strtolower($year),
						strtolower($season),
						strtolower($time)
					);

					$this->writeJson($prefix . '.json', [
						'type' => 'climate_day_night',
						'year' => strtolower($year),
						'season' => strtolower($season),
						'time' => strtolower($time),
						'sizeX' => $map->getSizeX(),
						'sizeZ' => $map->getSizeZ(),
						'windSpeedUnit' => 'm/s',
						'maxWindSpeed' => 30.0,
						'cells' => $rows
					]);

					$this->writeText(
						$prefix . '_temperature.txt',
						strtoupper($year) . ' / ' . strtoupper($season)
						. ' / ' . strtoupper($time) . " TEMPERATURE (C)\n\n"
						. implode(PHP_EOL, $temperatureRows)
					);

					$this->writeText(
						$prefix . '_wind_speed.txt',
						strtoupper($year) . ' / ' . strtoupper($season)
						. ' / ' . strtoupper($time) . " WIND SPEED (M/S)\n\n"
						. implode(PHP_EOL, $windRows)
					);

					$this->writeText(
						$prefix . '_direction.txt',
						strtoupper($year) . ' / ' . strtoupper($season)
						. ' / ' . strtoupper($time) . " WIND DIRECTION\n"
						. "Arrows indicate the direction the wind flows to.\n\n"
						. implode(PHP_EOL, $directionRows)
					);
				}
			}
		}
	}
	
	// @param array<string, array<string, ClimateHumidityMap>> $maps
	public function exportHumidityMaps(array $maps): void{
		foreach($maps as $year => $seasons){
			foreach($seasons as $season => $map){
				$year = strtolower($year);
				$season = strtolower($season);
				$prefix = "humidity_{$year}_{$season}";
				$values = [];
				$rows = [];
				for($z = 0; $z < $map->getSizeZ(); ++$z){
					$valueRow = [];
					$textRow = [];
					for($x = 0; $x < $map->getSizeX(); ++$x){
						$cell = $map->get($x, $z);
						if($cell === null){
							$valueRow[] = null;
							$textRow[] = "?";
							continue;
						}
						$humidity = round($cell->getHumidity(), 4);
						$valueRow[] = $humidity;
						$textRow[] = number_format($humidity, 3, ".", "");
					}
					$values[] = $valueRow;
					$rows[] = implode("\t", $textRow);
				}
				$this->writeJson($prefix . ".json", [
					"type" => "climate_humidity",
					"yearType" => $year,
					"season" => $season,
					"sizeX" => $map->getSizeX(),
					"sizeZ" => $map->getSizeZ(),
					"unit" => "ratio",
					"range" => [0.0, 1.0],
					"values" => $values
				]);
				$this->writeText(
					$prefix . ".txt",
					strtoupper($year) . " / " . strtoupper($season)
					. " HUMIDITY\n"
					. "Range: 0..1; rows run north to south.\n"
					. "Values are separated by tabs.\n\n"
					. implode(PHP_EOL, $rows)
				);
			}
		}
	}

	// @param ClimatePhenomenonRegion[] $regions
	public function exportPhenomenonRegions(array $regions): void{
		$data = [];
		foreach($regions as $region){
			$data[] = $region->toArray();
		}
		$this->writeJson("phenomena_regions.json", [
			"type" => "climate_phenomena",
			"regionCount" => count($data),
			"regions" => $data
		]);
	}
}