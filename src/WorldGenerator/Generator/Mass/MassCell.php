<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Mass;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Geology\RockRegistry;
use WorldGenerator\Generator\Geology\RockType;
use WorldGenerator\Generator\Geology\FormationState;
use WorldGenerator\Generator\Formation\FormationEvent;
use WorldGenerator\Generator\Movement\MovementVector;
use WorldGenerator\Generator\Structure\Structure;

final class MassCell{

    private int $x;
    private int $z;

    private int $density=self::EMPTY;
    private int $fragmentId=-1;
    private int $originObjectId=-1;

    private RockRegistry $rockRegistry;

    /** @var RockLayer[] */
    private array $layers=[];
	/** @var array<int,array{
	 *     structure:Structure,
	 *     neighbours:array<int,array{0:int,1:int}>
	 * }> */
	private array $structures=[];
    // @var FormationEvent[]
    private array $formationHistory=[];

    public const EMPTY=0;
    public const DENSE=1;
    public const MEDIUM=2;
    public const WEAK=3;
    public const LOOSE=4;

    private FormationState $formationState;

    private ?MovementVector $previousMovement=null;
    private ?MovementVector $currentMovement=null;
	
	private bool $land=false;

    public function __construct(
        int $x,
        int $z,
        RockRegistry $rockRegistry
    ){
        $this->x=$x;
        $this->z=$z;
        $this->rockRegistry=$rockRegistry;
        $this->formationState=new FormationState();
    }
	
    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getDensity(): int{return $this->density;}
    public function setDensity(int $density): void{$this->density=$density;}
    public function getFragmentId(): int{return $this->fragmentId;}
    public function setFragmentId(int $fragmentId): void{$this->fragmentId=$fragmentId;}
    public function getOriginObjectId(): int{return $this->originObjectId;}
    public function setOriginObjectId(int $objectId): void{$this->originObjectId=$objectId;}
    public function getRockRegistry(): RockRegistry{return $this->rockRegistry;}
    public function getRock(string $id): ?RockType{return $this->rockRegistry->get($id);}
    public function getEmptyRock(): ?RockType{return $this->rockRegistry->get("EMPTY");}
    public function getWaterRock(): ?RockType{return $this->rockRegistry->get("WATER");}

    /* Добавляет физический слой без дополнительной логики.
     * Используется для первоначальной генерации и специальных
     * операций, где порядок слоёв задаётся вызывающим кодом. */
    public function addLayer(RockLayer $layer): void{$this->layers[]=$layer;}
	
	public function extractSolidMass(float $amount): array{
		$amount=max(0.0,$amount);
		if($amount<=0.0){
			return [];
		}
		$extracted=[];
		foreach($this->layers as $layer){
			if($amount<=0.0){
				break;
			}
			$rock=$layer->getRock();
			if($rock->isWater() || $rock->isEmpty()){
				continue;
			}
			$available=$layer->getAmount();
			if($available<=0.0){
				continue;
			}
			$take=min($available,$amount);
			$ratio=$available>0.0 ? $take/$available : 0.0;
			$waterContent=$layer->getWaterContent();
			$extractedLayer=new RockLayer($rock,$take);
			$extractedLayer->setCompression($layer->getCompression());
			// Stress относится к отделяемому объёму, поэтому делится пропорционально.
			$extractedLayer->setStress($layer->getStress()*$ratio);
			$extractedLayer->setFraction($layer->getFraction());
			$extractedLayer->setWaterImpact($layer->getWaterImpact());
			// Насыщенная часть материала также должна разделиться пропорционально.
			$extractedWaterContent=$waterContent*$ratio;
			$extractedLayer->setWaterContent(min($take,$extractedWaterContent));
			$extracted[]=$extractedLayer;
			$layer->setAmount($available-$take);
			$layer->setStress($layer->getStress()*(1.0-$ratio));
			$layer->setWaterContent(max(0.0,$waterContent-$extractedWaterContent));
			$amount-=$take;
		}
		$this->removeEmptyLayers();
		$this->updateDensity();
		return $extracted;
	}

	public function extractWater(): array{
		$water=[];
		foreach($this->layers as $index=>$layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			if(!$layer->isWater()){
				continue;
			}
			$amount=$layer->getAmount();
			if($amount<=0.0){
				continue;
			}
			$waterLayer=new RockLayer($layer->getRock(),$amount);
			$waterLayer->setCompression($layer->getCompression());
			$waterLayer->setStress($layer->getStress());
			$waterLayer->setFraction($layer->getFraction());
			$waterLayer->setWaterImpact($layer->getWaterImpact());
			$waterLayer->setWaterContent($layer->getWaterContent());
			$water[]=$waterLayer;
			unset($this->layers[$index]);
		}
		$this->layers=array_values($this->layers);
		$this->updateDensity();
		return $water;
	}
	
    public function addSolidMass(array $layers): void{
        foreach($layers as $layer){
            $amount=$layer->getAmount();
            if($amount<=0.0){
                continue;
            }
            $rock=$layer->getRock();
            if($rock->isWater() || $rock->isEmpty()){
                continue;
            }
            $topIndex=count($this->layers)-1;
            $topLayer=$topIndex>=0 ? $this->layers[$topIndex] : null;
            if($topLayer!==null && $topLayer->getRock()->getId()===$rock->getId()){
                $topLayer->addAmount($amount);
                $topLayer->addCompression($layer->getCompression());
            }else{
                $newLayer=new RockLayer($rock,$amount);
                $newLayer->setCompression($layer->getCompression());
                $this->layers[]=$newLayer;
            }
        }
		$this->updateDensity();
    }

    /* Добавляет воду как отдельный слой.
     * Фактическое поведение воды при контакте с породой
     * будет выполняться WaterSimulation.
     * @param RockLayer[] $water */
    public function addWater(array $water): void{
        foreach($water as $layer){
            if($layer->getAmount()<=0.0){
                continue;
            }
            if(!$layer->getRock()->isWater()){
                continue;
            }
            $newLayer=new RockLayer($layer->getRock(),$layer->getAmount());
            $newLayer->setCompression($layer->getCompression());
            $this->layers[]=$newLayer;
        }
		$this->updateDensity();
    }

    // Возвращает общее количество материи, включая воду, но без EMPTY.
    public function getMaterialAmount(): float{
        $amount=0.0;
        foreach($this->layers as $layer){
            if($layer->getRock()->isEmpty()){
                continue;
            }
            $amount+=$layer->getAmount();
        }
        return $amount;
    }

    // Удаляет слои с нулевым количеством.
    public function removeEmptyLayers(): void{
        $this->layers=array_values(
            array_filter($this->layers, static fn(RockLayer $layer): bool =>$layer->getAmount()>0.0));
    }

    // @return RockLayer[]
    public function getLayers(): array{return $this->layers;}
	// Добавляет структуру в клетку.
	// Добавляет структуру в клетку.
	public function addStructure(Structure $structure): void{
		$id=$structure->getId();
		if(isset($this->structures[$id])){
			return;
		}
		$this->structures[$id]=["structure"=>$structure,"neighbours"=>[],];
	}

	/* Добавляет относительную связь клетки этой структуры с соседней клеткой той же структуры.
	 * @param array{0:int,1:int} $offset */
	public function addStructureNeighbour(int $structureId,array $offset): void{
		if(!isset($this->structures[$structureId])){
			return;
		}
		foreach($this->structures[$structureId]["neighbours"] as $existing){
			if($existing[0]===$offset[0] && $existing[1]===$offset[1]){
				return;
			}
		}
		$this->structures[$structureId]["neighbours"][]=[$offset[0],$offset[1],];
	}

	// @return array<int,array{0:int,1:int}>
	public function getStructureNeighbours(int $structureId): array{return $this->structures[$structureId]["neighbours"] ?? [];}

	// Удаляет структуру из клетки.
	public function removeStructure(int $structureId): void{unset($this->structures[$structureId]);}

	// Проверяет наличие структуры.
	public function hasStructure(int $structureId): bool{return isset($this->structures[$structureId]);}

	// Проверяет наличие структуры указанного типа.
	public function hasStructureType(string $type): bool{
		foreach($this->structures as $data){
			if($data["structure"]->getType()===$type){
				return true;
			}
		}
		return false;
	}

	// @return Structure[]
	public function getStructures(): array{
		$result=[];
		foreach($this->structures as $data){
			$result[]=$data["structure"];
		}
		return $result;
	}

	// @param Structure[] $structures
	public function setStructures(array $structures): void{
		$this->structures=[];
		foreach($structures as $structure){
			$this->structures[$structure->getId()]=["structure"=>$structure,"neighbours"=>[],];
		}
	}

	public function clearStructures(): void{$this->structures=[];}
	
	// Переносит структуры вместе с их локальными связями.
	public function transferStructuresTo(MassCell $target): void{
		if($this===$target){
			return;
		}
		foreach($this->structures as $id=>$data){
			$target->structures[$id]=$data;
		}
		$this->structures=[];
	}

    // Возвращает общий вес всех слоёв.
    public function getWeight(): float{
        $weight=0.0;
        foreach($this->layers as $layer){
            $weight+=$layer->getWeight();
        }
        return $weight;
    }

    //Проверяет наличие материала в клетке.
    public function hasMaterial(string $rockId): bool{
        foreach($this->layers as $layer){
            if($layer->getRock()->getId()===$rockId){
                return true;
            }
        }
        return false;
    }

    public function isEmpty(): bool{return $this->density===self::EMPTY;}
    public function getFormationState(): FormationState{return $this->formationState;}
    // Сохраняет последнее изменение формирования клетки.
    public function addFormationEvent(FormationEvent $event): void{$this->formationHistory=[$event];}
    // @return FormationEvent[]
    public function getFormationHistory(): array{return $this->formationHistory;}
    public function getFormationEventCount(): int{return count($this->formationHistory);}
    
	//Записывает последнее изменение направления воздействия.
    public function recordMovement(MovementVector $movement): void{
        $this->previousMovement=$this->currentMovement;
        $this->currentMovement=new MovementVector($movement->getX(),$movement->getZ());
    }

    public function getPreviousMovement(): ?MovementVector{return $this->previousMovement;}
    public function getCurrentMovement(): ?MovementVector{return $this->currentMovement;}
    public function setPosition(int $x,int $z): void{$this->x=$x;$this->z=$z;}

	private function updateDensity(): void{
		$hasSolid=false;
		$hasWater=false;
		foreach($this->layers as $layer){
			$rock=$layer->getRock();
			if($layer->getAmount()<=0.0 || $rock->isEmpty()){
				continue;
			}
			if($rock->isWater()){
				$hasWater=true;
				continue;
			}
			$hasSolid=true;
		}
		if($hasSolid){
			if($this->density===self::EMPTY){
				$this->density=self::LOOSE;
			}
			return;
		}
		if($hasWater){
			$this->density=self::LOOSE;
			return;
		}
		$this->density=self::EMPTY;
	}
	
	// @param RockLayer[] $layers
	public function setLayers(array $layers): void{
		$this->layers=array_values($layers);
		$this->removeEmptyLayers();
		$this->updateDensity();
	}
	
	public function isLand(): bool{return $this->land;}
	public function setLand(bool $land): void{$this->land=$land;}
	
	// @param FormationEvent[] $events
	public function setFormationHistory(array $events): void{$this->formationHistory=$events;}
}