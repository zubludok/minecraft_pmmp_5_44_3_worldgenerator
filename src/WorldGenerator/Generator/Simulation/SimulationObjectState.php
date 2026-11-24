<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

use WorldGenerator\Generator\Objects\ObjectPlacement;

final class SimulationObjectState{

    private int $objectId;
    private int $x;
    private int $z;
    private int $directionX;
    private int $directionZ;
    private int $movementDelay;

    public function __construct(ObjectPlacement $object){
        $this->objectId=$object->getId();
        $this->x=$object->getPositionX();
        $this->z=$object->getPositionZ();
        $this->directionX=$object->getDirectionX();
        $this->directionZ=$object->getDirectionZ();
        $this->movementDelay=$object->getMovementDelay();
    }

    public function getObjectId(): int{return $this->objectId;}
    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getDirectionX(): int{return $this->directionX;}
    public function getDirectionZ(): int{return $this->directionZ;}
    public function getMovementDelay(): int{return $this->movementDelay;}
    // Перемещает объект на один шаг по его направлению
    public function move(): void{$this->x+=$this->directionX;$this->z+=$this->directionZ;}
    // Устанавливает направление движения объекта
    public function setDirection(int $x,int $z): void{$this->directionX=$x;$this->directionZ=$z;}
    public function setPosition(int $x,int $z): void{$this->x=$x;$this->z=$z;}
}