<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation\Rules;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Geology\RockLayer;

final class WaterErosionRule{

	public function apply(MassCell $cell): bool{
		$layers=$cell->getLayers();
		if(count($layers)<2){
			return false;
		}
		$changed=false;
		for($i=count($layers)-1;$i>0;$i--){
			$water=$layers[$i];
			if(!$water instanceof RockLayer || !$water->isWater()
			){
				continue;
			}
			$rockIndex=$i-1;
			$rock=$layers[$rockIndex];
			if(
				!$rock instanceof RockLayer ||
				!$rock->isSolid() ||
				$rock->isWater() ||
				$rock->isEmpty() ||
				$rock->isWaterBearing()
			){
				continue;
			}
			$amount=$rock->getAmount();
			if($amount<=0.0){
				continue;
			}
			$take=min(1.0,$amount);
			$ratio=$amount>0.0 ? $take/$amount : 0.0;
			$eroded=$this->copyLayer($rock,$take);
			$impact=min(1.0,$water->getAmount()*(1.0-$rock->getRock()->getHardness())*0.01);
			$eroded->addWaterImpact($impact);
			$remaining=$amount-$take;
			$new=[];
			for($j=0;$j<count($layers);$j++){
				if($j!==$rockIndex){
					$new[]=$layers[$j];
					continue;
				}
				if($remaining>0.0){
					$remainingLayer=$this->copyLayer($rock,$remaining);
					// Stress распределяется между остатком и отделившимся блоком.
					$remainingLayer->setStress($rock->getStress()*(1.0-$ratio));
					$new[]=$remainingLayer;
				}
				$new[]=$eroded;
			}
			$layers=$new;
			$changed=true;
			break;
		}

		if($changed){
			$cell->setLayers($layers);
		}
		return $changed;
	}

	private function copyLayer(RockLayer $source,float $amount): RockLayer{
		$layer=new RockLayer($source->getRock(),$amount);
		$layer->setCompression($source->getCompression());
		$layer->setStress($source->getStress());
		$layer->setFraction($source->getFraction());
		$layer->setWaterImpact($source->getWaterImpact());
		$layer->setWaterContent(0.0);
		return $layer;
	}
}