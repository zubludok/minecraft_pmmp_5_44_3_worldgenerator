<?php

declare(strict_types=1);

namespace WorldGenerator\Generator;

use WorldGenerator\Config\Preset;

final class GenerationContext{	// параметры генерации

    private Preset $preset;
    private string $worldName;
    private int $seed;
    private int $sizeX;
    private int $sizeZ;
	private int $sectorCountX;
	private int $sectorCountZ;
	private int $islandCount;
	private int $worldHeight;
	private int $waterLevel;
	private int $landPercent;
	private int $aquiferLevel;
	private string $generationType;
	private string $borderType;
	private bool $ringWorld;

    public function __construct(Preset $preset){
        $this->preset=$preset;
        $this->worldName=$preset->getWorld()->getName();
        $this->seed=$preset->getWorld()->getSeed();
		$this->sizeX=$preset->getWorld()->getBlockSizeX();
		$this->sizeZ=$preset->getWorld()->getBlockSizeZ();
		
		$this->sectorCountX=$preset->getWorld()->getSectorCountX();
		$this->sectorCountZ=$preset->getWorld()->getSectorCountZ();
		
		$this->islandCount=$preset->getWorld()->getIslandCount();
		$this->worldHeight = $preset->getWorld()->getWorldHeight();
		$this->waterLevel = $preset->getWorld()->getWaterLevel();
		$this->aquiferLevel=$preset->getAquiferLevel();
		$this->landPercent = $preset->getWorld()->getLandPercent();
		$this->generationType = $preset->getWorld()->getGenerationType();
		$this->borderType = $preset->getWorld()->getBorderType();
		$this->ringWorld = $preset->getWorld()->isRingWorld();
    }

    public function getPreset(): Preset{return $this->preset;}
    public function getWorldName(): string{return $this->worldName;}
    public function getSeed(): int{return $this->seed;}
    public function getSizeX(): int{return $this->sizeX;}
    public function getSizeZ(): int{return $this->sizeZ;}
	public function getWorldHeight(): int{return $this->worldHeight;}
	public function getWaterLevel(): int{return $this->waterLevel;}
	public function getAquiferLevel(): int{return $this->aquiferLevel;}
	public function getLandPercent(): int{return $this->landPercent;}
	public function getGenerationType(): string{return $this->generationType;}
	public function getBorderType(): string{return $this->borderType;}
	public function isRingWorld(): bool{return $this->ringWorld;}
	public function getIslandCount(): int{return $this->islandCount;}
	public function getSectorCountX(): int{return $this->sectorCountX;}
	public function getSectorCountZ(): int{return $this->sectorCountZ;}
}