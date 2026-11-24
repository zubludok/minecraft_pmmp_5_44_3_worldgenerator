<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use WorldGenerator\Generator\Geology\RockType;

final class ClimateLandscapeCell{

    private int $x;
    private int $z;

    private float $surfaceHeight;
    private bool $water;
    private bool $mountain;

    private float $relativeHeight;
    private float $heightDifference;

    private ?RockType $surfaceRock;

    public function __construct(
        int $x,
        int $z,
        float $surfaceHeight,
        bool $water,
        ?RockType $surfaceRock
    ){
        $this->x=$x;
        $this->z=$z;
        $this->surfaceHeight=$surfaceHeight;
        $this->water=$water;
        $this->mountain=false;
        $this->relativeHeight=0.0;
        $this->heightDifference=0.0;
        $this->surfaceRock=$surfaceRock;
    }

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getSurfaceHeight(): float{return $this->surfaceHeight;}
    public function isWater(): bool{return $this->water;}
    public function isLand(): bool{return !$this->water;}
    public function isMountain(): bool{return $this->mountain;}
    public function setMountain(bool $mountain): void{$this->mountain=$mountain;}
    public function getRelativeHeight(): float{return $this->relativeHeight;}
    public function setRelativeHeight(float $height): void{$this->relativeHeight=$height;}
    public function getHeightDifference(): float{return $this->heightDifference;}
    public function setHeightDifference(float $difference): void{$this->heightDifference=$difference;}
    public function getSurfaceRock(): ?RockType{return $this->surfaceRock;}
    public function getSurfaceRockId(): ?string{return $this->surfaceRock?->getId();}

    public function getClimateType(): string{
        if($this->isWater()){
			return "W";
        }
        if($this->isMountain()){
            return "M";
        }
        return "L";
    }
}