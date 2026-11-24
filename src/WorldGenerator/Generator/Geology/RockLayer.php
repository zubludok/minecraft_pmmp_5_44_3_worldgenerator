<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Geology;

final class RockLayer{
/* 	RockLayer В каком состоянии сейчас находится конкретная порция этого материала?
	│
	├── rock
	├── amount		- количество материала
	├── compression	- степень уплотнения
	├── stress		- накопленное напряжение
	├── fraction	- крупность материала
	├── waterImpact	- накопленное воздействие воды
	└── waterContent- водонесущий слой.*/
    private RockType $rock;

    // Количество материала.
    private float $amount;

    /* Степень уплотнения материала.
     * 0.0 = не уплотнён
     * 1.0 = максимально уплотнён*/
    private float $compression = 0.0;

    // Накопленное напряжение именно этой порции материала.
    private float $stress = 0.0;

    /* Размер фракции.
     * 0.0 = очень мелкая
     * 1.0 = очень крупная */
    private float $fraction = 0.5;

    /* Накопленное воздействие воды:
     * размыв, сглаживание, разрушение связей.*/
    private float $waterImpact = 0.0;

    /* Количество воды, удерживаемой материалом.
     * Это не свободный слой WATER.
     * Это вода внутри/между частицами материала. */
    private float $waterContent = 0.0;
	
    public function __construct(
        RockType $rock,
        float $amount
    ){
        $this->rock = $rock;
        $this->amount = max(0.0, $amount);
    }

    public function getRock(): RockType{return $this->rock;}
    public function setRock(RockType $rock): void{$this->rock = $rock;}
    public function getAmount(): float{return $this->amount;}
    public function setAmount(float $amount): void{$this->amount = max(0.0, $amount);}
    public function addAmount(float $amount): void{$this->amount = max(0.0, $this->amount + $amount);}
    public function getCompression(): float{return $this->compression;}
	public function setCompression(float $compression): void{$this->compression = max(0.0, min(1.0, $compression));}
    public function addCompression(float $value): void{$this->setCompression($this->compression + $value);}
    public function getStress(): float{return $this->stress;}
    public function setStress(float $stress): void{$this->stress = max(0.0, $stress);}
    public function addStress(float $value): void{$this->stress = max(0.0, $this->stress + $value);}
    public function getFraction(): float{return $this->fraction;}
    public function setFraction(float $fraction): void{$this->fraction = max(0.0, min(1.0, $fraction));}
    public function getWaterImpact(): float{return $this->waterImpact;}
    public function setWaterImpact(float $waterImpact): void{$this->waterImpact = max(0.0, $waterImpact);}
    public function addWaterImpact(float $value): void{$this->waterImpact = max(0.0, $this->waterImpact + $value);}
	public function getWaterContent(): float{return $this->waterContent;}
    public function setWaterContent(float $waterContent): void{$this->waterContent = max(0.0, $waterContent);}
    public function addWaterContent(float $amount): void{$this->waterContent = max(0.0,$this->waterContent + $amount);}
    public function getWeight(): float{return $this->amount * $this->rock->getDensity();}
    public function isEmpty(): bool{return $this->rock->isEmpty();}
    public function isWater(): bool{return $this->rock->isWater();}
    public function isSolid(): bool{return $this->rock->isSolid();}
    // Есть ли вода, связанная с материалом.
    public function isWaterBearing(): bool{return $this->waterContent > 0.0;}
}