<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation\Rules;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Geology\RockLayer;

final class CellFinalizationRule{

	public function apply(MassCell $cell): bool{
		$layers=$cell->getLayers();
		if($layers===[]){
			return false;
		}
		$result=[];
		$changed=false;
		foreach($layers as $layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			if($layer->getAmount()<=0.0){
				$changed=true;
				continue;
			}
			$previous=$result[count($result)-1]??null;
			if($previous instanceof RockLayer && $this->canMerge($previous,$layer)){
				$previous->addAmount($layer->getAmount());
				$changed=true;
				continue;
			}
			$result[]=$layer;
		}

		// Остаточный EMPTY постепенно исчезает.
		foreach($result as $index=>$layer){
			if(!$layer instanceof RockLayer || !$layer->isEmpty()){
				continue;
			}
			if($layer->getAmount()>1.0){
				$layer->setAmount($layer->getAmount()-1.0);
				$changed=true;
				continue;
			}
			if($layer->getAmount()<=1.0){
				unset($result[$index]);
				$changed=true;
			}
		}
		$result=array_values($result);
		if($changed){
			$cell->setLayers($result);
		}
		return $changed;
	}

	private function canMerge(RockLayer $a,RockLayer $b): bool{
		if($a->isEmpty() && $b->isEmpty()){
			return true;
		}
		if($a->isWater() && $b->isWater()){
			return true;
		}
		return false;
	}
}