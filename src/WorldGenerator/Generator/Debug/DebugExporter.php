<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Debug;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Simulation\SimulationState;
use WorldGenerator\Generator\Formation\FormationSnapshot;
use WorldGenerator\Generator\Formation\FormationEvent;

use WorldGenerator\Generator\Climate\ClimateLandscapeMap;
use WorldGenerator\Generator\Climate\ClimateLandscapeAnalyzer;
use WorldGenerator\Generator\Climate\ClimateSeasonalMap;

final class DebugExporter{

    private string $directory;
    private int $waterLevel;
	private int $worldHeight;
	private int $seed;
	
    public function __construct(
		string $worldPath,
		int $waterLevel,
		int $worldHeight = 320,
		int $seed = 0
	){
		$this->waterLevel = $waterLevel;
		$this->worldHeight = max(1, $worldHeight);
		$this->seed = $seed;
		$this->directory = $worldPath . DIRECTORY_SEPARATOR . "debug";
		if(!is_dir($this->directory)){
			mkdir($this->directory, 0777, true);
		}
    }

    // Экспорт карты массы в TXT и JSON
    public function exportMass(MassMap $map): void{
        $this->exportMassText($map);
        $this->exportMassJson($map);
    }

    // Экспорт человекочитаемой карты
    private function exportMassText(MassMap $map): void{
        $lines=[
            "WORLD GENERATOR DEBUG",
            "TYPE: MASS MAP",
            "SIZE: ".$map->getSizeX()." x ".$map->getSizeZ(),
            "MASS: ".$map->countMass(),
            "",
            "LEGEND:",
            "█ DENSE",
            "▓ MEDIUM",
            "▒ WEAK",
            "░ LOOSE",
            "  EMPTY",
            "",
            "MAP:"
        ];

        for($z=0;$z<$map->getSizeZ();$z++){
            $line="";
            for($x=0;$x<$map->getSizeX();$x++){
				$cell=$map->get($x,$z);
                $line.=$cell===null ? "?" : $this->getSymbol($cell->getDensity());
            }
            $lines[]=$line;
        }
        file_put_contents($this->directory.DIRECTORY_SEPARATOR."mass.txt",implode(PHP_EOL,$lines));
    }

    // Экспорт структурированных данных карты
    private function exportMassJson(MassMap $map): void{
        $density=[];
        for($z=0;$z<$map->getSizeZ();$z++){
            $row=[];
            for($x=0;$x<$map->getSizeX();$x++){
                $cell=$map->get($x,$z);
                $row[]=$cell===null ? 0 : $cell->getDensity();
            }
            $density[]=$row;
        }
        $data=[
            "type"=>"mass",
            "sizeX"=>$map->getSizeX(),
            "sizeZ"=>$map->getSizeZ(),
            "mass"=>$map->countMass(),
            "density"=>$density
        ];
        file_put_contents($this->directory.DIRECTORY_SEPARATOR."mass.json",json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    }

	// Экспорт текущего состояния симуляции и столкновений
	public function exportSimulation(SimulationState $simulation, ?ClimateLandscapeMap $climateMap = null): void {
		$force = $simulation->getForce();
		$data = [
			"type" => "simulation",
			"step" => $simulation->getStep(),
			"cycle" => $simulation->getCycle(),
			"totalSteps" => $simulation->getTotalSteps(),
			"force" => [
				"x" => $force->getX(),
				"z" => $force->getZ(),
				"width" => $force->getWidth(),
				"height" => $force->getHeight(),
				"directionX" => $force->getDirectionX(),
				"directionZ" => $force->getDirectionZ(),
				"minAmount" => $force->getMinAmount(),
				"maxAmount" => $force->getMaxAmount()
			],
			"mass" => $this->exportMassAmount($simulation->getMap()),
			"collisions" => $simulation->getCollisions()
		];
		file_put_contents(
			$this->directory . DIRECTORY_SEPARATOR . "simulation.json",
			json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)
		);
		$this->exportFormation($simulation->getMap(),$climateMap);
	}

    // Получить символ плотности
    private function getSymbol(int $density): string{
        return match($density){
            MassCell::DENSE=>"█",
            MassCell::MEDIUM=>"▓",
            MassCell::WEAK=>"▒",
            MassCell::LOOSE=>"░",
            default=>" "
        };
    }

	// Экспорт истории формирования клеток
	public function exportFormation(MassMap $map, ?ClimateLandscapeMap $climateMap = null): void {
		$cells = [];
		$fragmentRows = [];
		$climateRows = [];
		for($z = 0; $z < $map->getSizeZ(); $z++){
			$fragmentRow = [];
			$climateRow = [];
			for($x = 0; $x < $map->getSizeX(); $x++){
				$cell = $map->get($x, $z);
				$fragmentRow[] = $cell === null ? -1 : $cell->getFragmentId();
				if($climateMap !== null){
					$climateCell = $climateMap->get($x, $z);
					$climateRow[] = $climateCell === null ? "?" : $climateCell->getClimateType();
				}
				if($cell !== null){
					$cells[] = $this->exportCell($cell);
				}
			}
			$fragmentRows[] = "        " . json_encode(
				$fragmentRow,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
			if($climateMap !== null){
				$climateRows[] = "        " . json_encode(
					$climateRow,
					JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
				);
			}
		}
		$cellsJson = [];
		foreach($cells as $cell){
			$cellsJson[] = "        " . json_encode(
				$cell,
				JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
			);
		}
		$json =
			"{" . PHP_EOL .
			"    \"type\": \"formation\"," . PHP_EOL .
			"    \"sizeX\": " . $map->getSizeX() . "," . PHP_EOL .
			"    \"sizeZ\": " . $map->getSizeZ() . "," . PHP_EOL .
			"    \"worldHeight\": " . $this->worldHeight . "," . PHP_EOL .
			"    \"waterLevel\": " . $this->waterLevel . "," . PHP_EOL .
			"    \"seed\": " . $this->seed . "," . PHP_EOL .
			"    \"fragmentIds\": [" . PHP_EOL .
			implode("," . PHP_EOL, $fragmentRows) . PHP_EOL .
			"    ]," . PHP_EOL;
		if($climateMap !== null){
			$json .=
				"    \"climateLandscape\": [" . PHP_EOL .
				implode("," . PHP_EOL, $climateRows) . PHP_EOL .
				"    ]," . PHP_EOL;
		}
		$json .=
			"    \"cells\": [" . PHP_EOL .
			implode("," . PHP_EOL, $cellsJson) . PHP_EOL .
			"    ]" . PHP_EOL .
			"}";
		$result = file_put_contents(
			$this->directory . DIRECTORY_SEPARATOR . "formation.json",
			$json
		);
		if($result === false){
			throw new \RuntimeException("Не удалось записать formation.json в " . $this->directory);
		}
	}

	// Вынесеный экспорт клекти
	private function exportCell(MassCell $cell): array{
		$events=[];
		foreach($cell->getFormationHistory() as $event){
			if($event===null){
				throw new \RuntimeException(
					"NULL FormationEvent at cell X=".$cell->getX().
					" Z=".$cell->getZ().
					" Fragment=".$cell->getFragmentId()
				);
			}
			$events[]=[
				"step"=>$event->getStep(),
				"type"=>$event->getType(),
				"participants"=>$event->getParticipants(),
				"x"=>$event->getX(),
				"z"=>$event->getZ(),
				"pressure"=>$event->getPressure(),
				"directionX"=>$event->getDirectionX(),
				"directionZ"=>$event->getDirectionZ(),
				"intensity"=>$event->getIntensity(),
				"deformation"=>$event->getDeformation(),
				"before"=>$this->exportSnapshot($event->getBefore()),
				"after"=>$this->exportSnapshot($event->getAfter())
			];
		}
		return[
			"x"=>$cell->getX(),
			"z"=>$cell->getZ(),
			"state"=>[
				"density"=>$cell->getDensity(),
				"weight"=>round($cell->getWeight(),2),
				"stress"=>round($cell->getFormationState()->getStress(),2),
				"deformation"=>$cell->getFormationState()->getDeformation(),
				"redistributionCount"=>$cell->getFormationState()->getRedistributionCount(),
				"fragmentId"=>$cell->getFragmentId(),
				"land"=>$cell->isLand(),
				"structures"=>$this->exportStructures($cell),
				"layers"=>$this->exportLayers($cell),
				"movement"=>$this->exportMovement($cell)
			],
			"events"=>$events
		];
	}
	
	// Экспортирует текущее состояние слоёв клетки
	private function exportLayers(MassCell $cell): array{
		$layers=[];
		foreach($cell->getLayers() as $layer){
			$layers[]=[
				"rock"=>$layer->getRock()->getId(),
				"amount"=>round($layer->getAmount(),2),
				"weight"=>round($layer->getWeight(),2),
				"compression"=>round($layer->getCompression(),2)
			];
		}
		return $layers;
	}
	
	// Экспортирует снимок состояния материала до или после события
	private function exportSnapshot(FormationSnapshot $snapshot): array{
		$layers=[];
		foreach($snapshot->getLayers() as $layer){
			$layers[]=["rock"=>$layer["rock"],"amount"=>round($layer["amount"],2)];
		}
		return["density"=>$snapshot->getDensity(),"layers"=>$layers];
	}
	
	// Экспортирует компактную карту количества материи
	private function exportMassAmount(MassMap $map): array{
		$mass=[];
		for($z=0;$z<$map->getSizeZ();$z++){
			$row=[];
			for($x=0;$x<$map->getSizeX();$x++){
				$cell=$map->get($x,$z);
				$row[]=$cell===null ? 0 : round($cell->getMaterialAmount(),2);
			}
			$mass[]=implode(" ",$row);
		}
		return $mass;
	}
	
	// Экспорт последнего изменения направления движения материала
	private function exportMovement(MassCell $cell): ?array{
		$previous=$cell->getPreviousMovement();
		$current=$cell->getCurrentMovement();
		if($previous===null && $current===null){
			return null;
		}
		return[
			"previous"=>$previous===null ? null :["x"=>$previous->getX(),"z"=>$previous->getZ()],
			"current"=>$current===null ? null :["x"=>$current->getX(),"z"=>$current->getZ()]
		];
	}
	
	private function exportStructures(MassCell $cell): array{
		$structures=[];
		foreach($cell->getStructures() as $structure){
			$structureId=$structure->getId();
			$neighbours=[];
			foreach($cell->getStructureNeighbours($structureId) as [$dx,$dz]){
				$neighbours[]=["x"=>$dx,"z"=>$dz,];
			}
			$structures[]=["id"=>$structureId,"type"=>$structure->getType(),"neighbours"=>$neighbours,];
		}
		return $structures;
	}
	
	public function exportGenerationSave(array $payload): void{
		try{
			$json = json_encode(
				$payload,
				JSON_PRETTY_PRINT
				| JSON_UNESCAPED_UNICODE
				| JSON_UNESCAPED_SLASHES
				| JSON_THROW_ON_ERROR
			);
		}catch(\JsonException $e){
			throw new \RuntimeException("Не удалось сериализовать сохранение генерации: " . $e->getMessage(),0,$e);
		}
		$path = $this->directory. DIRECTORY_SEPARATOR. "generation_save.json";
		if(file_put_contents($path, $json, LOCK_EX) === false){
			throw new \RuntimeException("Не удалось записать сохранение генерации: " . $path);
		}
	}

	// @return array<string,mixed>|null
	public function importGenerationSave(): ?array{
		$path = $this->directory . DIRECTORY_SEPARATOR . "generation_save.json";
		if(!is_file($path)){
			return null;
		}
		$contents = file_get_contents($path);
		if($contents === false){
			return null;
		}
		$data = json_decode($contents, true);
		return is_array($data) ? $data : null;
	}

	public function hasGenerationSave(): bool{return is_file($this->directory . DIRECTORY_SEPARATOR . "generation_save.json");}
}