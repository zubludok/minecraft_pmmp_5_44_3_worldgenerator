<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

use RuntimeException;
use WorldGenerator\Generator\Climate\ClimateBaseCell;
use WorldGenerator\Generator\Climate\ClimateBaseMap;
use WorldGenerator\Generator\Climate\ClimateDayNightCell;
use WorldGenerator\Generator\Climate\ClimateDayNightMap;
use WorldGenerator\Generator\Climate\ClimateHumidityCell;
use WorldGenerator\Generator\Climate\ClimateHumidityMap;
use WorldGenerator\Generator\Climate\ClimateLandscapeCell;
use WorldGenerator\Generator\Climate\ClimateLandscapeMap;
use WorldGenerator\Generator\Climate\ClimatePhenomenonRegion;
use WorldGenerator\Generator\Climate\ClimateSeasonalCell;
use WorldGenerator\Generator\Climate\ClimateSeasonalMap;
use WorldGenerator\Generator\Climate\ClimateSettings;
use WorldGenerator\Generator\Climate\ClimateTemperatureCell;
use WorldGenerator\Generator\Climate\ClimateTemperatureMap;
use WorldGenerator\Generator\Formation\FormationEvent;
use WorldGenerator\Generator\Formation\FormationSnapshot;
use WorldGenerator\Generator\Formation\Force;
use WorldGenerator\Generator\Formation\ForceShape;
use WorldGenerator\Generator\Geology\FormationState;
use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Geology\RockRegistry;
use WorldGenerator\Generator\Mass\Fragment;
use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Movement\MovementVector;
use WorldGenerator\Generator\Objects\ObjectLayout;
use WorldGenerator\Generator\Objects\ObjectPlacement;
use WorldGenerator\Generator\Structure\Structure;
use WorldGenerator\Generator\Geology\RockType;

final class SimulationSerializer{

	private const FORMAT_VERSION = 1;

	public function __construct(private RockRegistry $rockRegistry){}

	// Экспорт
	// @return array<string,mixed>
	public function stateToArray(SimulationState $sim): array{	
		return [
			"version" => self::FORMAT_VERSION,
			"stage" => $sim->getStage(),
			"cycle" => $sim->getCycle(),
			"step" => $sim->getStep(),
			"stepsInCycle" => $sim->getStepsInCycle(),
			"totalSteps" => $sim->getTotalSteps(),
			"cycleAxisMovement" => $sim->getCycleAxisMovement(),
			"cycleAxisSize" => $sim->getCycleAxisSize(),
			"seed" => $sim->getSeed(),
			"landPercent" => $sim->getLandPercent(),
			"targetAdditionalCycles" => $sim->getTargetAdditionalCycles(),
			"running" => $sim->isRunning(),
			"finished" => $sim->isFinished(),
			"climateSettings" => [
				"polar" => $sim->getClimateSettings()->getPolarTemperature(),
				"equator" => $sim->getClimateSettings()->getEquatorTemperature(),
				"shift" => $sim->getClimateSettings()->getPolarShift(),
			],
			"map" => $this->massMapToArray($sim->getMap()),
			"formation" => $this->formationToArray($sim),
			"islands" => $this->islandsToArray($sim),
			"objectLayout" => $this->objectLayoutToArray($sim->getObjectLayout()),
			"climate" => [
				"baseMaps" => $this->climateBaseMapsToArray($sim->getClimateBaseMaps()),
				"seasonalMaps" => $this->climateSeasonalMapsToArray($sim->getClimateSeasonalMaps()),
				"temperatureMap" => $this->climateTemperatureToArray($sim->getClimateTemperatureMap()),
				"landscapeMap" => $this->climateLandscapeToArray($sim->getClimateLandscapeMap()),
				"dayNightMaps" => $this->climateDayNightMapsToArray($sim->getClimateDayNightMaps()),
				"humidityMaps" => $this->climateHumidityMapsToArray($sim->getClimateHumidityMaps()),
				"phenomenonRegions" => $this->phenomenonRegionsToArray($sim->getClimatePhenomenonRegions()),
			],
		];
	}

	// Импорт
	// @param array<string,mixed> $data
	public function stateFromArray(array $data): ?SimulationState{
		if(($data["version"] ?? 0) !== self::FORMAT_VERSION){
			return null;
		}
		$map = $this->massMapFromArray($data["map"]);
		$objectLayout = $this->objectLayoutFromArray($data["objectLayout"]);
		$climate = new ClimateSettings(
			(float)$data["climateSettings"]["polar"],
			(float)$data["climateSettings"]["equator"],
			(int)$data["climateSettings"]["shift"]
		);
		$sim = SimulationState::fromArray($data, $map, $objectLayout, $climate);
		if($sim === null){
			return null;
		}
		$this->restoreFormation($sim, $data["formation"]);
		$this->restoreIslands($sim, $data["islands"], $map);
		$this->restoreClimate($sim, $data["climate"] ?? []);
		return $sim;
	}

	// MassMap
	// @return array<string,mixed>
	private function massMapToArray(MassMap $map): array{
		$cells = [];
		for($z = 0; $z < $map->getSizeZ(); $z++){
			for($x = 0; $x < $map->getSizeX(); $x++){
				$cell = $map->get($x, $z);
				if($cell === null){
					continue;
				}
				$cells[] = $this->cellToArray($cell);
			}
		}
		return ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
	}

	// @param array<string,mixed> $data
	private function massMapFromArray(array $data): MassMap{
		$map = new MassMap((int)$data["sizeX"], (int)$data["sizeZ"], $this->rockRegistry);
		foreach($data["cells"] as $cellData){
			$cell = $this->cellFromArray($cellData);
			$map->set($cell->getX(), $cell->getZ(), $cell);
		}
		return $map;
	}

	// @return array<string,mixed>
	private function cellToArray(MassCell $cell): array{
		$layers = [];
		foreach($cell->getLayers() as $layer){
			$layers[] = $this->layerToArray($layer);
		}
		$structures = [];
		foreach($cell->getStructures() as $structure){
			$structures[] = [
				"id" => $structure->getId(),
				"type" => $structure->getType(),
				"neighbours" => $cell->getStructureNeighbours($structure->getId()),
			];
		}
		$events = [];
		foreach($cell->getFormationHistory() as $event){
			$events[] = $this->eventToArray($event);
		}
		return [
			"x" => $cell->getX(),
			"z" => $cell->getZ(),
			"density" => $cell->getDensity(),
			"fragmentId" => $cell->getFragmentId(),
			"originObjectId" => $cell->getOriginObjectId(),
			"land" => $cell->isLand(),
			"layers" => $layers,
			"structures" => $structures,
			"formationState" => $this->formationStateToArray($cell->getFormationState()),
			"previousMovement" => $cell->getPreviousMovement() === null ? null : [$cell->getPreviousMovement()->getX(), $cell->getPreviousMovement()->getZ()],
			"currentMovement" => $cell->getCurrentMovement() === null ? null : [$cell->getCurrentMovement()->getX(), $cell->getCurrentMovement()->getZ()],
			"formationHistory" => $events,
		];
	}

	// @param array<string,mixed> $data
	private function cellFromArray(array $data): MassCell{
		$cell = new MassCell((int)$data["x"], (int)$data["z"], $this->rockRegistry);
		$cell->setDensity((int)$data["density"]);
		$cell->setFragmentId((int)$data["fragmentId"]);
		$cell->setOriginObjectId((int)($data["originObjectId"] ?? -1));
		$cell->setLand((bool)($data["land"] ?? false));
		$layers = [];
		foreach($data["layers"] as $layerData){
			$layers[] = $this->layerFromArray($layerData);
		}
		$cell->setLayers($layers);
		foreach($data["structures"] as $structureData){
			$structure = new Structure((int)$structureData["id"], (string)$structureData["type"]);
			$cell->addStructure($structure);
			foreach($structureData["neighbours"] as $offset){
				$cell->addStructureNeighbour($structure->getId(), [(int)$offset[0], (int)$offset[1]]);
			}
		}
		$this->applyFormationState($cell->getFormationState(), $data["formationState"]);
		// Восстановление истории перемещений. recordMovement() хранит только предыдущее/текущее,
		// поэтому сначала пишем previous, затем current.
		if($data["previousMovement"] !== null){
			$cell->recordMovement(new MovementVector((int)$data["previousMovement"][0],(int)$data["previousMovement"][1]));
		}
		if($data["currentMovement"] !== null){
			$cell->recordMovement(new MovementVector((int)$data["currentMovement"][0],(int)$data["currentMovement"][1]));
		}
		$events = [];
		foreach($data["formationHistory"] ?? [] as $eventData){
			$events[] = $this->eventFromArray($eventData);
		}
		$cell->setFormationHistory($events);
		return $cell;
	}

	// @return array<string,mixed>
	private function layerToArray(RockLayer $layer): array{
		return [
			"rock" => $layer->getRock()->getId(),
			"amount" => $layer->getAmount(),
			"compression" => $layer->getCompression(),
			"stress" => $layer->getStress(),
			"fraction" => $layer->getFraction(),
			"waterImpact" => $layer->getWaterImpact(),
			"waterContent" => $layer->getWaterContent(),
		];
	}

	// @param array<string,mixed> $data
	private function layerFromArray(array $data): RockLayer{
		$layer = new RockLayer($this->rockRegistry->get((string)$data["rock"]), (float)$data["amount"]);
		$layer->setCompression((float)$data["compression"]);
		$layer->setStress((float)$data["stress"]);
		$layer->setFraction((float)$data["fraction"]);
		$layer->setWaterImpact((float)$data["waterImpact"]);
		$layer->setWaterContent((float)$data["waterContent"]);
		return $layer;
	}

	// @return array<string,mixed>
	private function formationStateToArray(FormationState $state): array{
		return [
			"stress" => $state->getStress(),
			"deformation" => $state->getDeformation(),
			"collisionCount" => $state->getCollisionCount(),
			"directionChanges" => $state->getDirectionChanges(),
			"redistributionCount" => $state->getRedistributionCount(),
			"origin" => $state->getOrigin(),
		];
	}

	// @param array<string,mixed> $data
	private function applyFormationState(FormationState $state, array $data): void{
		$state->setStress((float)$data["stress"]);
		$state->setDeformation((float)$data["deformation"]);
		$state->setCollisionCount((int)$data["collisionCount"]);
		$state->setDirectionChanges((int)$data["directionChanges"]);
		$state->setRedistributionCount((int)$data["redistributionCount"]);
		$state->setOrigin((string)$data["origin"]);
	}

	// @return array<string,mixed>
	private function eventToArray(FormationEvent $event): array{
		return [
			"step" => $event->getStep(),
			"type" => $event->getType(),
			"participants" => $event->getParticipants(),
			"x" => $event->getX(),
			"z" => $event->getZ(),
			"pressure" => $event->getPressure(),
			"directionX" => $event->getDirectionX(),
			"directionZ" => $event->getDirectionZ(),
			"intensity" => $event->getIntensity(),
			"deformation" => $event->getDeformation(),
			"before" => $this->snapshotToArray($event->getBefore()),
			"after" => $this->snapshotToArray($event->getAfter()),
		];
	}

	// @param array<string,mixed> $data
	private function eventFromArray(array $data): FormationEvent{
		return new FormationEvent(
			(int)$data["step"],
			(string)$data["type"],
			array_map("intval", $data["participants"]),
			(int)$data["x"],
			(int)$data["z"],
			(int)$data["pressure"],
			(int)$data["directionX"],
			(int)$data["directionZ"],
			(int)$data["intensity"],
			$this->snapshotFromArray($data["before"]),
			$this->snapshotFromArray($data["after"]),
			(int)$data["deformation"]
		);
	}

	private function snapshotToArray(FormationSnapshot $snapshot): array{return ["density" => $snapshot->getDensity(),"layers" => $snapshot->getLayers(),];}	// @return array<string,mixed>
	private function snapshotFromArray(array $data): FormationSnapshot{return new FormationSnapshot((int)$data["density"], $data["layers"]);}	// @param array<string,mixed> $data

	// FormationSimulation

	// @return array<string,mixed>
	private function formationToArray(SimulationState $sim): array{
		$formation = $sim->getFormationSimulation();
		$force = $formation->getForce();
		$objects = [];
		foreach($formation->getObjects() as $object){
			$objects[] = [
				"id" => $object->getObjectId(),
				"x" => $object->getX(),
				"z" => $object->getZ(),
				"dirX" => $object->getDirectionX(),
				"dirZ" => $object->getDirectionZ(),
				"delay" => $object->getMovementDelay(),
			];
		}
		return [
			"force" => [
				"x" => $force->getX(),
				"z" => $force->getZ(),
				"shape" => [
					"centerX" => $force->getShape()->getCenterX(),
					"centerZ" => $force->getShape()->getCenterZ(),
					"cells" => $force->getShape()->getCells(),
				],
				"movement" => [$force->getMovement()->getX(), $force->getMovement()->getZ()],
				"lastMovement" => [$force->getLastMovement()->getX(), $force->getLastMovement()->getZ()],
				"minAmount" => $force->getMinAmount(),
				"maxAmount" => $force->getMaxAmount(),
				"randomizer" => base64_encode(serialize($force->getRandomizer())),
			],
			"objects" => $objects,
			"collisions" => $formation->getCollisions(),
		];
	}

	// @param array<string,mixed> $data
	private function restoreFormation(SimulationState $sim, array $data): void{
		$formation = $sim->getFormationSimulation();
		$forceData = $data["force"];
		$shape = new ForceShape(
			(int)$forceData["shape"]["centerX"],
			(int)$forceData["shape"]["centerZ"],
			array_map(static fn(array $c): array => [(int)$c[0], (int)$c[1]], $forceData["shape"]["cells"])
		);
		$randomizer = unserialize(base64_decode($forceData["randomizer"]));
		if(!$randomizer instanceof \Random\Randomizer){
			throw new RuntimeException("Не удалось восстановить Randomizer Force.");
		}
		$force = new Force(
			(int)$forceData["x"],
			(int)$forceData["z"],
			$shape,
			new MovementVector((int)$forceData["movement"][0], (int)$forceData["movement"][1]),
			(float)$forceData["minAmount"],
			(float)$forceData["maxAmount"],
			$randomizer
		);
		$formation->setForce($force);
		$objects = [];
		foreach($data["objects"] as $objectData){
			$placement = new ObjectPlacement((int)$objectData["id"],0, 0,(int)$objectData["x"],(int)$objectData["z"]);
			$placement->setDirection((int)$objectData["dirX"], (int)$objectData["dirZ"]);
			$placement->setMovementDelay((int)$objectData["delay"]);
			$state = new SimulationObjectState($placement);
			$objects[$state->getObjectId()] = $state;
		}
		$formation->setObjects($objects);
		$formation->setCollisions($data["collisions"] ?? []);
	}

	// IslandSimulation 
	// @return array<string,mixed>
	private function islandsToArray(SimulationState $sim): array{
		$islands = $sim->getIslandSimulation();
		$fragments = [];
		foreach($islands->getFragments() as $fragment){
			$cells = [];
			foreach($fragment->getCells() as $cell){
				$cells[] = [$cell->getX(), $cell->getZ()];
			}
			$fragments[] = [
				"id" => $fragment->getId(),
				"dirX" => $fragment->getDirectionX(),
				"dirZ" => $fragment->getDirectionZ(),
				"cells" => $cells,
			];
		}
		return [
			"fragmentIndex" => $islands->getFragmentIndex(),
			"cycleCompleted" => $islands->isCycleCompleted(),
			"fragments" => $fragments,
		];
	}

	// @param array<string,mixed> $data
	private function restoreIslands(SimulationState $sim, array $data, MassMap $map): void{
		$islands = $sim->getIslandSimulation();
		$fragments = [];
		foreach($data["fragments"] as $fragmentData){
			$fragment = new Fragment((int)$fragmentData["id"]);
			$cells = [];
			foreach($fragmentData["cells"] as $pos){
				$cell = $map->get((int)$pos[0], (int)$pos[1]);
				if($cell !== null){
					$cells[] = $cell;
				}
			}
			$fragment->setCells($cells);
			$fragment->setDirection((int)$fragmentData["dirX"], (int)$fragmentData["dirZ"]);
			$fragments[] = $fragment;
		}
		$islands->setFragments($fragments);
		$islands->setFragmentIndex((int)$data["fragmentIndex"]);
		$islands->setCycleCompleted((bool)$data["cycleCompleted"]);
	}

	// ObjectLayout
	// @return array<string,mixed>
	private function objectLayoutToArray(ObjectLayout $layout): array{
		$objects = [];
		foreach($layout->getObjects() as $object){
			$objects[] = [
				"id" => $object->getId(),
				"gridX" => $object->getGridX(),
				"gridZ" => $object->getGridZ(),
				"x" => $object->getPositionX(),
				"z" => $object->getPositionZ(),
				"dirX" => $object->getDirectionX(),
				"dirZ" => $object->getDirectionZ(),
				"delay" => $object->getMovementDelay(),
			];
		}
		return [
			"columns" => $layout->getColumns(),
			"rows" => $layout->getRows(),
			"objects" => $objects,
		];
	}

	// @param array<string,mixed> $data
	private function objectLayoutFromArray(array $data): ObjectLayout{
		$layout = new ObjectLayout((int)$data["columns"], (int)$data["rows"]);
		foreach($data["objects"] as $objectData){
			$placement = new ObjectPlacement(
				(int)$objectData["id"],
				(int)$objectData["gridX"],
				(int)$objectData["gridZ"],
				(int)$objectData["x"],
				(int)$objectData["z"]
			);
			$placement->setDirection((int)$objectData["dirX"], (int)$objectData["dirZ"]);
			$placement->setMovementDelay((int)$objectData["delay"]);
			$layout->addObject($placement);
		}
		return $layout;
	}

	// Климат
	// @param array<string,ClimateBaseMap> $maps
	private function climateBaseMapsToArray(array $maps): array{
		$result = [];
		foreach($maps as $name => $map){
			$cells = [];
			for($z = 0; $z < $map->getSizeZ(); $z++){
				for($x = 0; $x < $map->getSizeX(); $x++){
					$cell = $map->get($x, $z);
					if($cell === null){
						continue;
					}
					$cells[] = [
						"x" => $cell->getX(),
						"z" => $cell->getZ(),
						"temperature" => $cell->getTemperature(),
						"windCoefficient" => $cell->getWindCoefficient(),
					];
				}
			}
			$result[$name] = ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
		}
		return $result;
	}

	// @param array<string,mixed> $data @return array<string,ClimateBaseMap>
	private function climateBaseMapsFromArray(array $data): array{
		$maps = [];
		foreach($data as $name => $mapData){
			$map = new ClimateBaseMap((int)$mapData["sizeX"], (int)$mapData["sizeZ"]);
			foreach($mapData["cells"] as $cellData){
				$map->set(new ClimateBaseCell(
					(int)$cellData["x"],
					(int)$cellData["z"],
					(float)$cellData["temperature"],
					(float)$cellData["windCoefficient"]
				));
			}
			$maps[$name] = $map;
		}
		return $maps;
	}

	// @param array<string,array<string,ClimateSeasonalMap>> $maps
	private function climateSeasonalMapsToArray(array $maps): array{
		$result = [];
		foreach($maps as $year => $seasons){
			foreach($seasons as $season => $map){
				$cells = [];
				for($z = 0; $z < $map->getSizeZ(); $z++){
					for($x = 0; $x < $map->getSizeX(); $x++){
						$cell = $map->get($x, $z);
						if($cell === null){
							continue;
						}
						$cells[] = [
							"x" => $cell->getX(),
							"z" => $cell->getZ(),
							"temperature" => $cell->getTemperature(),
							"windCoefficient" => $cell->getWindCoefficient(),
							"windDirection" => $cell->getWindDirection(),
						];
					}
				}
				$result[$year][$season] = ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
			}
		}
		return $result;
	}

	// @param array<string,mixed> $data @return array<string,array<string,ClimateSeasonalMap>>
	private function climateSeasonalMapsFromArray(array $data): array{
		$maps = [];
		foreach($data as $year => $seasons){
			foreach($seasons as $season => $mapData){
				$map = new ClimateSeasonalMap((int)$mapData["sizeX"], (int)$mapData["sizeZ"]);
				foreach($mapData["cells"] as $cellData){
					$map->set(new ClimateSeasonalCell(
						(int)$cellData["x"],
						(int)$cellData["z"],
						(float)$cellData["temperature"],
						(float)$cellData["windCoefficient"],
						(float)$cellData["windDirection"]
					));
				}
				$maps[$year][$season] = $map;
			}
		}
		return $maps;
	}

	// @return array<string,mixed>|null
	private function climateTemperatureToArray(?ClimateTemperatureMap $map): ?array{
		if($map === null){
			return null;
		}
		$cells = [];
		for($z = 0; $z < $map->getSizeZ(); $z++){
			for($x = 0; $x < $map->getSizeX(); $x++){
				$cell = $map->get($x, $z);
				if($cell === null){
					continue;
				}
				$cells[] = ["x" => $cell->getX(), "z" => $cell->getZ(), "temperature" => $cell->getTemperature()];
			}
		}
		return ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
	}

	// @param array<string,mixed>|null $data
	private function climateTemperatureFromArray(?array $data): ?ClimateTemperatureMap{
		if($data === null){
			return null;
		}
		$map = new ClimateTemperatureMap((int)$data["sizeX"], (int)$data["sizeZ"]);
		foreach($data["cells"] as $cellData){
			$map->set(new ClimateTemperatureCell((int)$cellData["x"], (int)$cellData["z"], (float)$cellData["temperature"]));
		}
		return $map;
	}

	// @return array<string,mixed>|null
	private function climateLandscapeToArray(?ClimateLandscapeMap $map): ?array{
		if($map === null){
			return null;
		}
		$cells = [];
		for($z = 0; $z < $map->getSizeZ(); $z++){
			for($x = 0; $x < $map->getSizeX(); $x++){
				$cell = $map->get($x, $z);
				if($cell === null){
					continue;
				}
				$cells[] = [
					"x" => $cell->getX(),
					"z" => $cell->getZ(),
					"surfaceHeight" => $cell->getSurfaceHeight(),
					"water" => $cell->isWater(),
					"mountain" => $cell->isMountain(),
					"relativeHeight" => $cell->getRelativeHeight(),
					"heightDifference" => $cell->getHeightDifference(),
					"surfaceRock" => $cell->getSurfaceRockId(),
					"climateType" => $cell->getClimateType(),
				];
			}
		}
		return ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
	}

	// @param array<string,mixed>|null $data
	private function climateLandscapeFromArray(?array $data): ?ClimateLandscapeMap{
		if($data === null){
			return null;
		}
		$map = new ClimateLandscapeMap((int)$data["sizeX"],(int)$data["sizeZ"]);
		foreach($data["cells"] as $cellData){
			$rockId = $cellData["surfaceRock"] ?? null;
			$rock = null;
			if(is_string($rockId) && $rockId !== "" && $this->rockRegistry->has($rockId)){
				$rock = $this->rockRegistry->get($rockId);
			}
			$cell = new ClimateLandscapeCell(
				(int)$cellData["x"],
				(int)$cellData["z"],
				(float)$cellData["surfaceHeight"],
				(bool)$cellData["water"],
				$rock
			);
			$cell->setMountain((bool)($cellData["mountain"] ?? false));
			$cell->setRelativeHeight((float)($cellData["relativeHeight"] ?? 0.0));
			$cell->setHeightDifference((float)($cellData["heightDifference"] ?? 0.0));
			$map->set($cell);
		}
		return $map;
	}

	// @param array<string,array<string,array<string,ClimateDayNightMap>>> $maps
	private function climateDayNightMapsToArray(array $maps): array{
		$result = [];
		foreach($maps as $year => $seasons){
			foreach($seasons as $season => $times){
				foreach($times as $time => $map){
					$cells = [];
					for($z = 0; $z < $map->getSizeZ(); $z++){
						for($x = 0; $x < $map->getSizeX(); $x++){
							$cell = $map->get($x, $z);
							if($cell === null){
								continue;
							}
							$cells[] = [
								"x" => $cell->getX(),
								"z" => $cell->getZ(),
								"temperature" => $cell->getTemperature(),
								"windCoefficient" => $cell->getWindCoefficient(),
								"windDirection" => $cell->getWindDirection(),
								"windSpeed" => $cell->getWindSpeed(),
							];
						}
					}
					$result[$year][$season][$time] = ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
				}
			}
		}
		return $result;
	}

	// @param array<string,mixed> $data @return array<string,array<string,array<string,ClimateDayNightMap>>>
	private function climateDayNightMapsFromArray(array $data): array{
		$maps = [];
		foreach($data as $year => $seasons){
			foreach($seasons as $season => $times){
				foreach($times as $time => $mapData){
					$map = new ClimateDayNightMap((int)$mapData["sizeX"], (int)$mapData["sizeZ"]);
					foreach($mapData["cells"] as $cellData){
						$map->set(new ClimateDayNightCell(
							(int)$cellData["x"],
							(int)$cellData["z"],
							(float)$cellData["temperature"],
							(float)$cellData["windCoefficient"],
							(float)$cellData["windDirection"],
							(float)$cellData["windSpeed"]
						));
					}
					$maps[$year][$season][$time] = $map;
				}
			}
		}
		return $maps;
	}

	// @param array<string,array<string,ClimateHumidityMap>> $maps
	private function climateHumidityMapsToArray(array $maps): array{
		$result = [];
		foreach($maps as $year => $seasons){
			foreach($seasons as $season => $map){
				$cells = [];
				for($z = 0; $z < $map->getSizeZ(); $z++){
					for($x = 0; $x < $map->getSizeX(); $x++){
						$cell = $map->get($x, $z);
						if($cell === null){
							continue;
						}
						$cells[] = ["x" => $cell->getX(), "z" => $cell->getZ(), "humidity" => $cell->getHumidity()];
					}
				}
				$result[$year][$season] = ["sizeX" => $map->getSizeX(), "sizeZ" => $map->getSizeZ(), "cells" => $cells];
			}
		}
		return $result;
	}

	// @param array<string, mixed> $data
	// @return array<string, array<string, ClimateHumidityMap>>
	private function climateHumidityMapsFromArray(array $data): array{
		$maps = [];
		foreach($data as $year => $seasons){
			if(!is_array($seasons)){
				continue;
			}
			foreach($seasons as $season => $mapData){
				if(!is_array($mapData)){
					continue;
				}
				$map = new ClimateHumidityMap(
					(int)($mapData["sizeX"] ?? 0),
					(int)($mapData["sizeZ"] ?? 0),
					(string)$year,
					(string)$season
				);
				foreach($mapData["cells"] ?? [] as $cellData){
					$map->set(new ClimateHumidityCell(
						(int)$cellData["x"],
						(int)$cellData["z"],
						(float)$cellData["humidity"]
					));
				}
				$maps[(string)$year][(string)$season] = $map;
			}
		}
		return $maps;
	}

	// @param ClimatePhenomenonRegion[] $regions
	private function phenomenonRegionsToArray(array $regions): array{
		$result = [];
		foreach($regions as $region){
			$result[] = $region->toArray();
		}
		return $result;
	}

	// @param array<int,array<string,mixed>> $data @return ClimatePhenomenonRegion[]
	private function phenomenonRegionsFromArray(array $data): array{
		$regions = [];
		foreach($data as $regionData){
			$cells = [];
			foreach($regionData["cells"] as $cellData){
				$cells[] = ["x" => (int)$cellData["x"], "z" => (int)$cellData["z"]];
			}
			$regions[] = new ClimatePhenomenonRegion(
				(int)$regionData["id"],
				(string)$regionData["phenomenon"],
				(string)$regionData["season"],
				(string)$regionData["yearType"],
				(string)$regionData["hemisphere"],
				$cells
			);
		}
		return $regions;
	}

	// @param array<string,mixed> $climate
	private function restoreClimate(SimulationState $sim, array $climate): void{
		$sim->setClimateBaseMaps($this->climateBaseMapsFromArray($climate["baseMaps"] ?? []));
		$sim->setClimateSeasonalMaps($this->climateSeasonalMapsFromArray($climate["seasonalMaps"] ?? []));
		$sim->setClimateTemperatureMap($this->climateTemperatureFromArray($climate["temperatureMap"] ?? null));
		$sim->setClimateLandscapeMap($this->climateLandscapeFromArray($climate["landscapeMap"] ?? null));
		$sim->setClimateDayNightMaps($this->climateDayNightMapsFromArray($climate["dayNightMaps"] ?? []));
		$sim->setClimateHumidityMaps($this->climateHumidityMapsFromArray($climate["humidityMaps"] ?? []));
		$sim->setClimatePhenomenonRegions($this->phenomenonRegionsFromArray($climate["phenomenonRegions"] ?? []));
	}
}