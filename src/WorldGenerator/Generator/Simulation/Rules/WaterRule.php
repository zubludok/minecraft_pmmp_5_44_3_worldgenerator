<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class WaterRule implements RockTransformationProcess{
	
    public function apply(MassCell $cell): bool{
        $layers = $cell->getLayers();
        $changed = false;
        foreach($layers as $index => $layer){
            if(!$layer instanceof RockLayer){
                continue;
            }
            if(!$layer->isWater()){
                continue;
            }
            $waterAmount = $layer->getAmount();
            if($waterAmount <= 0.0){
                continue;
            }
            // Вода воздействует на находящийся ниже материал.
            $targetIndex = $index - 1;
            if($targetIndex < 0){
                continue;
            }
            $target = $layers[$targetIndex] ?? null;
            if(!$target instanceof RockLayer){
                continue;
            }
            if(!$target->isSolid() || $target->isEmpty()){
                continue;
            }
            $impact = min(1.0,$waterAmount * (1.0 - $target->getRock()->getHardness()) * 0.01);
            if($impact <= 0.0){
                continue;
            }
            $target->addWaterImpact($impact);
            // Часть воды становится связанной с материалом.
            if($target->getRock()->getCohesion() < 0.5){
                $target->addWaterContent($waterAmount * 0.01);
            }
            $changed = true;
        }
        return $changed;
    }
}