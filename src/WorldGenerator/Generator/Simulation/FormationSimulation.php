<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

use Random\Engine\Mt19937;
use Random\Randomizer;

use WorldGenerator\Generator\Mass\MassMap;
use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Objects\ObjectLayout;
use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Movement\MovementVector;
use WorldGenerator\Generator\Formation\FormationEvent;
use WorldGenerator\Generator\Formation\FormationSnapshot;
use WorldGenerator\Generator\Formation\Force;
use WorldGenerator\Generator\Formation\ForceShapeGenerator;

final class FormationSimulation{
/* 	FormationSimulation
    ├── движение объектов
    ├── столкновения
    ├── расчёт нагрузки
    ├── stress
    ├── deformation
    └── compression */
	
    private MassMap $map;
    private Force $force;
    private array $objects=[];		// @var SimulationObjectState[]
    private array $collisions=[];	// @var array<int,array{step:int,objects:int[],x:int,z:int,pressure:int}>

	public function __construct(
		MassMap $map,
		ObjectLayout $objectLayout,
		int $seed
	){
		$this->map=$map;
		foreach($objectLayout->getObjects() as $object){
			$this->objects[$object->getId()]=new SimulationObjectState($object);
		}
		$forceRandomizer=new \Random\Randomizer(
			new \Random\Engine\Mt19937($seed)
		);
		$shapeGenerator=new ForceShapeGenerator();
		$shape=$shapeGenerator->generate($map);
		$this->force=new Force($shape->getCenterX(),$shape->getCenterZ(),$shape,new MovementVector(1,0),15.0,30.0,$forceRandomizer);
		$this->setConvergingDirections();
	}

    public function setMap(MassMap $map): void{$this->map=$map;}
    public function getMap(): MassMap{return $this->map;}
    public function getForce(): Force{return $this->force;}
    public function getObjects(): array{return $this->objects;}	// @return SimulationObjectState[]
    public function getObject(int $objectId): ?SimulationObjectState{return $this->objects[$objectId]??null;}
    public function getCollisions(): array{return $this->collisions;}	// @return array<int,array{step:int,objects:int[],x:int,z:int,pressure:int}>
    public function getObjectCount(): int{return count($this->objects);}
    public function getFormationEventCount(int $x,int $z): int{$cell=$this->map->get($x,$z);return $cell?->getFormationEventCount()??0;}
    
	public function moveObjects(int $totalSteps): void{
        $nextPositions=[];
        foreach($this->objects as $object){
            $nextPositions[$object->getObjectId()]=[$object->getX()+$object->getDirectionX(),$object->getZ()+$object->getDirectionZ()];
        }
        $handled=[];
        foreach($this->objects as $object){
            $id=$object->getObjectId();
            if(isset($handled[$id])){
                continue;
            }
            [$nextX,$nextZ]=$nextPositions[$id];
            $participants=[$id];
            $collisionX=$nextX;
            $collisionZ=$nextZ;
            foreach($this->objects as $other){
                $otherId=$other->getObjectId();
                if($otherId===$id){
                    continue;
                }
                [$otherX,$otherZ]=$nextPositions[$otherId];
                $sameTarget=$nextX===$otherX && $nextZ===$otherZ;
                $crossing=$nextX===$other->getX() && $nextZ===$other->getZ() && $otherX===$object->getX() && $otherZ===$object->getZ();
                $contact=$object->getX()===$other->getX() && $object->getZ()===$other->getZ();
                if(!$sameTarget && !$crossing && !$contact){
                    continue;
                }
                $participants[]=$otherId;
                if($contact){
                    $collisionX=$object->getX();
                    $collisionZ=$object->getZ();
                }elseif($crossing){
                    $collisionX=$object->getX();
                    $collisionZ=$object->getZ();
                }
            }
            if(count($participants)>1){
                sort($participants);
                foreach($participants as $participantId){
                    $participant=$this->objects[$participantId];
                    $participant->setPosition($collisionX,$collisionZ);
                    $participant->setDirection(0,0);
                    $handled[$participantId]=true;
                }
                $this->addCollision($participants,$collisionX,$collisionZ,$totalSteps);
                $this->applyCollisionFormation($participants,$collisionX,$collisionZ,$totalSteps);
                continue;
            }
            $object->setPosition($nextX,$nextZ);
        }
    }

    private function addCollision(array $objects,int $x,int $z,int $totalSteps): void{
        sort($objects);
        foreach($this->collisions as $index=>$collision){
            if($collision["objects"]!==$objects){
                continue;
            }
            $this->collisions[$index]["pressure"]++;
            $this->collisions[$index]["step"]=$totalSteps+1;
            $this->collisions[$index]["x"]=$x;
            $this->collisions[$index]["z"]=$z;
            return;
        }
        $this->collisions[]=["step"=>$totalSteps+1,"objects"=>$objects,"x"=>$x,"z"=>$z,"pressure"=>1];
    }

    private function setConvergingDirections(): void{
        foreach($this->objects as $object){
            $target=$this->findNearestObject($object);
            if($target===null){
                $object->setDirection(0,0);
                continue;
            }
            $dx=$target->getX()-$object->getX();
            $dz=$target->getZ()-$object->getZ();
            $object->setDirection($dx===0 ? 0 : ($dx>0 ? 1 : -1),$dz===0 ? 0 : ($dz>0 ? 1 : -1));
        }
    }

    private function findNearestObject(SimulationObjectState $source): ?SimulationObjectState{
        $nearest=null;
        $nearestDistance=PHP_INT_MAX;
        foreach($this->objects as $object){
            if($object->getObjectId()===$source->getObjectId()){
                continue;
            }
            $dx=$object->getX()-$source->getX();
            $dz=$object->getZ()-$source->getZ();
            $distance=($dx*$dx)+($dz*$dz);
            if($distance<$nearestDistance){
                $nearestDistance=$distance;
                $nearest=$object;
            }
        }
        return $nearest;
    }

    private function applyCollisionFormation(array $objects,int $x,int $z,int $totalSteps): void{
        $cell=$this->map->get($x,$z);
        if($cell===null){
            return;
        }
        $pressure=1;
        foreach($this->collisions as $collision){
            if($collision["objects"]!==$objects){
                continue;
            }
            if($collision["x"]!==$x || $collision["z"]!==$z){
                continue;
            }
            $pressure=$collision["pressure"];
            break;
        }
        $before=FormationSnapshot::fromCell($cell);
        $load=$cell->getWeight()*$pressure;
        $strength=$this->getCellStrength($cell);
        $deformation=0;
        $cell->getFormationState()->addStress($load);
        $stress=$cell->getFormationState()->getStress();
        if($strength>0.0){
            $deformation=(int)floor(($stress/$strength)*10.0);
        }
        $deformation=max(0,min(100,$deformation));
        $cell->getFormationState()->setDeformation($deformation/100);
        if($deformation>0){
            $cell->getFormationState()->addCollision();
        }
		$this->applyDeformation($cell,$deformation);
        $after=FormationSnapshot::fromCell($cell);
        $event=new FormationEvent($totalSteps+1,FormationEvent::COLLISION,$objects,$x,$z,$pressure,0,0,$pressure,$before,$after,$deformation);
        $cell->addFormationEvent($event);
    }

    private function applyDeformation(MassCell $cell,int $deformation): void{
        if($deformation<25){
            return;
        }
        if($deformation<50){
            $cell->setDensity($this->increaseDensity($cell->getDensity(),1));
            $this->applyLayerCompression($cell,0.15);
            return;
        }
        if($deformation<75){
            $cell->setDensity($this->increaseDensity($cell->getDensity(),2));
            $this->applyLayerCompression($cell,0.30);
            return;
        }
        $cell->setDensity(MassCell::DENSE);
        $this->applyLayerCompression($cell,0.50);
    }

    private function applyLayerCompression(MassCell $cell,float $compression): void{
        foreach($cell->getLayers() as $layer){
            $layer->setCompression(max($layer->getCompression(),$compression));
        }
    }

    private function increaseDensity(int $density,int $levels): int{
        $result=$density;
        for($i=0;$i<$levels;$i++){
            $result=match($result){
                MassCell::LOOSE=>MassCell::WEAK,
                MassCell::WEAK=>MassCell::MEDIUM,
                MassCell::MEDIUM=>MassCell::DENSE,
                default=>MassCell::DENSE
            };
        }
        return $result;
    }
   
	private function getCellStrength(MassCell $cell): float{
        $totalWeight=0.0;
        $weightedStrength=0.0;
        foreach($cell->getLayers() as $layer){
            $weight=$layer->getWeight();
            if($weight<=0.0){
                continue;
            }
            $totalWeight+=$weight;
            $weightedStrength+=$weight*$layer->getRock()->getStrength();}
        if($totalWeight<=0.0){
            return 0.0;
        }
        return $weightedStrength/$totalWeight;
    }

    public function stepForce(int $step,int $totalSteps): bool{
        $result=$this->force->step($this->map,$step);
        if(!$result){
            return false;
        }
        return true;
    }

    public function getLastMovement(): MovementVector{return $this->force->getLastMovement();}
	
	public function setObjects(array $objects): void{
		$this->objects = [];
		foreach($objects as $object){
			if($object instanceof SimulationObjectState){
				$this->objects[$object->getObjectId()] = $object;
			}
		}
	}

	public function setCollisions(array $collisions): void{$this->collisions=$collisions;}
	public function setForce(Force $force): void{$this->force=$force;}
}