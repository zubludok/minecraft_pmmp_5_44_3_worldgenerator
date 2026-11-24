<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class CompactionRule implements RockTransformationProcess{

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
            if($rock->getCompressibility() <= 0.0){
                continue;
            }
            $weight = $layer->getWeight();
            if($weight <= 0.0){
                continue;
            }
            // Чем выше нагрузка и сжимаемость, тем быстрее материал уплотняется.
            $pressure = min(1.0, $weight / 100.0);
            $compression = $pressure * $rock->getCompressibility() * 0.05;
            if($compression <= 0.0){
                continue;
            }
            $before = $layer->getCompression();
            $layer->addCompression($compression);
            if($layer->getCompression() !== $before){$changed = true;}
        }
        return $changed;
    }
}