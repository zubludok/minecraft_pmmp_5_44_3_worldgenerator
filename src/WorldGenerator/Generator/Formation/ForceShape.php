<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Formation;

final class ForceShape{

    private int $centerX;
    private int $centerZ;

    /** @var array<int,array{0:int,1:int}> */
    private array $cells;

    /* Хранит готовую форму Force и её опорный центр.
     * @param array<int,array{0:int,1:int}> $cells */
    public function __construct(
        int $centerX,
        int $centerZ,
        array $cells
    ){
        if($cells===[]){
            throw new \InvalidArgumentException("Force shape must contain at least one cell");
        }
        $this->centerX=$centerX;
        $this->centerZ=$centerZ;
        $this->cells=$cells;
    }

    public function getCenterX(): int{return $this->centerX;}
    public function getCenterZ(): int{return $this->centerZ;}

    /** @return array<int,array{0:int,1:int}> */
    public function getCells(): array{return $this->cells;}
    public function getCellCount(): int{return count($this->cells);}

    public function contains(int $localX,int $localZ): bool{
        foreach($this->cells as $cell){
            if($cell[0]===$localX && $cell[1]===$localZ){
                return true;
            }
        }
        return false;
    }

    public function getMinX(): int{
        $min=PHP_INT_MAX;
        foreach($this->cells as $cell){
            $min=min($min,$cell[0]);
        }
        return $min;
    }

    public function getMaxX(): int{
        $max=PHP_INT_MIN;
        foreach($this->cells as $cell){
            $max=max($max,$cell[0]);
        }
        return $max;
    }

    public function getMinZ(): int{
        $min=PHP_INT_MAX;
        foreach($this->cells as $cell){
            $min=min($min,$cell[1]);
        }
        return $min;
    }

    public function getMaxZ(): int{
        $max=PHP_INT_MIN;
        foreach($this->cells as $cell){
            $max=max($max,$cell[1]);
        }
        return $max;
    }
	
    public function getWidth(): int{return $this->getMaxX()-$this->getMinX()+1;}
    public function getHeight(): int{return $this->getMaxZ()-$this->getMinZ()+1;}
}