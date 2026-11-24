<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Formation;

use Random\Randomizer;
use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Movement\MovementVector;
use WorldGenerator\Generator\Formation\FormationEvent;
use WorldGenerator\Generator\Formation\FormationSnapshot;
use WorldGenerator\Generator\Simulation\WaterTransfer;
use WorldGenerator\Generator\Simulation\Rules\WaterExtractionRule;
use WorldGenerator\Generator\Simulation\Rules\WaterPlacementRule;
use WorldGenerator\Generator\Simulation\Rules\WaterSaturationRule;
use WorldGenerator\Generator\Simulation\Rules\WaterErosionRule;
use WorldGenerator\Generator\Simulation\Rules\CellFinalizationRule;

final class Force{

    private int $x;
    private int $z;
    private ForceShape $shape;
    private MovementVector $movement;
	private MovementVector $lastMovement;
    private float $minAmount;
    private float $maxAmount;
    private Randomizer $randomizer;
	private array $lastAffectedCells=[];
	
	private WaterExtractionRule $waterExtractionRule;
	private WaterPlacementRule $waterPlacementRule;
	private WaterSaturationRule $waterSaturationRule;
	private WaterErosionRule $waterErosionRule;
	private CellFinalizationRule $cellFinalizationRule;

	public function __construct(
		int $x,
		int $z,
		ForceShape $shape,
		MovementVector $movement,
		float $minAmount,
		float $maxAmount,
		Randomizer $randomizer
	){
		$this->x=$x;
		$this->z=$z;
		$this->shape=$shape;
		$this->movement=$movement;
		$this->lastMovement=$movement;
		$this->minAmount=max(0.0,$minAmount);
		$this->maxAmount=max($this->minAmount,$maxAmount);
		$this->randomizer=$randomizer;

		$this->waterExtractionRule=new WaterExtractionRule();
		$this->waterPlacementRule=new WaterPlacementRule();
		$this->waterSaturationRule=new WaterSaturationRule();
		$this->waterErosionRule=new WaterErosionRule();
		$this->cellFinalizationRule=new CellFinalizationRule();
	}

	// Выполняет один шаг силы с переносом массы всех клеток формы.
	public function step(MassMap $map,int $step): bool{
		$this->lastAffectedCells=[];
		$movement=$this->movement->next($map->getSizeX(),$map->getSizeZ(),$this->randomizer);
		$movement=$this->getMovementForNextStep($map,$movement);
		$this->lastMovement=$movement;
		$nextX=$this->wrapX($this->x+$movement->getX(),$map->getSizeX());
		$nextZ=$this->z+$movement->getZ();
		$transfers=[];
		$affected=[];
		foreach($this->shape->getCells() as $cell){
			$localX=$cell[0]-$this->getShapeCenterX();
			$localZ=$cell[1]-$this->getShapeCenterZ();
			$sourceX=$this->wrapX($this->x+$localX,$map->getSizeX());
			$sourceZ=$this->z+$localZ;
			$targetX=$this->wrapX($nextX+$localX,$map->getSizeX());
			$targetZ=$nextZ+$localZ;
			$source=$map->get($sourceX,$sourceZ);
			$target=$map->get($targetX,$targetZ);
			if($source===null || $target===null){
				return false;
			}
			$sourceKey=$sourceX.":".$sourceZ;
			$targetKey=$targetX.":".$targetZ;
			if(!isset($affected[$sourceKey])){
				$affected[$sourceKey]=["cell"=>$source,"before"=>FormationSnapshot::fromCell($source),"amount"=>0.0];
			}
			if(!isset($affected[$targetKey])){
				$affected[$targetKey]=["cell"=>$target,"before"=>FormationSnapshot::fromCell($target),"amount"=>0.0];
			}
			$amount=$this->randomizer->getInt((int)round($this->minAmount*100),(int)round($this->maxAmount*100))/100;
			$transfers[]=[
				"source"=>$source,
				"target"=>$target,
				"amount"=>$amount,
				"sourceKey"=>$sourceKey,
				"targetKey"=>$targetKey,
				"sourceX"=>$sourceX,
				"sourceZ"=>$sourceZ,
				"targetX"=>$targetX,
				"targetZ"=>$targetZ
			];
		}

		// Фаза 1: Сначала полностью извлекаем массу из всех источников. Никакого размещения здесь нет.
		foreach($transfers as &$transfer){
			$beforeWaterLayers=$transfer["source"]->getLayers();
			$solidLayers=$transfer["source"]->extractSolidMass($transfer["amount"]);
			// После извлечения твёрдой массы фиксируем состояние оставшегося водяного слоя.
			$waterTransfer=WaterTransfer::create($transfer["source"]->getLayers());
			$waterLayers=$transfer["source"]->extractWater();
			if(!$waterTransfer->isEmpty()){
				$this->waterExtractionRule->apply($transfer["source"],$waterTransfer);
			}
			$actualAmount=0.0;
			foreach($solidLayers as $layer){
				$actualAmount+=$layer->getAmount();
			}
			$transfer["solidLayers"]=$solidLayers;
			$transfer["waterTransfer"]=$waterTransfer;
			$transfer["waterLayers"]=$waterLayers;
			$transfer["beforeWaterLayers"]=$beforeWaterLayers;
			$transfer["actualAmount"]=$actualAmount;
			$affected[$transfer["sourceKey"]]["amount"]+=$actualAmount;
			$affected[$transfer["targetKey"]]["amount"]+=$actualAmount;
		}

		unset($transfer);

		// Фаза 2: Размещаем всю извлечённую массу.
		foreach($transfers as $transfer){
			/** @var MassCell $source */
			$source=$transfer["source"];
			/** @var MassCell $target */
			$target=$transfer["target"];
			if($transfer["solidLayers"]!==[]){
				$target->addSolidMass($transfer["solidLayers"]);
			}
			$target->addWater($transfer["waterLayers"]);
			$source->transferStructuresTo($target);
		}

		// Фаза 3: Только после всех переносов нормализуем клетки. Это важно, поскольку одна клетка может быть одновременно
		// источником одного переноса и целью другого.
		foreach($affected as $data){$this->cellFinalizationRule->apply($data["cell"]);}

		// Фаза 4: Записываем результаты формирования.
		foreach($affected as $data){
			$cell=$data["cell"];
			$amount=$data["amount"];
			if($amount<=0.0){
				continue;
			}
			$after=FormationSnapshot::fromCell($cell);
			$cell->recordMovement($movement);
			$cell->getFormationState()->addRedistribution();
			$cell->getFormationState()->addStress($amount);
			$event=new FormationEvent(
				$step,
				FormationEvent::REDISTRIBUTION,
				[],
				$cell->getX(),
				$cell->getZ(),
				0,
				$movement->getX(),
				$movement->getZ(),
				(int)round($amount),
				$data["before"],
				$after,
				0
			);
			$cell->addFormationEvent($event);
		}
		$this->movement=$movement;
		$this->x=$nextX;
		$this->z=$nextZ;
		foreach($affected as $data){
			$this->lastAffectedCells[]=$data["cell"];
		}
		return true;
	}

    // Определяет вектор следующего шага с учётом северной и южной границы.
	private function getMovementForNextStep(MassMap $map,MovementVector $movement): MovementVector{
        if($movement->getZ()===0){
            return $movement;
        }
        $nextZ=$this->z+$movement->getZ();
        $minZ=$this->shape->getMinZ()-$this->getShapeCenterZ();
        $maxZ=$this->shape->getMaxZ()-$this->getShapeCenterZ();
        if($nextZ+$minZ<0 || $nextZ+$maxZ>=$map->getSizeZ()){
            return $movement->invertZ();
        }
        return $movement;
    }

    // Замыкает координату X по цилиндрической карте.
    private function wrapX(int $x,int $sizeX): int{
        if($sizeX<=0)return 0;
        $x%=$sizeX;
        if($x<0){
            $x+=$sizeX;
        }
        return $x;
    }

    // Проверяет наличие клетки формы по локальным координатам относительно центра Force.
    public function isInside(int $localX,int $localZ): bool{
        $shapeX=$localX+$this->getShapeCenterX();
        $shapeZ=$localZ+$this->getShapeCenterZ();

        return $this->shape->contains($shapeX,$shapeZ);
    }

    // Устанавливает новое направление движения Force.
    public function setMovement(MovementVector $movement): void{$this->movement=$movement;}
    public function getMovement(): MovementVector{return $this->movement;}

    /** @return array<int,array{0:int,1:int}> */
    public function getCells(): array{
        $cells=[];
        foreach($this->shape->getCells() as $cell){
            $cells[]=[$cell[0]-$this->getShapeCenterX(),$cell[1]-$this->getShapeCenterZ()];
        }
        return $cells;
    }

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getDirectionX(): int{return $this->movement->getX();}
    public function getDirectionZ(): int{return $this->movement->getZ();}
    public function getMinAmount(): float{return $this->minAmount;}
    public function getMaxAmount(): float{return $this->maxAmount;}
    public function getWidth(): int{return $this->shape->getWidth();}
    public function getHeight(): int{return $this->shape->getHeight();}
    public function getShape(): ForceShape{return $this->shape;}
    private function getShapeCenterX(): int{return intdiv($this->shape->getMinX()+$this->shape->getMaxX(),2);}
    private function getShapeCenterZ(): int{return intdiv($this->shape->getMinZ()+$this->shape->getMaxZ(),2);}
	
	// Перенос отрисовки для карты
	public function containsMapCell(int $x,int $z,MassMap $map): bool{
		foreach($this->shape->getCells() as $cell){
			$localX=$cell[0]-$this->getShapeCenterX();
			$localZ=$cell[1]-$this->getShapeCenterZ();
			$forceX=$this->wrapX($this->x+$localX,$map->getSizeX());
			$forceZ=$this->z+$localZ;
			if($forceX===$x && $forceZ===$z){
				return true;
			}
		}
		return false;
	}
	
	public function getLastMovement(): MovementVector{return $this->lastMovement;}
	
	// @return MassCell[]
	public function getLastAffectedCells(): array{return $this->lastAffectedCells;}
	
	public function getRandomizer(): Randomizer{return $this->randomizer;}
	public function setRandomizer(Randomizer $randomizer): void{$this->randomizer=$randomizer;}
}