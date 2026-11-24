<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation\Rules;

use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Simulation\WaterTransfer;

final class WaterExtractionRule{

	private float $strongRockThreshold;

	public function __construct(float $strongRockThreshold=0.75){
		$this->strongRockThreshold=$strongRockThreshold;
	}

	public function apply(MassCell $source,WaterTransfer $transfer): bool{
		if($transfer->isEmpty()){
			return false;
		}
		$layers=$source->getLayers();
		foreach($layers as $layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			// При удалении свободной воды насыщенная порода
			// возвращается в обычное состояние.
			if($layer->isWaterBearing()){
				$layer->setWaterContent(0.0);
			}
		}
		$waterBottom=$transfer->getBottom();
		$waterTop=$transfer->getTop();
		
		/* Ищем материал непосредственно над водой
		 * в снимке ДО удаления воды. */
		$upperLayer=null;
		$height=0.0;
		foreach($transfer->getSourceLayers() as $layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			$nextHeight=$height+$layer->getAmount();
			if($waterTop >= $height && $waterTop < $nextHeight){
				$upperLayer=$layer;
				break;
			}
			$height=$nextHeight;
		}

		/* Если вода находилась под прочной породой,
		 * занимаемый ею объём превращается в EMPTY. */
		if(
			$upperLayer instanceof RockLayer &&
			$upperLayer->isSolid() &&
			!$upperLayer->isWater() &&
			!$upperLayer->isEmpty() &&
			$upperLayer->getRock()->getStrength() > $this->strongRockThreshold
		){
			$emptyRock=$source->getEmptyRock();
			if($emptyRock!==null){
				$empty=new RockLayer($emptyRock,$transfer->getAmount());
				$layers=$this->insertLayerAtHeight($layers,$empty,$waterBottom);
			}
		}
		$source->setLayers($layers);
		$source->getFormationState()->addRedistribution();
		return true;
	}

	/* @param RockLayer[] $layers
	 * @return RockLayer[] */
	private function insertLayerAtHeight(array $layers,RockLayer $insert,float $height): array{
		$result=[];
		$current=0.0;
		$inserted=false;
		foreach($layers as $layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			$next=$current+$layer->getAmount();
			if(!$inserted && $height<=$next){
				$offset=$height-$current;
				if($offset<=0.0){
					$result[]=$insert;
					$result[]=$layer;
				}elseif($offset>=$layer->getAmount()){
					$result[]=$layer;
					$result[]=$insert;
				}else{
					$lower=$this->copyLayer($layer,$offset);
					$upper=$this->copyLayer($layer,$layer->getAmount()-$offset);
					$result[]=$lower;
					$result[]=$insert;
					$result[]=$upper;
				}
				$inserted=true;
			}else{
				$result[]=$layer;
			}
			$current=$next;
		}
		if(!$inserted){
			$result[]=$insert;
		}
		return $result;
	}

	private function copyLayer(RockLayer $source,float $amount): RockLayer{
		$sourceAmount=$source->getAmount();
		$ratio=$sourceAmount>0.0 ? $amount/$sourceAmount : 0.0;
		$layer=new RockLayer($source->getRock(),$amount);
		$layer->setCompression($source->getCompression());
		$layer->setStress($source->getStress()*$ratio);
		$layer->setFraction($source->getFraction());
		$layer->setWaterImpact($source->getWaterImpact());
		$layer->setWaterContent($source->getWaterContent()*$ratio);
		return $layer;
	}
}