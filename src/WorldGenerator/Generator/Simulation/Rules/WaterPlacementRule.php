<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation\Rules;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Geology\RockType;
use WorldGenerator\Generator\Simulation\WaterTransfer;

final class WaterPlacementRule{

	public function apply(MassCell $target,WaterTransfer $transfer): bool{
		if($transfer->isEmpty()){
			return false;
		}
		$waterAmount=$transfer->getAmount();
		if($waterAmount<=0.0){
			return false;
		}
		$layers=$target->getLayers();
		// Сначала вода заполняет EMPTY, начиная от waterTop и двигаясь вниз.
		$layers=$this->fillEmpty($layers,$waterAmount,$transfer->getTop());
		// Затем вода насыщает LOOSE ROCK.
		$layers=$this->saturateLooseRock($layers,$waterAmount,$transfer->getTop());
		/* Если свободная вода осталась —
		 * вставляем её на waterBottom и сдвигаем
		 * всё находящееся выше. */
		if($waterAmount>0.0){
			$waterRock=$this->getWaterRock($transfer);
			if($waterRock!==null){
				$water=new RockLayer($waterRock,$waterAmount);
				$layers=$this->insertAtHeight($layers,$water,$transfer->getBottom());
			}
		}

		// Специальное правило: EMPTY снизу + WATER сверху -> поменять местами.
		$layers=$this->swapWaterDownward($layers);
		$target->setLayers($layers);
		$target->getFormationState()->addRedistribution();
		return true;
	}

	/* @param RockLayer[] $layers
	 * @return RockLayer[] */
	private function fillEmpty(array $layers,float &$waterAmount,float $waterTop): array{
		$positions=$this->getPositions($layers);
		// Идём сверху вниз.
		for($i=count($layers)-1;$i>=0;$i--){
			if($waterAmount<=0.0){
				break;
			}
			$layer=$layers[$i];
			if(!$layer instanceof RockLayer || !$layer->isEmpty()){
				continue;
			}
			$position=$positions[$i];
			if($position["bottom"]>$waterTop){
				continue;
			}
			$available=$layer->getAmount();
			if($available<=0.0){
				continue;
			}
			$take=min($available,$waterAmount);
			$waterRock=$this->findWaterRock($layers);
			if($waterRock===null){
				continue;
			}
			$new=[];
			for($j=0;$j<count($layers);$j++){
				if($j!==$i){
					$new[]=$layers[$j];
					continue;
				}
				$remaining=$available-$take;
				if($remaining>0.0){
					$new[]=$this->copyLayer($layer,$remaining);
				}
				$new[]=new RockLayer($waterRock,$take);
			}
			$layers=$new;
			$waterAmount-=$take;
			$positions=$this->getPositions($layers);
		}
		return $layers;
	}

	/* @param RockLayer[] $layers
	 * @return RockLayer[] */
	private function saturateLooseRock(array $layers,float &$waterAmount,float $waterTop): array{
		$positions=$this->getPositions($layers);
		for($i=count($layers)-1;$i>=0;$i--){
			if($waterAmount<=0.0){
				break;
			}
			$layer=$layers[$i];
			if(!$layer instanceof RockLayer){
				continue;
			}
			if(!$this->isLooseRock($layer)){
				continue;
			}
			if($layer->isWaterBearing()){
				continue;
			}
			if($positions[$i]["bottom"]>$waterTop){
				continue;
			}
			$take=min(1.0,$layer->getAmount(),$waterAmount);
			if($take<=0.0){
				continue;
			}
			$saturated=$this->copyLayer($layer,$take);
			$saturated->setWaterContent($take);
			$remaining=$layer->getAmount()-$take;
			$new=[];
			for($j=0;$j<count($layers);$j++){
				if($j!==$i){
					$new[]=$layers[$j];
					continue;
				}

				if($remaining>0.0){
					$new[]=$this->copyLayer($layer,$remaining);
				}
				$new[]=$saturated;
			}
			$layers=$new;
			$waterAmount-=$take;
			$positions=$this->getPositions($layers);
		}
		return $layers;
	}

	private function isLooseRock(RockLayer $layer): bool{
		return
			$layer->isSolid() &&
			!$layer->isWater() &&
			!$layer->isEmpty() &&
			$layer->getRock()->getCohesion()<0.5;
	}

	/* @param RockLayer[] $layers
	 * @return RockLayer[] */
	private function insertAtHeight(array $layers,RockLayer $insert,float $height): array{
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
					$result[]=$this->copyLayer($layer,$offset);
					$result[]=$insert;
					$result[]=$this->copyLayer($layer,$layer->getAmount()-$offset);
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

	/* @param RockLayer[] $layers
	 * @return RockLayer[] */
	private function swapWaterDownward(array $layers): array{
		do{
			$changed=false;
			for($i=0;$i<count($layers)-1;$i++){
				$lower=$layers[$i];
				$upper=$layers[$i+1];
				if(
					$lower instanceof RockLayer &&
					$upper instanceof RockLayer &&
					$lower->isEmpty() &&
					$upper->isWater()
				){
					$layers[$i]=$upper;
					$layers[$i+1]=$lower;
					$changed=true;
				}
			}
		}while($changed);
		return $layers;
	}

	private function findWaterRock(array $layers): ?RockType{
		foreach($layers as $layer){
			if($layer instanceof RockLayer && $layer->isWater()){
				return $layer->getRock();
			}
		}
		return null;
	}

	private function getWaterRock(WaterTransfer $transfer): ?RockType{
		foreach($transfer->getLayers() as $layer){
			if($layer instanceof RockLayer && $layer->isWater()){
				return $layer->getRock();
			}
		}
		return null;
	}

	/* @param RockLayer[] $layers
	 * @return array<int,array{bottom:float,top:float}> */
	private function getPositions(array $layers): array{
		$result=[];
		$height=0.0;
		foreach($layers as $index=>$layer){
			$amount=$layer instanceof RockLayer ? $layer->getAmount() : 0.0;
			$result[$index]=["bottom"=>$height,"top"=>$height+$amount];
			$height+=$amount;
		}
		return $result;
	}

	private function copyLayer(RockLayer $source,float $amount): RockLayer{
		$layer=new RockLayer($source->getRock(),$amount);
		$layer->setCompression($source->getCompression());
		$layer->setStress($source->getStress());
		$layer->setFraction($source->getFraction());
		$layer->setWaterImpact($source->getWaterImpact());
		$layer->setWaterContent(min($amount,$source->getWaterContent()));
		return $layer;
	}
}