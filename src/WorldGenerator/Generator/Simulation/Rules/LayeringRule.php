<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;

final class LayeringRule implements RockTransformationProcess{
	
    public function apply(MassCell $cell): bool{
        $changed = false;
        $layers = $cell->getLayers();
        for($i = 0; $i < count($layers) - 1; $i++){
            $current = $layers[$i] ?? null;
            $next = $layers[$i + 1] ?? null;
            if(!$current instanceof RockLayer || !$next instanceof RockLayer){
                continue;
            }
            if($current->isEmpty() || $next->isEmpty()){
                continue;
            }
            if($current->isWater() || $next->isWater()){
                continue;
            }
            if($current->getRock() !== $next->getRock()){
                continue;
            }
            $amount = $next->getAmount();
            if($amount <= 0.0){
                continue;
            }
            $currentAmount = $current->getAmount();
            $totalAmount = $currentAmount + $amount;
            if($totalAmount > 0.0){
                $fraction = ($current->getFraction() * $currentAmount + $next->getFraction() * $amount) / $totalAmount;
                $compression = ($current->getCompression() * $currentAmount + $next->getCompression() * $amount) / $totalAmount;
                $stress = ($current->getStress() * $currentAmount + $next->getStress() * $amount) / $totalAmount;
                $waterImpact = ($current->getWaterImpact() * $currentAmount + $next->getWaterImpact() * $amount) / $totalAmount;
                $waterContent = ($current->getWaterContent() * $currentAmount + $next->getWaterContent() * $amount) / $totalAmount;
                $current->setFraction($fraction);
                $current->setCompression($compression);
                $current->setStress($stress);
                $current->setWaterImpact($waterImpact);
                $current->setWaterContent($waterContent);
            }
            $current->addAmount($amount);
            $next->setAmount(0.0);
            $changed = true;
        }
        if($changed){
            $cell->removeEmptyLayers();
        }
        return $changed;
    }
}