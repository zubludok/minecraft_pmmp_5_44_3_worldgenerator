<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation\Rules;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Geology\RockLayer;

final class SolidExtractionRule{
    /* Применяет последствия извлечения твёрдой материи из клетки.
     * → извлекает снизу вверх
     * @param RockLayer[] $layers */
	 
    public function simulate(MassCell $source,array $layers): void{
        $amount=0.0;
        foreach($layers as $layer){
            if(!$layer instanceof RockLayer){
                continue;
            }
            if($layer->isEmpty() || $layer->isWater()){
                continue;
            }
            $amount+=$layer->getAmount();
        }
        if($amount<=0.0){
            return;
        }
        $source->getFormationState()->addRedistribution();
        $source->getFormationState()->addStress($amount);
        if($source->getLayers()===[]){
            $source->setDensity(MassCell::EMPTY);
        }
    }
}