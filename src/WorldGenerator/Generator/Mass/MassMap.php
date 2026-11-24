<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Mass;

use WorldGenerator\Generator\Geology\RockRegistry;

final class MassMap{

    private int $sizeX;
    private int $sizeZ;

    private RockRegistry $rockRegistry;

    /** @var MassCell[][] */
    private array $cells=[];

    public function __construct(
        int $sizeX,
        int $sizeZ,
        RockRegistry $rockRegistry
    ){
        $this->sizeX=$sizeX;
        $this->sizeZ=$sizeZ;
        $this->rockRegistry=$rockRegistry;

        for($x=0;$x<$sizeX;$x++){
            for($z=0;$z<$sizeZ;$z++){
                $this->cells[$x][$z]=new MassCell($x,$z,$this->rockRegistry);
            }
        }
    }

    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
    public function get(int $x,int $z): ?MassCell{return $this->cells[$x][$z] ?? null;}
    public function getRockRegistry(): RockRegistry{return $this->rockRegistry;}

    public function countMass(): int{
        $count=0;
        for($x=0;$x<$this->sizeX;$x++){
            for($z=0;$z<$this->sizeZ;$z++){if(!
				$this->cells[$x][$z]->isEmpty()){$count++;}
            }
        }
        return $count;
    }

    public function set(int $x,int $z,MassCell $cell): void{
        if($x<0 || $x>=$this->sizeX || $z<0 || $z>=$this->sizeZ){
            return;
        }
        $this->cells[$x][$z]=$cell;
    }
}