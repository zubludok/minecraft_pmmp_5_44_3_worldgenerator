<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class MaterialSeparationRule implements RockTransformationProcess{

    public function apply(MassCell $cell): bool{
        $changed = false;
        foreach($cell->getLayers() as $layer){
            if(!$layer instanceof RockLayer){
                continue;
            }
            if(!$layer->isSolid() || $layer->isEmpty() || $layer->isWater()){
                continue;
            }
            // Чем меньше связность, тем легче материал разделяется на фракции.
            $cohesion = $layer->getRock()->getCohesion();
            if($cohesion >= 0.5){
                continue;
            }
            $fraction = $layer->getFraction();

            // Очень мелкий или очень крупный материал уже находится преимущественно в своей фракции.
            if($fraction <= 0.05 || $fraction >= 0.95){
                continue;
            }
            $separation = (0.5 - $cohesion) * 0.1;
            $layer->setFraction(max(0.0, $fraction - $separation));
            $changed = true;
        }
        return $changed;
    }
}