<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

use Random\Engine\Mt19937;
use Random\Randomizer;

use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Mass\Fragment;

use WorldGenerator\Generator\Objects\ObjectLayout;

use WorldGenerator\Generator\Movement\MovementVector;

use WorldGenerator\Generator\Simulation\WaterTransfer;
use WorldGenerator\Generator\Simulation\Rules\WaterExtractionRule;
use WorldGenerator\Generator\Simulation\Rules\WaterPlacementRule;
use WorldGenerator\Generator\Simulation\Rules\WaterSaturationRule;
use WorldGenerator\Generator\Simulation\Rules\WaterErosionRule;
use WorldGenerator\Generator\Simulation\Rules\CellFinalizationRule;

final class IslandSimulation{
    private MassMap $map;
    private int $seed;
	private bool $cycleCompleted=false;
    /** @var Fragment[] */
    private array $fragments=[];
    private int $fragmentIndex=0;
    private float $minTransfer=15.0;
    private float $maxTransfer=30.0;
	
	private WaterExtractionRule $waterExtractionRule;
	private WaterPlacementRule $waterPlacementRule;
	private WaterSaturationRule $waterSaturationRule;
	private WaterErosionRule $waterErosionRule;
	private CellFinalizationRule $cellFinalizationRule;

    public function __construct(
		MassMap $map,
		int $seed
    ){
        $this->map=$map;
        $this->seed=$seed;
		$this->waterExtractionRule=new WaterExtractionRule();
		$this->waterPlacementRule=new WaterPlacementRule();
		$this->waterSaturationRule=new WaterSaturationRule();
		$this->waterErosionRule=new WaterErosionRule();
		$this->cellFinalizationRule=new CellFinalizationRule();
    }

    public function setMap(MassMap $map): void{$this->map=$map;}
    public function getMap(): MassMap{return $this->map;}

    /** @param Fragment[] $fragments */
    public function setFragments(array $fragments): void{
        $this->fragments=$fragments;
        $this->fragmentIndex=0;
    }

    /** @return Fragment[] */
    public function getFragments(): array{return $this->fragments;}

	public function step(int $totalSteps): bool{
		$this->cycleCompleted = false;
		if($this->fragments === []){
			return false;
		}
		$fragment = $this->fragments[$this->fragmentIndex];
		// Попытка хода засчитывается даже при отсутствии движения.
		$this->stepFragment($fragment, $totalSteps);
		++$this->fragmentIndex;
		if($this->fragmentIndex >= count($this->fragments)){
			$this->fragmentIndex = 0;
			$this->cycleCompleted = true;
		}
		return true;
	}

    public function setFragmentDirections(): void{
        $centerX=intdiv($this->map->getSizeX(),2);
        $centerZ=intdiv($this->map->getSizeZ(),2);
        foreach($this->fragments as $fragment){
            $cells=$fragment->getCells();
            if($cells===[]){
                continue;
            }
            $sumX=0;
            $sumZ=0;
            foreach($cells as $cell){
				$sumX+=$cell->getX();
				$sumZ+=$cell->getZ();
			}
            $averageX=$sumX/count($cells);
            $averageZ=$sumZ/count($cells);
            $directionX=$averageX<=>$centerX;
            $directionZ=$averageZ<=>$centerZ;
            if($directionX===0 && $directionZ===0){
                $directionX=1;
            }
            $fragment->setDirection($directionX,$directionZ);
        }
    }

    private function stepFragment(Fragment $fragment,int $totalSteps): bool{
        $directionX=$fragment->getDirectionX();
        $directionZ=$fragment->getDirectionZ();
        if($directionX===0 && $directionZ===0){
            return false;
        }
        $cells=$fragment->getCells();
        if($cells===[]){
            return false;
        }
        $sizeX=$this->map->getSizeX();
        $sizeZ=$this->map->getSizeZ();
        $moves=[];
        $sourcePositions=[];

        // Фаза 1. Рассчитываем все перемещения.
        foreach($cells as $cell){
            $sourceX=$cell->getX();
            $sourceZ=$cell->getZ();
            $targetX=($sourceX+$directionX)%$sizeX;
            $targetX=($targetX+$sizeX)%$sizeX;
            $targetZ=$sourceZ+$directionZ;
            if($targetZ<0 || $targetZ>=$sizeZ){
                $fragment->setDirection($directionX,-$directionZ);
                return true;
            }
            $sourceKey=$sourceX.":".$sourceZ;
            $targetKey=$targetX.":".$targetZ;
            $sourcePositions[$sourceKey]=true;
            $moves[]=[
                "cell"=>$cell,
                "sourceX"=>$sourceX,
                "sourceZ"=>$sourceZ,
                "targetX"=>$targetX,
                "targetZ"=>$targetZ
            ];
        }

        // Фаза 2. Проверяем столкновения.
        foreach($moves as $move){
            $targetX=$move["targetX"];
            $targetZ=$move["targetZ"];
            $targetCell=$this->map->get($targetX,$targetZ);
            if($targetCell===null){
                $fragment->setDirection(-$directionX,-$directionZ);
                return true;
            }
            $targetFragmentId=$targetCell->getFragmentId();
            if($targetFragmentId>=0 && $targetFragmentId!==$fragment->getId() && !isset($sourcePositions[$targetX.":".$targetZ])){
                echo "[Islands] Fragment #".$fragment->getId()." COLLISION: ".$move["sourceX"].":".$move["sourceZ"]." -> ".$targetX.":".$targetZ." occupied by Fragment #".$targetFragmentId.PHP_EOL;
                $fragment->setDirection(-$directionX,-$directionZ);
                return true;
            }
        }

        // Фаза 3. Готовим случайный объём переноса.
        $randomizer=new Randomizer( new Mt19937($this->seed+$totalSteps+($fragment->getId()*100000)));
        $transfers=[];

		// Фаза 4. Снимаем массу со всех источников.
		foreach($moves as $move){
			// @var MassCell $source
			$source=$move["cell"];
			$target=$this->map->get($move["targetX"],$move["targetZ"]);
			if($target===null){
				continue;
			}
			$amount=$randomizer->getInt((int)round($this->minTransfer*100),(int)round($this->maxTransfer*100))/100;
			$solidLayers=$source->extractSolidMass($amount);
			$waterTransfer=WaterTransfer::create($source->getLayers());
			$waterLayers=$source->extractWater();
			if(!$waterTransfer->isEmpty()){
				$this->waterExtractionRule->apply($source,$waterTransfer);
			}
			$actualAmount=0.0;
			foreach($solidLayers as $layer){
				$actualAmount+=$layer->getAmount();
			}
			if($solidLayers===[] && $waterLayers===[]){
				continue;
			}
			$transfers[]=[
				"source"=>$source,
				"target"=>$target,
				"solidLayers"=>$solidLayers,
				"waterLayers"=>$waterLayers,
				"waterTransfer"=>$waterTransfer,
				"amount"=>$actualAmount
			];
		}
		if($transfers===[]){
			return false;
		}

        // Фаза 5. Кладём всю снятую массу в цели.
        foreach($transfers as $transfer){
            // @var MassCell $target
            $target=$transfer["target"];
            if($transfer["solidLayers"]!==[]){
                $target->addSolidMass($transfer["solidLayers"]);
            }
            if($transfer["waterTransfer"] instanceof WaterTransfer){
                $waterTransfer=$transfer["waterTransfer"];
                if(!$waterTransfer->isEmpty()){
                    $this->waterPlacementRule->apply($target,$waterTransfer);
                    $this->waterSaturationRule->apply($target);
                    $this->waterErosionRule->apply($target);
                }
            }
            $this->cellFinalizationRule->apply($transfer["source"]);
            $this->cellFinalizationRule->apply($target);
            $amount=$transfer["amount"];
            if($amount>0.0){
                $target->getFormationState()->addRedistribution();
                $target->getFormationState()->addStress($amount);
                $target->recordMovement(
                    new MovementVector($directionX,$directionZ)
                );
            }
        }
        // Переносим структуры после размещения всей массы.
        $finalized=[];
        foreach($transfers as $transfer){
            $source=$transfer["source"];
            $target=$transfer["target"];
            $source->transferStructuresTo($target);
            $finalized[$source->getX().":".$source->getZ()]=$source;
            $finalized[$target->getX().":".$target->getZ()]=$target;
        }
        foreach($finalized as $cell){
            $this->cellFinalizationRule->apply($cell);
        }
		
        // Фаза 6. Обновляем принадлежность клеток.
        $newCells=[];
        foreach($moves as $move){
            // @var MassCell $source
            $source=$move["cell"];
            $target=$this->map->get($move["targetX"],$move["targetZ"]);
            if($target===null){
                continue;
            }
            $source->setFragmentId(-1);
            $target->setFragmentId($fragment->getId());
            $newCells[]=$target;
        }

        // Фаза 7. Обновляем Fragment.
        $fragment->setCells($newCells);
        echo "[Islands] Fragment #".$fragment->getId()." MOVED: cells=".count($newCells)." direction=".$directionX.",".$directionZ.PHP_EOL;
        return true;
    }

	public function isCycleCompleted(): bool{return $this->cycleCompleted;}
	
	public function getFragmentIndex(): int{return $this->fragmentIndex;}
	public function setFragmentIndex(int $index): void{$this->fragmentIndex=$index;}
	public function setCycleCompleted(bool $completed): void{$this->cycleCompleted=$completed;}
}