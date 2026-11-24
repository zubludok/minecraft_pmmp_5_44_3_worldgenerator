<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class FractureRule implements RockTransformationProcess{

    public function apply(MassCell $cell): bool{
        $changed = false;
        foreach($cell->getLayers() as $layer){
            if(!$layer instanceof RockLayer){
                continue;
            }
            if(!$layer->isSolid() || $layer->isEmpty() || $layer->isWater()){
                continue;
            }
            $rock = $layer->getRock();
            if($rock->getBrittleness() <= 0.0){
                continue;
            }
            if($layer->getStress() <= $rock->getStrength()){
                continue;
            }
            $excess = $layer->getStress() - $rock->getStrength();
            $fracture = min(1.0,$excess * $rock->getBrittleness());
            if($fracture <= 0.0){
                continue;
            }
            // Разрушение уменьшает средний размер фракции.
            $layer->setFraction($layer->getFraction() * (1.0 - $fracture));
            // Часть напряжения снимается разрушением.
            $layer->setStress($layer->getStress() * (1.0 - $fracture));
            $changed = true;
        }
        return $changed;
    }
}