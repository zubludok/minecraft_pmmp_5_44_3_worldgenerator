<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation\Rules;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Geology\RockLayer;

final class WaterSaturationRule{

	public function apply(MassCell $cell): bool{
		$changed=false;
		foreach($cell->getLayers() as $layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			if(!$layer->isWaterBearing()){
				continue;
			}
			// Один насыщенный слой = один объём материала.
			$waterContent=min($layer->getAmount(),$layer->getWaterContent());
			if($waterContent<=0.0){
				$layer->setWaterContent(0.0);
				continue;
			}
			if($layer->getWaterContent()!==$waterContent){
				$layer->setWaterContent($waterContent);
				$changed=true;
			}
		}
		return $changed;
	}
}