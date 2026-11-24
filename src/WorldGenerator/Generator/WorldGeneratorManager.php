<?php

declare(strict_types=1);

namespace WorldGenerator\Generator;

use pocketmine\world\WorldCreationOptions;

use WorldGenerator\WorldGenerator;
use WorldGenerator\Generator\GenerationContext;
use WorldGenerator\Generator\Climate\ClimateLandscapeAnalyzer;
use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Mass\MassGenerator;
use WorldGenerator\Generator\Objects\ObjectLayoutGenerator;
use WorldGenerator\Generator\Geology\RockRegistry;
use WorldGenerator\Generator\Structure\StructureType;

use WorldGenerator\Generator\Climate\ClimateBaseMap;
use WorldGenerator\Generator\Climate\ClimateTemperatureMap;
use WorldGenerator\Generator\Climate\ClimateLandscapeMap;
use WorldGenerator\Generator\Climate\ClimateSettings;
use WorldGenerator\Generator\Climate\ClimateBaseMapGenerator;
use WorldGenerator\Generator\Climate\ClimateCycloneRouteGenerator;
use WorldGenerator\Generator\Climate\ClimateSeasonalGenerator;
use WorldGenerator\Generator\Climate\ClimateDayNightMap;

use WorldGenerator\Generator\Debug\DebugExporter;
use WorldGenerator\Generator\Debug\ClimateDebugExporter;
use WorldGenerator\Generator\Simulation\SimulationState;
use WorldGenerator\Generator\Simulation\SimulationSerializer;

use WorldGenerator\Generator\Generators\LandscapeGenerator;

use WorldGenerator\Language\Translator;

final class WorldGeneratorManager{
/* 	WorldGeneratorManager
			│
			▼
	   RockRegistry
		│    │    │
		│    │    └── FormationSimulation
		│    │
		│    └─────── MassGenerator
		│
		└──────────── MassMap
						 │
						 ▼
					 MassCell */
					 
    private WorldGenerator $plugin;
	private ?MassMap $debugMassMap=null;
	private ?SimulationState $simulationState=null;
	private ?DebugExporter $simulationExporter=null;
	private RockRegistry $rockRegistry;
	private ?ClimateTemperatureMap $climateTemperatureMap = null;
	private ?ClimateLandscapeMap $climateLandscapeMap = null;
	private string $worldPath="";
	private int $waterLevel=0;
	
	// Погода
	private ?ClimateDebugExporter $climateDebugExporter = null;
	private float $polarTemperature = -5.0;
	private float $equatorTemperature = 10.0;
	private int $polarShift = 0;
	
	private ?SimulationSerializer $simulationSerializer = null;
	
	// Генерация
	private ?GenerationContext $generationContext = null;
	private bool $playableWorldCreated = false;
	private string $worldCreationError = "";
	
	private int $worldHeight = 320;
	private int $seed = 0;
	
	public function __construct(WorldGenerator $plugin){
		$this->plugin=$plugin;
		$this->rockRegistry = new RockRegistry();
	}

	public function generate(GenerationContext $context): bool{
		$this->worldHeight = $context->getWorldHeight();
		$this->seed = $context->getSeed();
		$this->generationContext = $context;
		$this->playableWorldCreated = false;
		$this->worldCreationError = "";
		$this->waterLevel = $context->getWaterLevel();
		$objectLayoutGenerator=new ObjectLayoutGenerator($context->getSectorCountX(),$context->getSectorCountZ(),$context->getIslandCount(),$context->getSeed());
		$objectLayout = $objectLayoutGenerator->generate();
		$massMap = new MassMap($context->getSectorCountX(),$context->getSectorCountZ(),$this->rockRegistry);
		$generatorData = $this->generationContext->getPreset()->getGenerator();
		error_log("[ClimateDebug] Preset: " . json_encode($generatorData->toArray(),JSON_UNESCAPED_UNICODE));
		$this->polarTemperature = $generatorData->getPolarTemperature();
		$this->equatorTemperature = $generatorData->getEquatorTemperature();
		$this->polarShift = $generatorData->getPolarShift();
		error_log(
			"[ClimateDebug] Manager: Polar={$this->polarTemperature}, "
			. "Equator={$this->equatorTemperature}, "
			. "Shift={$this->polarShift}"
		);
		$climateSettings = new ClimateSettings($this->polarTemperature,$this->equatorTemperature,$this->polarShift);
		$structurePercents = [
			StructureType::MOUNTAIN_RANGE => $generatorData->getMountainRangesPercent(),
			StructureType::CAPE => $generatorData->getCapesPercent(),
			StructureType::BASIN => $generatorData->getBasinsPercent()
		];
		$massGenerator = new MassGenerator($massMap,$context->getLandPercent(),$context->getAquiferLevel(),$objectLayout,$context->getSeed(),$this->rockRegistry,$structurePercents);
		$massGenerator->generate();
		$this->debugMassMap=$massMap;
		// Сохраняем путь к папке мира для последующего экспорта погоды.
		$worldPath = rtrim($this->plugin->getDataFolder(),DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR. "generations". DIRECTORY_SEPARATOR. $context->getWorldName();
		$this->worldPath=$worldPath;
		$this->simulationState = new SimulationState($massMap,$objectLayout,$context->getLandPercent(),$context->getSeed(),$climateSettings);
		$this->simulationExporter = new DebugExporter($worldPath,$context->getWaterLevel(),$context->getWorldHeight(),$context->getSeed());
		$this->simulationExporter->exportMass($massMap);
		$this->climateDebugExporter = new ClimateDebugExporter($worldPath);
		$this->simulationExporter->exportSimulation(
			$this->simulationState,
			null
		);
		return true;
	}
	
	public function getDebugMassMap(): ?MassMap{return $this->debugMassMap;}
	public function getSimulationState(): ?SimulationState{return $this->simulationState;}
	public function getClimateLandscapeMap(): ?ClimateLandscapeMap{return $this->climateLandscapeMap;}
	public function getWaterLevel(): int{return $this->waterLevel;}
	public function getGenerationContext(): ?GenerationContext{return $this->generationContext;}
	
	// Выполняет один шаг симуляции
	public function nextSimulationStep(): bool{
		if($this->simulationState === null){
			return false;
		}
		$simulation = $this->simulationState;
		if(!$simulation->isRunning()){
			$simulation->start();
		}
		if(!$simulation->canStep()){
			return false;
		}
		if($simulation->getStage() === SimulationState::STAGE_FORMATION){
			// Один ход — перемещение всей массы.
			if(!$simulation->stepForce()){
				return false;
			}
			$simulation->registerStep();
			// Цикл завершается после количества ходов,
			// равного ширине карты.
			if($simulation->getStepsInCycle() >= $simulation->getCycleAxisSize()){
				$simulation->nextCycle();
			}
		}elseif($simulation->getStage() === SimulationState::STAGE_ISLANDS){
			// Один ход — попытка перемещения одного острова.
			if(!$simulation->stepIslands()){
				return false;
			}
			$simulation->registerStep();
			// Цикл завершается, когда каждый остров сделал ход.
			if($simulation->getIslandSimulation()->isCycleCompleted()){
				$simulation->nextCycle();
			}
		}else{
			return false;
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($simulation,$this->climateLandscapeMap);
		}
		$this->saveGeneration();
		return true;
	}

	// Выполняет оставшиеся шаги текущего цикла
	public function completeSimulationCycle(): bool{
		if($this->simulationState === null){
			return false;
		}
		$simulation = $this->simulationState;
		if(!$simulation->isRunning()){
			$simulation->start();
		}
		$initialCycle = $simulation->getCycle();
		while($simulation->canStep() && $simulation->getCycle() === $initialCycle){
			if(!$this->nextSimulationStep()){
				break;
			}
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($simulation,$this->climateLandscapeMap);
		}
		$this->saveGeneration();
		return true;
	}
	
	// Запускает инкрементальное разделение массы на острова.
	public function startFormationStage(int $fragmentCount): bool{
		if($this->simulationState === null){
			return false;
		}
		return $this->simulationState->startFormationStage($fragmentCount);
	}

	/* Выполняет часть разделения за один вызов.
	 * null  — обработка продолжается;
	 * true  — этап успешно завершён;
	 * false — ошибка или не удалось создать острова. */
	public function advanceFormationStage(int $budget = 100): ?bool{
		if($this->simulationState === null){
			return false;
		}
		$result = $this->simulationState->advanceFormationStage($budget);
		if($result === null){
			return null;
		}
		if(!$result){
			return false;
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($this->simulationState,$this->climateLandscapeMap);
		}
		$this->saveGeneration();
		return true;
	}

	// Возвращает прогресс разделения массы.
	public function getFormationProgress(): float{return $this->simulationState?->getFormationProgress() ?? 0.0;}
	// Возвращает описание текущей операции.
	public function getFormationStatus(): string{return $this->simulationState?->getFormationStatus() ?? 'Симуляция не создана';}

	// Создаёт годовые карты
	public function completeYearMapsStage(): bool{
		if($this->simulationState === null){
			return false;
		}
		$landscapeMap = (new ClimateLandscapeAnalyzer())->analyze($this->simulationState->getMap(),$this->waterLevel);
		$generatorData = $this->generationContext?->getPreset()->getGenerator();
		if($generatorData === null){
			return false;
		}
		$baseMaps = (new ClimateBaseMapGenerator())->generate($landscapeMap,$this->polarTemperature,$this->equatorTemperature,$this->polarShift,$this->waterLevel,$generatorData->getAnnualClimateSettings());
		$this->plugin->getLogger()->debug("Year maps keys: " . implode(", ", array_keys($baseMaps)));
		foreach($baseMaps as $key => $map){
			$this->plugin->getLogger()->debug("Year map {$key}: " . get_debug_type($map));
		}

		foreach(['normal', 'hot', 'cold'] as $name){
			if(!isset($baseMaps[$name])){
				$this->plugin->getLogger()->error("Missing climate map: {$name}");
			}elseif(!$baseMaps[$name] instanceof ClimateBaseMap){
				$this->plugin->getLogger()->error("Invalid climate map {$name}: " . get_debug_type($baseMaps[$name]));
			}
		}
		if(!$this->simulationState->completeYearMapsStage($baseMaps,$landscapeMap)){
			return false;
		}
		$this->climateLandscapeMap = $landscapeMap;
		if($this->climateDebugExporter !== null){
			$this->climateDebugExporter->exportLandscape($landscapeMap);
			$this->climateDebugExporter->exportBaseMaps($baseMaps);
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($this->simulationState,$landscapeMap);
		}
		$this->saveGeneration();
		return true;
	}

	// Создаёт сезонные карты
	public function completeSeasonalStage(): bool{
		if($this->simulationState === null || $this->generationContext === null){
			return false;
		}
		$generatorData = $this->generationContext->getPreset()->getGenerator();
		$humiditySettings = $generatorData->getSeasonalHumiditySettings();
		if(!$this->simulationState->completeSeasonalStage($humiditySettings,$this->waterLevel)){
			return false;
		}
		$maps = $this->simulationState->getClimateSeasonalMaps();
		if($this->climateDebugExporter !== null){
			$this->climateDebugExporter->exportSeasonalMaps($maps);
			$routeGenerator = new ClimateCycloneRouteGenerator();
			$sizeX = $this->climateLandscapeMap?->getSizeX() ?? 0;
			$sizeZ = $this->climateLandscapeMap?->getSizeZ() ?? 0;
			if($sizeX > 0 && $sizeZ > 0){
				$chains = $routeGenerator->generateSeasonalChains($sizeX, $sizeZ);
				$this->climateDebugExporter->exportSeasonalChains($chains,$sizeX,$sizeZ);
			}
			$this->climateDebugExporter->exportHumidityMaps($this->simulationState->getClimateHumidityMaps());
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($this->simulationState,$this->climateLandscapeMap);
		}
		$this->saveGeneration();
		return true;
	}

	// Создаёт суточные карты
	public function completeDayNightStage(): bool{
		if($this->simulationState === null || $this->generationContext === null){
			return false;
		}
		$generatorData = $this->generationContext->getPreset()->getGenerator();
		if(!$this->simulationState->completeDayNightStage(
			$generatorData->getDayNightSettings()
		)){
			return false;
		}
		$maps = $this->simulationState->getClimateDayNightMaps();
		if($this->climateDebugExporter !== null){
			$this->climateDebugExporter->exportDayNightMaps($maps);
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($this->simulationState,$this->climateLandscapeMap);
		}
		$this->saveGeneration();
		return true;
	}
	
	// Создаёт явления погоды
	public function completePhenomenaStage(array $settings = []): bool{
		if($this->simulationState === null){
			return false;
		}
		if(!$this->simulationState->completePhenomenaStage($settings)){
			return false;
		}
		$generatorData = $this->generationContext?->getPreset()->getGenerator();
		$settings = $generatorData?->getPhenomenonSettings() ?? [];
		$regions = $this->simulationState->getClimatePhenomenonRegions();
		if($this->climateDebugExporter !== null){
			$this->climateDebugExporter->exportPhenomenonRegions($regions);
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($this->simulationState,$this->climateLandscapeMap);
		}
		$this->saveGeneration();
		return true;
	}
	
	private function getSimulationSerializer(): SimulationSerializer{
		if($this->simulationSerializer === null){
			$this->simulationSerializer = new SimulationSerializer($this->rockRegistry);
		}
		return $this->simulationSerializer;
	}
	
	// Сохраняет текущее состояние генерации в debug/generation_save.json.
	public function saveGeneration(): bool{
		if($this->simulationState === null || $this->simulationExporter === null){
			return false;
		}
		$payload = [
			"meta" => [
				"worldHeight" => $this->worldHeight,
				"seed" => $this->seed,
				"savedAt" => time(),
				"waterLevel" => $this->waterLevel,
				"polarTemperature" => $this->polarTemperature,
				"equatorTemperature" => $this->equatorTemperature,
				"polarShift" => $this->polarShift,
				"worldPath" => $this->worldPath,
				"preset" => $this->generationContext?->getPreset()->toArray(),
			],
			"state" => $this->getSimulationSerializer()->stateToArray($this->simulationState),
		];
		$this->simulationExporter->exportGenerationSave($payload);
		return true;
	}

	/* Загружает сохранённую генерацию из debug/generation_save.json указанного мира.
	 * Восстанавливает состояние симуляции и путь к папке мира. */
	public function loadGeneration(string $worldPath): bool{
		$t = $this->plugin->getTranslator();
		$this->worldPath = $worldPath;
		// Сброс старых настроек
		$this->generationContext = null;
		$this->simulationState = null;
		$this->debugMassMap = null;
		$this->climateLandscapeMap = null;
		$this->climateTemperatureMap = null;
		$this->climateDebugExporter = null;
		$this->playableWorldCreated = false;
		$this->worldCreationError = "";
		// На этом этапе контекст ещё не восстановлен.
		$this->simulationExporter = new DebugExporter($worldPath,$this->waterLevel,$this->worldHeight,$this->seed);
		$payload = $this->simulationExporter->importGenerationSave();
		if($payload === null){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send1");
			return false;
		}
		$meta = $payload["meta"] ?? null;
		$state = $payload["state"] ?? null;
		if(!is_array($meta) || !is_array($state)){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send2");
			return false;
		}
		// Для полноценного восстановления необходим сохранённый пресет.
		$presetData = $meta["preset"] ?? null;
		if(!is_array($presetData)){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send3");
			return false;
		}
		try{
			
			$preset = \WorldGenerator\Config\Preset::fromArray($presetData);	// Восстанавливаем настройки мира и остальные параметры пресета.
			$this->generationContext = new GenerationContext($preset);			// Preset содержит настройки WorldData, необходимые контексту.
			$this->worldHeight = $this->generationContext->getWorldHeight();	// Основные параметры берём из восстановленного контекста.
			$this->seed = $this->generationContext->getSeed();
			$this->waterLevel = $this->generationContext->getWaterLevel();
			// Дополнительные параметры генерации.
			$this->polarTemperature = (float) ($meta["polarTemperature"] ?? -5.0);
			$this->equatorTemperature = (float) ($meta["equatorTemperature"] ?? 10.0);
			$this->polarShift = (int) ($meta["polarShift"] ?? $preset->getPolarShift());
			// Читаем состояние симуляции.
			$simulation = $this->getSimulationSerializer()->stateFromArray($state);
			if($simulation === null){
				$this->generationContext = null;
				$this->worldCreationError = $t->get("menu.worldgeneratormanager.send4");
				return false;
			}
			$this->simulationState = $simulation;
			$this->debugMassMap = $simulation->getMap();
			$this->climateLandscapeMap = $simulation->getClimateLandscapeMap();
			// Экспортёр пересоздаётся с восстановленными параметрами.
			$this->simulationExporter = new DebugExporter($worldPath,$this->waterLevel,$this->worldHeight,$this->seed);
			$this->climateDebugExporter = new ClimateDebugExporter($worldPath);
			$this->playableWorldCreated = false;
			$this->worldCreationError = "";
			$this->plugin->getLogger()->info($t->get("menu.worldgeneratormanager.send5"). $this->generationContext->getWorldName());
			return true;
		}catch(\Throwable $e){
			$this->generationContext = null;
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send6") . $e->getMessage();
			$this->plugin->getLogger()->error($this->worldCreationError);
			return false;
		}
	}

	// Проверяет наличие сохранённой генерации в папке мира.
	public function hasGenerationSave(string $worldPath): bool{
		$exporter = new DebugExporter($worldPath, $this->waterLevel);
		return $exporter->hasGenerationSave();
	}
	
	// Создаёт игровой мир на основе завершённой симуляции.
	public function createPlayableWorld(): bool{
		$t = $this->plugin->getTranslator();
		if($this->playableWorldCreated){
			return true;
		}
		$context = $this->generationContext;
		$simulation = $this->simulationState;
		if($context === null || $simulation === null){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send7");
			return false;
		}
		if($simulation->getStage() !== SimulationState::STAGE_PHENOMENA){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send8");
			return false;
		}
		$formationPath = $this->worldPath. DIRECTORY_SEPARATOR. "debug". DIRECTORY_SEPARATOR. "formation.json";
		if(!is_file($formationPath) || !is_readable($formationPath)){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send9");
			return false;
		}
		$json = file_get_contents($formationPath);
		if($json === false || !is_array(json_decode($json, true))){
			$this->worldCreationError = $t->get("menu.worldgeneratormanager.send10");
			return false;
		}

		try{
			$optionsJson = json_encode([
				"landscapePath" => $formationPath,
				"worldHeight" => $context->getWorldHeight(),
			], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

			$options = WorldCreationOptions::create()->setGeneratorClass(LandscapeGenerator::class)->setSeed($context->getSeed())->setGeneratorOptions($optionsJson);
			$created = $this->plugin->getServer()->getWorldManager()->generateWorld($context->getWorldName(),$options,true);
			if(!$created){
				$this->worldCreationError = $t->get("menu.worldgeneratormanager.send11");return false;
			}
			$this->playableWorldCreated = true;
			$this->worldCreationError = "";
			$this->plugin->getLogger()->info("menu.worldgeneratormanager.send12", ["getWorldName" => $context->getWorldName()]);
			return true;
		}catch(\Throwable $e){
			$this->worldCreationError = "menu.worldgeneratormanager.send13" . $e->getMessage();
			$this->plugin->getLogger()->error($this->worldCreationError);
			return false;
		}
	}

	public function isPlayableWorldCreated(): bool{return $this->playableWorldCreated;}
	public function getWorldCreationError(): string{return $this->worldCreationError;}
	
	public function updateClimateSettings(array $values): bool{
		$t = $this->plugin->getTranslator();
		if($this->simulationState === null || $this->generationContext === null){
			return false;
		}
		$stage = $this->simulationState->getStage();
		if(!in_array($stage, [
			SimulationState::STAGE_YEARS,
			SimulationState::STAGE_SEASONS,
			SimulationState::STAGE_DAY_NIGHT,
			SimulationState::STAGE_PHENOMENA
		], true)){
			return false;
		}
		$generatorData = $this->generationContext->getPreset()->getGenerator();
		$this->polarTemperature = (float) ($values["polarTemperature"] ?? $generatorData->getPolarTemperature());
		$this->equatorTemperature = (float) ($values["equatorTemperature"] ?? $generatorData->getEquatorTemperature());
		$this->polarShift = (int) ($values["polarShift"] ?? $generatorData->getPolarShift());
		$generatorData->setPolarTemperature($this->polarTemperature);
		$generatorData->setEquatorTemperature($this->equatorTemperature);
		$generatorData->setPolarShift($this->polarShift);
		if (isset($values["seasonalHumiditySettings"]) && is_array($values["seasonalHumiditySettings"])){
			$generatorData->setSeasonalHumiditySettings($values["seasonalHumiditySettings"]);
		}
		if(isset($values["dayNightSettings"]) && is_array($values["dayNightSettings"])){
			$generatorData->setDayNightSettings($values["dayNightSettings"]);
		}
		if(isset($values["phenomenonSettings"]) && is_array($values["phenomenonSettings"])){
			$generatorData->setPhenomenonSettings($values["phenomenonSettings"]);
		}
		$annualSettings = $generatorData->getAnnualClimateSettings();
		foreach(\WorldGenerator\Generator\Climate\ClimateBaseMapSettings::definitions() as $key => $definition){
			if(isset($values[$key]) && is_numeric($values[$key])){
				$annualSettings[$key] = (float) $values[$key];
			}
		}
		$generatorData->setAnnualClimateSettings($annualSettings);
		$climateSettings = $this->simulationState->getClimateSettings();
		$climateSettings->setPolarTemperature($this->polarTemperature);
		$climateSettings->setEquatorTemperature($this->equatorTemperature);
		$climateSettings->setPolarShift($this->polarShift);
		$baseMaps = [];
		if($stage === SimulationState::STAGE_YEARS){
			$landscapeMap = (new ClimateLandscapeAnalyzer())->analyze(
				$this->simulationState->getMap(),
				$this->waterLevel
			);
			$baseMaps = (new ClimateBaseMapGenerator())->generate(
				$landscapeMap,
				$this->polarTemperature,
				$this->equatorTemperature,
				$this->polarShift,
				$this->waterLevel,
				$generatorData->getAnnualClimateSettings()
			);
			$this->climateLandscapeMap = $landscapeMap;
		}else{
			// На последующих этапах используем уже рассчитанный ландшафт.
			$landscapeMap = $this->climateLandscapeMap;
			if($landscapeMap === null){
				return false;
			}
		}
		if(!$this->simulationState->rebuildClimateMaps(
			$baseMaps,
			$landscapeMap,
			$generatorData->getSeasonalHumiditySettings(),
			$this->waterLevel,
			$generatorData->getDayNightSettings(),
			$generatorData->getPhenomenonSettings()
		)){
			return false;
		}
		if($this->climateDebugExporter !== null){
			if($stage === SimulationState::STAGE_YEARS){
				$this->climateDebugExporter->exportLandscape($landscapeMap);
				$this->climateDebugExporter->exportBaseMaps($baseMaps);
			}

			if($stage >= SimulationState::STAGE_SEASONS){
				$this->climateDebugExporter->exportSeasonalMaps($this->simulationState->getClimateSeasonalMaps());
				$this->climateDebugExporter->exportHumidityMaps($this->simulationState->getClimateHumidityMaps());
			}

			if($stage >= SimulationState::STAGE_DAY_NIGHT){
				$this->climateDebugExporter->exportDayNightMaps($this->simulationState->getClimateDayNightMaps());
			}

			if($stage >= SimulationState::STAGE_PHENOMENA){
				$this->climateDebugExporter->exportPhenomenonRegions($this->simulationState->getClimatePhenomenonRegions());
			}
		}
		if($this->simulationExporter !== null){
			$this->simulationExporter->exportSimulation($this->simulationState,$landscapeMap);
		}
		$this->saveGeneration();
		return true;
	}
}