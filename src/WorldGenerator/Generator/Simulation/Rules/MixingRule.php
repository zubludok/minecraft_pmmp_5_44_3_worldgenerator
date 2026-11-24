<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class MixingRule implements RockTransformationProcess{

    public function apply(MassCell $cell): bool{
        $layers = $cell->getLayers();
        $changed = false;
        $count = count($layers);
        for($i = 0; $i < $count; $i++){
            $first = $layers[$i] ?? null;
            if(!$first instanceof RockLayer){
                continue;
            }
            if(!$first->isSolid() || $first->isEmpty() || $first->isWater()){
                continue;
            }
            $firstRock = $first->getRock();
            // Смешивание здесь допускается только для рыхлого материала.
            if($firstRock->getCohesion() >= 0.5){
                continue;
            }

            for($j = $i + 1; $j < $count; $j++){
                $second = $layers[$j] ?? null;
                if(!$second instanceof RockLayer){
                    continue;
                }
                if(!$second->isSolid() || $second->isEmpty() || $second->isWater()){
                    continue;
                }
                if($second->getRock() !== $firstRock){
                    continue;
                }
                $amount = $second->getAmount();
                if($amount <= 0.0){
                    continue;
                }
                // Сохраняем общий объём материала.
                $first->addAmount($amount);
                // Усредняем состояние фракции.
                $total = $first->getAmount();
                if($total > 0.0){
					$fraction = ($first->getFraction() * ($total - $amount) + $second->getFraction() * $amount) / $total;
                    $first->setFraction($fraction);
                }
                // Вторая порция полностью вошла в первую.
                $second->setAmount(0.0);
                $changed = true;
            }
        }
        if($changed){
            $cell->removeEmptyLayers();
        }
        return $changed;
    }
}