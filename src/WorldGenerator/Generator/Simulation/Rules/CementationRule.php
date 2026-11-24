<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class CementationRule implements RockTransformationProcess{
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
            // Цементация возможна только у материала, который способен удерживать частицы.
            if($rock->getCohesion() <= 0.0){
                continue;
            }
            // Материал должен быть достаточно уплотнён.
            if($layer->getCompression() < 0.7){
                continue;
            }

            /* Постепенно увеличиваем связность через compression/cohesion процесса.
             * Сам RockType пока не меняем. */
            $fraction = $layer->getFraction();
            if($fraction > 0.1){
                $layer->setFraction(
                    max(0.0, $fraction - 0.01)
                );
                $changed = true;
            }
        }
        return $changed;
    }
}