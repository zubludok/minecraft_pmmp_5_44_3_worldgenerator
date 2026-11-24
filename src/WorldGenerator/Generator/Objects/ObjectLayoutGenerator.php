<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Objects;

use Random\Engine\Mt19937;
use Random\Randomizer;

final class ObjectLayoutGenerator{

    private int $sizeX;
    private int $sizeZ;
    private int $objectCount;
    private Randomizer $randomizer;

    public function __construct(
        int $sizeX,
        int $sizeZ,
        int $objectCount,
        int $seed
    ){
        $this->sizeX=max(1,$sizeX);
        $this->sizeZ=max(1,$sizeZ);
        $this->objectCount=max(1,$objectCount);

        $this->randomizer=new Randomizer(
            new Mt19937($seed)
        );
    }

    public function generate(): ObjectLayout{
        [$columns,$rows]=$this->calculateGrid();
        $layout=new ObjectLayout($columns,$rows);
        for($id=0;$id<$this->objectCount;$id++){
            $gridX=$id%$columns;
            $gridZ=intdiv($id,$columns);
            $positionX=$this->calculatePosition($gridX,$columns,$this->sizeX);
            $positionZ=$this->calculatePosition($gridZ,$rows,$this->sizeZ);
            $object=new ObjectPlacement($id,$gridX,$gridZ,$positionX,$positionZ);
            $this->setDirection($object,$columns,$rows);
            $object->setMovementDelay($this->calculateDelay($gridX,$gridZ,$columns,$rows));
            $layout->addObject($object);
        }
        return $layout;
    }

    /** @return array{int,int} */
    private function calculateGrid(): array{
        $bestColumns=1;
        $bestRows=$this->objectCount;
        $bestScore=PHP_FLOAT_MAX;
        for($columns=1;$columns<=$this->objectCount;$columns++){
            $rows=(int)ceil($this->objectCount/$columns);
            $gridRatio=$columns/$rows;
            $worldRatio=$this->sizeX/$this->sizeZ;
            $ratioDifference=abs($gridRatio-$worldRatio);
            $emptyCells=($columns*$rows)-$this->objectCount;
            $score=($ratioDifference*10)+($emptyCells*0.5);
            if($score<$bestScore){
				$bestScore=$score;
                $bestColumns=$columns;
                $bestRows=$rows;
            }
        }
        return [$bestColumns,$bestRows];
    }

    private function calculatePosition(int $gridPosition,int $gridSize,int $mapSize): int{
        if($gridSize===1){
            return intdiv($mapSize-1,2);
        }
        $step=($mapSize-1)/$gridSize;
        return (int)round(($gridPosition+0.5)*$step);
    }

    private function setDirection(ObjectPlacement $object,int $columns,int $rows): void{
        $centerX=($columns-1)/2;
        $centerZ=($rows-1)/2;
        $dx=$object->getGridX()-$centerX;
        $dz=$object->getGridZ()-$centerZ;
        $directionX=0;
        $directionZ=0;
        if($dx<0){
            $directionX=-1;
        }elseif($dx>0){
            $directionX=1;
        }
        if($dz<0){
            $directionZ=-1;
        }elseif($dz>0){
            $directionZ=1;
        }
        if($directionX===0 && $directionZ===0){
            $directionX=$this->randomizer->getInt(-1,1);
            $directionZ=$this->randomizer->getInt(-1,1);
            if($directionX===0 && $directionZ===0){
				$directionX=1;
			}
        }
        $object->setDirection($directionX,$directionZ);
    }

    private function calculateDelay(int $gridX,int $gridZ,int $columns,int $rows): int{
        $centerX=($columns-1)/2;
        $centerZ=($rows-1)/2;
        $distanceX=abs($gridX-$centerX);
        $distanceZ=abs($gridZ-$centerZ);
        $distance=max($distanceX,$distanceZ);
        $maxDistance=max($centerX,$centerZ);
        if($maxDistance<=0){
            return 0;
        }
        $delay=(int)round(($maxDistance-$distance)*2);
        return max(0,$delay);
    }
}