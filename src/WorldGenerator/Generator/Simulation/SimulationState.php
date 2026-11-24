<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Mass\Fragmenter;

use WorldGenerator\Generator\Climate\ClimateSettings;
use WorldGenerator\Generator\Climate\ClimateLandscapeMap;
use WorldGenerator\Generator\Climate\ClimateTemperatureMap;
use WorldGenerator\Generator\Climate\ClimateBaseMap;
use WorldGenerator\Generator\Climate\ClimateSeasonalMap;
use WorldGenerator\Generator\Climate\ClimateSeasonalGenerator;
use WorldGenerator\Generator\Climate\ClimateTemperatureCell;
use WorldGenerator\Generator\Climate\ClimateDayNightMap;
use WorldGenerator\Generator\Climate\ClimateDayNightGenerator;
use WorldGenerator\Generator\Climate\ClimateHumidityMap;
use WorldGenerator\Generator\Climate\ClimateHumidityCalculator;

use WorldGenerator\Generator\Objects\ObjectLayout;
use WorldGenerator\Generator\Formation\Force;

final class SimulationState{

    public const MAX_STEPS_PER_CYCLE = 30;

    public const STAGE_FORMATION = 1;
    public const STAGE_ISLANDS = 2;
    public const STAGE_YEARS = 3;
    public const STAGE_SEASONS = 4;
	public const STAGE_DAY_NIGHT = 5;
	public const STAGE_PHENOMENA = 6;

    // @var array<string, ClimateBaseMap>
    private array $climateBaseMaps = [];
	
    // @var array<string, array<string, ClimateSeasonalMap>>
    private array $climateSeasonalMaps = [];

    private ?ClimateTemperatureMap $climateTemperatureMap = null;
    private ?ClimateLandscapeMap $climateLandscapeMap = null;
	
	// @var array<string, array<string, array<string, ClimateDayNightMap>>>
	private array $climateDayNightMaps = [];
	
	// @var array<string, array<string, ClimateHumidityMap>>
	private array $climateHumidityMaps = [];
	
	// @var ClimatePhenomenonRegion[]
	private array $climatePhenomenonRegions = [];

    private MassMap $map;
    private int $stage = self::STAGE_FORMATION;

    private int $seed;
    private ObjectLayout $objectLayout;

    private FormationSimulation $formation;
    private IslandSimulation $islands;
	private ?Fragmenter $fragmenter = null;
	private bool $formationProcessSucceeded = false;
	
    private int $cycle = 0;
    private int $step = 0;
    private int $stepsInCycle = 0;
    private int $targetAdditionalCycles = 0;
    private int $totalSteps = 0;

    private bool $running = false;
    private bool $finished = false;

    private int $cycleAxisMovement = 0;
    private int $cycleAxisSize;

    private ClimateSettings $climateSettings;
	
	private int $landPercent;
	
    public function __construct(MassMap $map,ObjectLayout $objectLayout,int $landPercent,int $seed,ClimateSettings $climateSettings){
        $this->map = $map;
        $this->objectLayout = $objectLayout;
        $this->seed = $seed;
		$this->landPercent = $landPercent;
        $this->climateSettings = $climateSettings;
        $this->cycleAxisSize = $map->getSizeX();
        $this->formation = new FormationSimulation($map,$objectLayout,$landPercent,$seed);
        $this->islands = new IslandSimulation($map,$seed);
    }


    public function setMap(MassMap $map): void{$this->map = $map;$this->formation->setMap($map);$this->islands->setMap($map);}
	public function setTargetAdditionalCycles(int $cycles): void{$this->targetAdditionalCycles = max(0, $cycles);}
	
    public function getMap(): MassMap{return $this->map;}
    public function getCycle(): int{return $this->cycle;}
    public function getStep(): int{return $this->step;}
    public function getStepsInCycle(): int{return $this->stepsInCycle;}
    public function getTotalSteps(): int{return $this->totalSteps;}
    public function getStage(): int{return $this->stage;}
	public function getFragments(): array{return $this->islands->getFragments();}
    public function getTargetAdditionalCycles(): int{return $this->targetAdditionalCycles;}
    // @return SimulationObjectState[]
    public function getObjects(): array{return $this->formation->getObjects();}
    public function getObject(int $objectId): ?SimulationObjectState{return $this->formation->getObject($objectId);}
    // @return array<int, array{step:int, objects:int[], x:int, z:int, pressure:int}>
    public function getCollisions(): array{return $this->formation->getCollisions();}
	public function getForce(): Force{return $this->formation->getForce();}
    public function getCycleAxisMovement(): int{return $this->cycleAxisMovement;}
    public function getCycleAxisSize(): int{return $this->cycleAxisSize;}
    public function getObjectCount(): int{return $this->formation->getObjectCount();}
    public function getIslandSimulation(): IslandSimulation{return $this->islands;}
    public function getFormationSimulation(): FormationSimulation{return $this->formation;}
    public function getFormationEventCount(int $x, int $z): int{return $this->formation->getFormationEventCount($x, $z);}
	public function getClimateTemperatureMap(): ?ClimateTemperatureMap{return $this->climateTemperatureMap;}
    public function getClimateLandscapeMap(): ?ClimateLandscapeMap{return $this->climateLandscapeMap;}
	// @return array<string, ClimateBaseMap>
    public function getClimateBaseMaps(): array{return $this->climateBaseMaps;}
    public function getClimateBaseMap(string $state): ?ClimateBaseMap{return $this->climateBaseMaps[strtolower($state)] ?? null;}
    // @return array<string, array<string, ClimateSeasonalMap>>
    public function getClimateSeasonalMaps(): array{return $this->climateSeasonalMaps;}
    public function getClimateSeasonalMap(string $state,string $season): ?ClimateSeasonalMap{return $this->climateSeasonalMaps[strtolower($state)][strtolower($season)] ?? null;}
	// @return array<string, array<string, array<string, ClimateDayNightMap>>>
	public function getClimateDayNightMaps(): array{return $this->climateDayNightMaps;}
	public function getClimateDayNightMap(string $year,string $season,string $time): ?ClimateDayNightMap{return $this->climateDayNightMaps[strtolower($year)][strtolower($season)][strtolower($time)] ?? null;}
	// @return array<string, array<string, ClimateHumidityMap>>
	public function getClimateHumidityMaps(): array{return $this->climateHumidityMaps;}
	public function getClimateHumidityMap(string $yearType,string $season): ?ClimateHumidityMap{return $this->climateHumidityMaps[strtolower($yearType)][strtolower($season)] ?? null;}
	// @return ClimatePhenomenonRegion[]
	public function getClimatePhenomenonRegions(): array{return $this->climatePhenomenonRegions;}

	public function isClimateStage(): bool{return $this->stage === self::STAGE_YEARS;}
	public function isFormationStage(): bool{return $this->stage === self::STAGE_FORMATION;}
    public function isIslandStage(): bool{return $this->stage === self::STAGE_ISLANDS;}
    public function isYearMapsStage(): bool{return $this->stage === self::STAGE_YEARS;}
    public function isSeasonalStage(): bool{return $this->stage === self::STAGE_SEASONS;}
	public function isDayNightStage(): bool{return $this->stage === self::STAGE_DAY_NIGHT;}
    public function isFinished(): bool{return $this->finished;}

    // @return \WorldGenerator\Generator\Mass\Fragment[]
	public function isRunning(): bool{return $this->running;}
    public function start(): void{$this->running = true;$this->finished = false;}
    public function stop(): void{$this->running = false;}
    public function finish(): void{$this->running = false;$this->finished = true;}

	public function nextStep(): void{
		++$this->step;
		++$this->stepsInCycle;
		++$this->totalSteps;
	}

	public function nextCycle(): void{
		++$this->cycle;
		$this->step = 0;
		$this->stepsInCycle = 0;
		$this->cycleAxisMovement = 0;
	}
	
	public function registerStep(): void{
		++$this->step;
		++$this->stepsInCycle;
		++$this->totalSteps;
	}

    public function canContinue(): bool{return true;}
	
	public function canStep(): bool{
		if(!$this->running || !$this->canContinue()){
			return false;
		}
		if($this->stage === self::STAGE_FORMATION){
			return $this->stepsInCycle < $this->cycleAxisSize;
		}
		if($this->stage === self::STAGE_ISLANDS){
			return $this->getFragments() !== [];
		}
		return false;
	}

    public function moveObjects(): void{$this->formation->moveObjects($this->totalSteps);}
	
    public function stepForce(): bool{
        $result = $this->formation->stepForce($this->step,$this->totalSteps);
        if(!$result){
            return false;
        }
        $this->cycleAxisMovement += abs($this->formation->getLastMovement()->getX());
        return true;
    }

    public function stepIslands(): bool{return $this->islands->step($this->totalSteps);}
	
	// Запускает пошаговое формирование фрагментов.
	public function startFormationStage(int $fragmentCount): bool{
		if($this->stage !== self::STAGE_FORMATION || $this->fragmenter !== null || $fragmentCount <= 0){
			return false;
		}
		$this->formationProcessSucceeded = false;
		$this->fragmenter = new Fragmenter($this->map,$fragmentCount,$this->seed,$this->objectLayout,$this->formation->getForce());
		return true;
	}

	/* Выполняет часть формирования.
	 * @return ?bool
	 * null  — процесс ещё выполняется;
	 * true  — процесс завершён успешно;
	 * false — процесс завершён с ошибкой или без результата. */
	public function advanceFormationStage(int $budget = 100): ?bool{
		if($this->fragmenter === null){
			return false;
		}
		if(!$this->fragmenter->advance($budget)){
			return null;
		}
		$fragments = $this->fragmenter->getResult();
		$this->fragmenter = null;
		if($fragments === []){
			$this->formationProcessSucceeded = false;
			return false;
		}
		$this->islands->setFragments($fragments);
		$this->islands->setFragmentDirections();
		$this->stage = self::STAGE_ISLANDS;
		$this->cycle = 0;
		$this->step = 0;
		$this->stepsInCycle = 0;
		$this->totalSteps = 0;
		$this->cycleAxisMovement = 0;
		$this->formationProcessSucceeded = true;
		return true;
	}

	// Текущий прогресс формирования.
	public function getFormationProgress(): float{return $this->fragmenter?->getProgress() ?? ($this->formationProcessSucceeded ? 1.0 : 0.0);}
	// Текущий статус формирования.
	public function getFormationStatus(): string{return $this->fragmenter?->getStatus() ?? ($this->formationProcessSucceeded ? 'Формирование завершено' : 'Формирование не запущено');}

	// Проверяет, запущен ли процесс формирования.
	public function isFormationProcessing(): bool{return $this->fragmenter !== null;}

    /* Этап 3: сохраняет три годовые карты и карту ландшафта.
     * @param array<string, ClimateBaseMap> $baseMaps */
    public function completeYearMapsStage(array $baseMaps,ClimateLandscapeMap $landscapeMap): bool{
        if($this->stage !== self::STAGE_ISLANDS){
            return false;
        }
        foreach(['normal', 'hot', 'cold'] as $name){
            if(!isset($baseMaps[$name]) || !$baseMaps[$name] instanceof ClimateBaseMap){
                return false;
            }
        }
		$this->climateSeasonalMaps = [];
		$this->climateDayNightMaps = [];
		$this->climateHumidityMaps = [];
		$this->climatePhenomenonRegions = [];
        $this->climateLandscapeMap = $landscapeMap;
        $this->climateBaseMaps = $baseMaps;
        $this->climateTemperatureMap = null;
        $this->stage = self::STAGE_YEARS;
        return true;
    }

	public function rebuildClimateMaps(
		array $baseMaps,
		ClimateLandscapeMap $landscapeMap,
		array $seasonalHumiditySettings,
		int $waterLevel,
		array $dayNightSettings,
		array $phenomenonSettings
	): bool{
		$previousStage = $this->stage;

		switch($previousStage){
			case self::STAGE_YEARS:
				$this->stage = self::STAGE_ISLANDS;

				$success = $this->completeYearMapsStage(
					$baseMaps,
					$landscapeMap
				);
				break;

			case self::STAGE_SEASONS:
				$this->stage = self::STAGE_YEARS;

				$success = $this->completeSeasonalStage(
					$seasonalHumiditySettings,
					$waterLevel
				);
				break;

			case self::STAGE_DAY_NIGHT:
				$this->stage = self::STAGE_SEASONS;

				$success = $this->completeDayNightStage(
					$dayNightSettings
				);
				break;

			case self::STAGE_PHENOMENA:
				$this->stage = self::STAGE_DAY_NIGHT;

				$success = $this->completePhenomenaStage(
					$phenomenonSettings
				);
				break;

			default:
				return false;
		}

		if(!$success){
			$this->stage = $previousStage;
			return false;
		}

		return true;
	}

	// Этап 4: формирует сезонные карты и рассчитывает влажность.
	public function completeSeasonalStage(array $seasonalHumiditySettings,int $waterLevel): bool{
		if($this->stage !== self::STAGE_YEARS || $this->climateBaseMaps === [] || $this->climateLandscapeMap === null){
			return false;
		}
		$seasonalMaps = (new ClimateSeasonalGenerator())->generate($this->climateBaseMaps);
		if(!isset($seasonalMaps['normal']['summer'])){
			return false;
		}
		$normalSummer = $seasonalMaps['normal']['summer'];
		$temperatureMap = new ClimateTemperatureMap($normalSummer->getSizeX(),$normalSummer->getSizeZ());
		for($z = 0; $z < $normalSummer->getSizeZ(); ++$z){
			for($x = 0; $x < $normalSummer->getSizeX(); ++$x){
				$cell = $normalSummer->get($x, $z);
				if($cell === null){
					continue;
				}
				$temperatureMap->set(
					new ClimateTemperatureCell($x,$z,$cell->getTemperature())
				);
			}
		}
		$calculator = new ClimateHumidityCalculator();
		$humidityMaps = [];
		foreach(['normal', 'hot', 'cold'] as $year){
			foreach(['spring', 'summer', 'autumn', 'winter'] as $season){
				$seasonalMap = $seasonalMaps[$year][$season] ?? null;
				if(!$seasonalMap instanceof ClimateSeasonalMap){
					return false;
				}
				$oppositeSeason = match($season){
					'spring' => 'autumn',
					'summer' => 'winter',
					'autumn' => 'spring',
					'winter' => 'summer',
					default => null,
				};
				if($oppositeSeason === null){
					return false;
				}
				$northHumidity = $seasonalHumiditySettings[$year][$season]['north'] ?? null;
				$southHumidity = $seasonalHumiditySettings[$year][$oppositeSeason]['south'] ?? null;
				if(
					!is_numeric($northHumidity)
					|| !is_finite((float) $northHumidity)
					|| (float) $northHumidity < -1.0
					|| (float) $northHumidity > 1.0
					|| !is_numeric($southHumidity)
					|| !is_finite((float) $southHumidity)
					|| (float) $southHumidity < -1.0
					|| (float) $southHumidity > 1.0
				){
					return false;
				}
				$humidityMaps[$year][$season] = $calculator->generate($this->climateLandscapeMap,$seasonalMap,(float) $northHumidity,(float) $southHumidity,$waterLevel,$year,$season);
			}
		}
		$this->climateSeasonalMaps = $seasonalMaps;
		$this->climateTemperatureMap = $temperatureMap;
		$this->climateHumidityMaps = $humidityMaps;
		$this->climateDayNightMaps = [];
		$this->climatePhenomenonRegions = [];
		$this->stage = self::STAGE_SEASONS;
		return true;
	}
	
	// Этап 5: формирует суточные карты.
	public function completeDayNightStage(array $settings): bool{
		if(
			$this->stage !== self::STAGE_SEASONS
			|| $this->climateSeasonalMaps === []
			|| $this->climateLandscapeMap === null
		){
			return false;
		}

		$maps = (new ClimateDayNightGenerator())->generate(
			$this->climateSeasonalMaps,
			$this->climateLandscapeMap,
			$settings
		);

		foreach(['normal', 'hot', 'cold'] as $year){
			foreach(['spring', 'summer', 'autumn', 'winter'] as $season){
				foreach(['day', 'night'] as $time){
					if(
						!isset($maps[$year][$season][$time])
						|| !$maps[$year][$season][$time] instanceof ClimateDayNightMap
					){
						return false;
					}
				}
			}
		}

		$this->climateDayNightMaps = $maps;
		$this->stage = self::STAGE_DAY_NIGHT;

		return true;
	}
	
	public function completePhenomenaStage(array $settings = []): bool{
		if(
			$this->stage !== self::STAGE_DAY_NIGHT
			|| $this->climateSeasonalMaps === []
			|| $this->climateHumidityMaps === []
			|| $this->climateLandscapeMap === null
		){
			return false;
		}

		$generator = new \WorldGenerator\Generator\Climate\ClimatePhenomenonGenerator();

		$regions = $generator->generate(
			$this->climateSeasonalMaps,
			$this->climateHumidityMaps,
			$this->climateLandscapeMap,
			$this->seed,
			$settings
		);

		$this->climatePhenomenonRegions = $regions;
		$this->stage = self::STAGE_PHENOMENA;

		return true;
	}
	
	// Геттеры для сериализации
	public function getSeed(): int{return $this->seed;}
	public function getLandPercent(): int{return $this->landPercent;}
	public function getClimateSettings(): ClimateSettings{return $this->climateSettings;}
	public function getObjectLayout(): ObjectLayout{return $this->objectLayout;}

	// Сеттеры климата для восстановления
	public function setClimateBaseMaps(array $maps): void{$this->climateBaseMaps=$maps;}
	public function setClimateSeasonalMaps(array $maps): void{$this->climateSeasonalMaps=$maps;}
	public function setClimateTemperatureMap(?ClimateTemperatureMap $map): void{$this->climateTemperatureMap=$map;}
	public function setClimateLandscapeMap(?ClimateLandscapeMap $map): void{$this->climateLandscapeMap=$map;}
	public function setClimateDayNightMaps(array $maps): void{$this->climateDayNightMaps=$maps;}
	public function setClimateHumidityMaps(array $maps): void{$this->climateHumidityMaps=$maps;}
	public function setClimatePhenomenonRegions(array $regions): void{$this->climatePhenomenonRegions=$regions;}
	public function setClimateSettings(ClimateSettings $settings): void{$this->climateSettings = $settings;}
	
	/* Восстанавливает SimulationState из массива.
	 * Карты, объекты и климат заполняются через SimulationSerializer.
	 * @param array<string,mixed> $data */
	public static function fromArray(array $data,MassMap $map,ObjectLayout $objectLayout,ClimateSettings $climateSettings): ?self{
		if(!isset($data["stage"], $data["cycle"], $data["step"], $data["seed"])){
			return null;
		}
		$sim = new self($map,$objectLayout,(int)($data["landPercent"] ?? 10),(int)$data["seed"],$climateSettings);
		$sim->stage = (int)$data["stage"];
		$sim->cycle = (int)$data["cycle"];
		$sim->step = (int)$data["step"];
		$sim->stepsInCycle = (int)$data["stepsInCycle"];
		$sim->totalSteps = (int)$data["totalSteps"];
		$sim->cycleAxisMovement = (int)$data["cycleAxisMovement"];
		$sim->cycleAxisSize = (int)$data["cycleAxisSize"];
		$sim->targetAdditionalCycles = (int)$data["targetAdditionalCycles"];
		$sim->running = (bool)$data["running"];
		$sim->finished = (bool)$data["finished"];
		return $sim;
	}
}