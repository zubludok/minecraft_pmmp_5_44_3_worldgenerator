<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Formation;

use WorldGenerator\Generator\Mass\MassMap;

final class ForceShapeGenerator{

    public function generate(MassMap $map): ForceShape{
        $cells=[];
        $minX=PHP_INT_MAX;
        $minZ=PHP_INT_MAX;
        $maxX=PHP_INT_MIN;
        $maxZ=PHP_INT_MIN;
        for($x=0;$x<$map->getSizeX();$x++){
            for($z=0;$z<$map->getSizeZ();$z++){
                $cell=$map->get($x,$z);
                if($cell===null){
                    continue;
                }
                /* Основная форма Force определяется именно географией суши.
                 * Наличие физической массы здесь значения не имеет. */
                if(!$cell->isLand() && $cell->getStructures()===[]){
                    continue;
                }
                $cells[]=[$x,$z];
                $minX=min($minX,$x);
                $minZ=min($minZ,$z);
                $maxX=max($maxX,$x);
                $maxZ=max($maxZ,$z);
            }
        }
        if($cells===[]){
            throw new \InvalidArgumentException("Cannot create Force shape without land");
        }
        $centerX=intdiv($minX+$maxX,2);
        $centerZ=intdiv($minZ+$maxZ,2);
        foreach($cells as &$cell){
            $cell[0]-=$centerX;
            $cell[1]-=$centerZ;
        }
        unset($cell);
        return new ForceShape($centerX,$centerZ,$cells);
    }
}