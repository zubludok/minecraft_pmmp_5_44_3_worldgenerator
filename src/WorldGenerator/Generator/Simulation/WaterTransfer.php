<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Simulation;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Geology\RockLayer;

final class WaterTransfer{

	// @var RockLayer[]
	private array $layers;

	private float $amount;
	private float $bottom;
	private float $top;
	
	// @var RockLayer[]
	private array $sourceLayers;

	// @param RockLayer[] $layers
	private function __construct(
		array $layers,
		float $amount,
		float $bottom,
		float $top,
		array $sourceLayers
	){
		$this->layers=$layers;
		$this->amount=$amount;
		$this->bottom=$bottom;
		$this->top=$top;
		$this->sourceLayers=$sourceLayers;
	}

	// Создаётся ПОСЛЕ extractSolidMass(), но ДО extractWater(). @param RockLayer[] $layers
	public static function create(array $layers): self{
		$water=[];
		$sourceLayers=[];
		$height=0.0;
		$bottom=0.0;
		$top=0.0;
		$found=false;
		foreach($layers as $layer){
			if(!$layer instanceof RockLayer){
				continue;
			}
			/* ВАЖНО:
			 * сохраняем копию состояния слоя, потому что
			 * extractWater()/extractSolidMass() меняют оригиналы. */
			$copy=new RockLayer($layer->getRock(),$layer->getAmount());
			$copy->setCompression($layer->getCompression());
			$copy->setStress($layer->getStress());
			$copy->setFraction($layer->getFraction());
			$copy->setWaterImpact($layer->getWaterImpact());
			$copy->setWaterContent($layer->getWaterContent());
			$sourceLayers[]=$copy;
			if($layer->isWater()){
				if(!$found){$bottom=$height;$found=true;}
				$waterCopy=new RockLayer($layer->getRock(),$layer->getAmount());
				$waterCopy->setCompression($layer->getCompression());
				$waterCopy->setStress($layer->getStress());
				$waterCopy->setFraction($layer->getFraction());
				$waterCopy->setWaterImpact($layer->getWaterImpact());
				$waterCopy->setWaterContent($layer->getWaterContent());
				$water[]=$waterCopy;
				$top=$height+$layer->getAmount();
			}
			$height+=$layer->getAmount();
		}
		$amount=0.0;
		foreach($water as $layer){$amount+=$layer->getAmount();}
		return new self($water,$amount,$bottom,$top,$sourceLayers);
	}

	// @return RockLayer[]
	public function getLayers(): array{return $this->layers;}
	public function getAmount(): float{return $this->amount;}
	public function getBottom(): float{return $this->bottom;}
	public function getTop(): float{return $this->top;}
	public function isEmpty(): bool{return $this->amount<=0.0 || $this->layers===[];}
	// @return RockLayer[]
	public function getSourceLayers(): array{return $this->sourceLayers;}
}