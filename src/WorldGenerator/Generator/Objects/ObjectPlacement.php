<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Objects;

final class ObjectPlacement{

    private int $id;
    private int $gridX;
    private int $gridZ;
    private int $positionX;
    private int $positionZ;
    private int $directionX=0;
    private int $directionZ=0;
    private int $movementDelay=0;

    public function __construct(
        int $id,
        int $gridX,
        int $gridZ,
        int $positionX,
        int $positionZ
    ){
        $this->id=$id;
        $this->gridX=$gridX;
        $this->gridZ=$gridZ;
        $this->positionX=$positionX;
        $this->positionZ=$positionZ;
    }

    public function getId(): int{return $this->id;}
    public function getGridX(): int{return $this->gridX;}
    public function getGridZ(): int{return $this->gridZ;}
    public function getPositionX(): int{return $this->positionX;}
    public function getPositionZ(): int{return $this->positionZ;}
    public function setDirection(int $x,int $z): void{$this->directionX=$x;$this->directionZ=$z;}
    public function getDirectionX(): int{return $this->directionX;}
    public function getDirectionZ(): int{return $this->directionZ;}
    public function setMovementDelay(int $delay): void{$this->movementDelay=max(0,$delay);}
    public function getMovementDelay(): int{return $this->movementDelay;}
}