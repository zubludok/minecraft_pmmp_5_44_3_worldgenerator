<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use WorldGenerator\Generator\Climate\ClimateBaseMap;
use WorldGenerator\Generator\Climate\ClimateSeasonalMap;
use WorldGenerator\Generator\Climate\ClimateTemperatureMap;
use WorldGenerator\Generator\Formation\Force;
use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Simulation\SimulationState;
use WorldGenerator\Generator\Climate\ClimateDayNightMap;
use WorldGenerator\Generator\Climate\ClimateDayNightCell;
use WorldGenerator\Generator\Climate\ClimateHumidityMap;
use WorldGenerator\Generator\Structure\StructureType;

final class MapViewport{

    private MassMap $map;

    private int $x = 0;
    private int $z = 0;
    private int $size = 10;
    private int $step = 10;

    private ?SimulationState $simulation = null;
    private ?ClimateTemperatureMap $climateTemperatureMap = null;
    
    private array $climateBaseMaps = [];		// @var array<string, ClimateBaseMap>
    private array $climateSeasonalMaps = [];	// @var array<string, array<string, ClimateSeasonalMap>>
	private array $climateHumidityMaps = [];	// @var array<string, array<string, ClimateHumidityMap>>
	
    private int $waterLevel = 61;

    private int $displayMode = 0;
    private int $yearIndex = 0;
    private int $seasonIndex = 0;
	
	private MapCellTextureResolver $textureResolver;

    private const YEARS = [	// @var array<string, string>
        'cold' => 'Холодный',
        'normal' => 'Нейтральный',
        'hot' => 'Жаркий'
    ];

    private const SEASONS = [	// @var array<string, string>
        'summer' => 'Лето',
        'autumn' => 'Осень',
        'winter' => 'Зима',
        'spring' => 'Весна'
    ];

	private const PHENOMENA = [	// @var list<string>
		'rain',
		'snow',
		'fog',
		'storm',
		'sand_storm'
	];
	
	private array $climateDayNightMaps = [];	// @var array<string, array<string, array<string, ClimateDayNightMap>>>
	private bool $daytime = true;
	private array $phenomenonCellIndex = [];	// @var array<string, array<string, true>>
	
	public function __construct(MassMap $map){
		$this->map = $map;
		$this->textureResolver = new MapCellTextureResolver();
		$this->clampPosition();
	}
	
	public function getCellTexture(int $x, int $z): string{
		$cell = $this->map->get($x, $z);
		if($cell === null){
			return 'textures/map/default';
		}
		$height = $this->getSurfaceHeight($cell);
		$isWater = $height <= $this->waterLevel;
		$stage = $this->simulation?->getStage();
		$mode = $this->getCurrentDisplayMode();
		$structureType = null;
		foreach($cell->getStructures() as $structure){
			$structureType = $structure->getType();
			break;
		}
		$data = [
			'exists' => true,
			'mode' => $mode,
			'height' => $height,
			'water' => $isWater,
			'fragment_id' => $cell->getFragmentId(),
			'terrain_objects' => $structureType,
		];
		$key = $x . ':' . $z;
		$phenomenon = null;
		foreach($this->phenomenonCellIndex as $type => $cells){
			if(isset($cells[$key])){
				$phenomenon = $type;
				break;
			}
		}
		$selectedPhenomenon = $this->getSelectedPhenomenonType();
		$data['phenomenon'] = $phenomenon === $selectedPhenomenon ? $selectedPhenomenon : null;
		switch($mode){
			case 'wind':
				$climateCell = null;
				if($stage === SimulationState::STAGE_YEARS){
					$climateCell = ($this->climateBaseMaps[$this->getYearKey()] ?? null)?->get($x, $z);
					$data['wind'] = $climateCell?->getWindCoefficient();
				}elseif($stage === SimulationState::STAGE_SEASONS){
					$climateCell = $this->getSelectedSeasonalCell($x, $z);
					$data['wind'] = $climateCell?->getWindCoefficient();
				}elseif(
					$stage === SimulationState::STAGE_DAY_NIGHT ||
					$stage === SimulationState::STAGE_PHENOMENA
				){
					$climateCell = $this->getSelectedDayNightCell($x, $z);
					$data['wind'] = $climateCell !== null ? $climateCell->getWindSpeed() / 30.0 : null;
				}
				break;

			case 'direction':
				$climateCell = ($stage === SimulationState::STAGE_DAY_NIGHT || $stage === SimulationState::STAGE_PHENOMENA) ? $this->getSelectedDayNightCell($x, $z) : $this->getSelectedSeasonalCell($x, $z);
				$data['direction'] = $climateCell?->getWindDirection();
				break;

			case 'temperature':
				if($stage === SimulationState::STAGE_YEARS){
					$climateCell = ($this->climateBaseMaps[$this->getYearKey()] ?? null)?->get($x, $z);
				}elseif($stage === SimulationState::STAGE_DAY_NIGHT || $stage === SimulationState::STAGE_PHENOMENA){
					$climateCell = $this->getSelectedDayNightCell($x, $z);
				}else{
					$climateCell = $this->getSelectedSeasonalCell($x, $z);
				}
				$data['temperature'] = $climateCell?->getTemperature();
				break;

			case 'humidity':
				$humidityMap = $this->climateHumidityMaps
					[$this->getYearKey()]
					[$this->getSeasonKey()] ?? null;
				$humidityCell = $humidityMap?->get($x, $z);
				$data['humidity'] = $humidityCell?->getHumidity();
				break;

			case 'phenomena':
				break;
			}

			return $this->textureResolver->resolve($data);
	}

	/* Доступные режимы зависят от этапа симуляции.
     * @return list<string> */
	public function getAvailableModes(): array{
		return match($this->simulation?->getStage()){
			SimulationState::STAGE_FORMATION => [
				'height',
				'terrain_objects'
			],
			SimulationState::STAGE_ISLANDS => [
				'height',
				'terrain_objects',
				'islands_water'
			],
			SimulationState::STAGE_YEARS => [
				'height',
				'terrain_objects',
				'islands_water',
				'wind',
				'temperature'
			],
			SimulationState::STAGE_SEASONS => [
				'height',
				'terrain_objects',
				'islands_water',
				'wind',
				'direction',
				'temperature',
				'humidity'
			],
			SimulationState::STAGE_DAY_NIGHT => [
				'height',
				'terrain_objects',
				'islands_water',
				'wind',
				'direction',
				'temperature',
				'humidity'
			],
			SimulationState::STAGE_PHENOMENA => [
				'height',
				'terrain_objects',
				'islands_water',
				'wind',
				'direction',
				'temperature',
				'humidity',
				'phenomena'
			],
			default => ['height']
		};
	}
    
	public function getDisplayMode(): int{return $this->displayMode;}
	public function getCurrentDisplayMode(): string{$modes = $this->getAvailableModes();return $modes[$this->displayMode] ?? ($modes[0] ?? 'density');}
    
	public function getDisplayModeName(): string{
        return match($this->getCurrentDisplayMode()){
            'height' => 'Высота',
            'terrain_objects' => 'Рельеф и объекты',
            'islands_water' => 'Острова и вода',
            'wind' => 'Сила ветра',
			'humidity' => 'Влажность',
            'direction' => 'Направление ветра',
            'temperature' => 'Температура',
            'phenomena' => 'Явления погоды',
            default => 'Неизвестный режим'
        };
    }
    
	public function getYearIndex(): int{return $this->yearIndex;}
    public function getYearName(): string{$keys = array_keys(self::YEARS);return self::YEARS[$keys[$this->yearIndex]] ?? 'Нейтральный';}
    public function getYearKey(): string{$keys = array_keys(self::YEARS);return $keys[$this->yearIndex] ?? 'normal';}
    public function getSeasonIndex(): int{return $this->seasonIndex;}
    public function getSeasonName(): string{$keys = array_keys(self::SEASONS);return self::SEASONS[$keys[$this->seasonIndex]] ?? 'Лето';}
    public function getSeasonKey(): string{$keys = array_keys(self::SEASONS);return $keys[$this->seasonIndex] ?? 'summer';}
	public function getTimeKey(): string{return $this->daytime ? 'day' : 'night';}
    
	private function getSelectedSeasonalCell(int $x, int $z): mixed{
        $map = $this->climateSeasonalMaps[$this->getYearKey()][$this->getSeasonKey()] ?? null;
        return $map?->get($x, $z);
    }
	
	private function getSurfaceHeight(MassCell $cell): float{
        $height = 0.0;
        foreach($cell->getLayers() as $layer){
            if($layer->isEmpty() || $layer->isWater()){
                continue;
            }
            $height += $layer->getAmount();
        }
        return $height;
    }
	
	// Метод получения выбранной ячейки
	private function getSelectedDayNightCell(int $x,int $z): ?ClimateDayNightCell{
		$time = $this->daytime ? 'day' : 'night';
		$map = $this->climateDayNightMaps
			[$this->getYearKey()]
			[$this->getSeasonKey()]
			[$time] ?? null;
		return $map?->get($x, $z);
	}
    
	public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getMaxX(): int{return max(0, $this->map->getSizeX() - $this->size);}
    public function getMaxZ(): int{return max(0, $this->map->getSizeZ() - $this->size);}
    public function getEndX(): int{return min($this->map->getSizeX() - 1, $this->x + $this->size - 1);}
    public function getEndZ(): int{return min($this->map->getSizeZ() - 1, $this->z + $this->size - 1);}
    public function getSize(): int{return $this->size;}
    public function getStep(): int{return $this->step;}
    public function getTimeName(): string{return $this->daytime ? 'День' : 'Ночь';}
	public function getCell(int $x, int $z): ?MassCell{return $this->map->get($x, $z);}
	
    public function move(int $x, int $z): void{$this->x += $x * $this->step;$this->z += $z * $this->step;$this->clampPosition();}
    public function setPosition(int $x, int $z): void{$this->x = $x;$this->z = $z;$this->clampPosition();}
	public function setSize(int $size): void{$this->size = max(5, min(15, $size));$this->clampPosition();}
	public function setStep(int $step): void{$this->step = max(1, min(15, $step));}
	public function setMap(MassMap $map): void{$this->map = $map;$this->x = 0;$this->z = 0;$this->clampPosition();}
	
	public function setSimulation(?SimulationState $simulation): void{
		$oldStage = $this->simulation?->getStage();
		$newStage = $simulation?->getStage();
		$this->simulation = $simulation;
		if($oldStage !== $newStage){
			$this->displayMode = 0;
			if(!($oldStage === SimulationState::STAGE_SEASONS && $newStage === SimulationState::STAGE_DAY_NIGHT)){
				$this->yearIndex = 0;
				$this->seasonIndex = 0;
			}
		}else{
			$this->setDisplayMode($this->displayMode);
		}
	}
    
	public function setClimateTemperatureMap(?ClimateTemperatureMap $map): void{$this->climateTemperatureMap = $map;}
    public function setClimateBaseMaps(array $maps): void{$this->climateBaseMaps = $maps;}	// @param array<string, ClimateBaseMap> $maps
    public function setClimateSeasonalMaps(array $maps): void{$this->climateSeasonalMaps = $maps;}	// @param array<string, array<string, ClimateSeasonalMap>> $maps
    public function setWaterLevel(int $waterLevel): void{$this->waterLevel = $waterLevel;}
	public function setClimateHumidityMaps(array $maps): void{$this->climateHumidityMaps = $maps;}	// @param array<string, array<string, ClimateHumidityMap>> $maps
	public function setDisplayMode(int $index): void{$modes = $this->getAvailableModes();$count = count($modes);$this->displayMode = $count > 0 ? (($index % $count) + $count) % $count : 0;}
	public function setYearIndex(int $index): void{$count = count(self::YEARS);$this->yearIndex = (($index % $count) + $count) % $count;}
    public function setSeasonIndex(int $index): void{$count = count(self::SEASONS);$this->seasonIndex = (($index % $count) + $count) % $count;}
	public function setClimateDayNightMaps(array $maps): void{$this->climateDayNightMaps = $maps;}
	
	public function nextDisplayMode(): void{$this->setDisplayMode($this->displayMode + 1);}
    public function previousDisplayMode(): void{$this->setDisplayMode($this->displayMode - 1);}
    public function nextYear(): void{$this->setYearIndex($this->yearIndex + 1);}
    public function nextSeason(): void{$this->setSeasonIndex($this->seasonIndex + 1);}
 
    private function isSeasonStage(?int $stage): bool{return defined(SimulationState::class . '::STAGE_SEASONS') && $stage === constant(SimulationState::class . '::STAGE_SEASONS');}
	
	public function isDaytime(): bool{return $this->daytime;}
	private function clampPosition(): void{$this->x = max(0, min($this->x, $this->getMaxX()));$this->z = max(0, min($this->z, $this->getMaxZ()));}
	public function toggleDaytime(): void{$this->daytime = !$this->daytime;}
	private array $climatePhenomenonRegions = [];	// @var list<ClimatePhenomenonRegion>
	private int $phenomenonIndex = 0;

	// @param array $regions
	public function setClimatePhenomenonRegions(array $regions): void{
		$regions = array_values($regions);
		if($this->climatePhenomenonRegions === $regions){
			return;
		}
		$this->climatePhenomenonRegions = $regions;
		$this->phenomenonCellIndex = [];
		foreach($regions as $region){
			$type = $region->getPhenomenon();
			foreach($region->getCells() as $cell){
				$key = $cell->getX() . ':' . $cell->getZ();
				$this->phenomenonCellIndex[$type][$key] = true;
			}
		}
	}
	
	public function getSelectedPhenomenonType(): string{return self::PHENOMENA[$this->phenomenonIndex] ?? 'rain';}
	
	public function previousPhenomenon(): void{
		$count = count(self::PHENOMENA);
		$this->phenomenonIndex = ($this->phenomenonIndex - 1 + $count) % $count;
	}
	
	public function nextPhenomenon(): void{
		$count = count(self::PHENOMENA);
		$this->phenomenonIndex = ($this->phenomenonIndex + 1) % $count;
	}
	
	// Возвращает погодные явления, присутствующие в указанной клетке. @return list<string>
	public function getPhenomenaAt(int $x, int $z): array{
		$key = $x . ':' . $z;
		$result = [];
		foreach($this->phenomenonCellIndex as $type => $cells){
			if(isset($cells[$key])){
				$result[] = $type;
			}
		}
		return $result;
	}

}