<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Geology;

final class FormationState{
/* 	FormationState
	│
	├── stress
	├── deformation
	├── collisionCount
	├── directionChanges
	├── redistributionCount
	└── origin */
    private float $stress=0.0;
    private float $deformation=0.0;
    private int $collisionCount=0;
    private int $directionChanges=0;
    private int $redistributionCount=0;
    private string $origin="PRIMARY";

    public function getStress(): float{return $this->stress;}
    public function setStress(float $stress): void{$this->stress=max(0.0,$stress);}

    public function getDeformation(): float{return $this->deformation;}
    public function setDeformation(float $deformation): void{$this->deformation=max(0.0,min(1.0,$deformation));}

    public function getCollisionCount(): int{return $this->collisionCount;}
    public function addCollision(): void{$this->collisionCount++;}

    public function getDirectionChanges(): int{return $this->directionChanges;}
    public function addDirectionChange(): void{$this->directionChanges++;}

    public function getRedistributionCount(): int{return $this->redistributionCount;}
    public function addRedistribution(): void{$this->redistributionCount++;}

    public function getOrigin(): string{return $this->origin;}
    public function setOrigin(string $origin): void{$this->origin=$origin;}
	// Накапливает внутреннее напряжение клетки
	public function addStress(float $value): void{$this->stress=max(0.0,$this->stress+$value);}
	
	public function setCollisionCount(int $count): void{$this->collisionCount=$count;}
	public function setDirectionChanges(int $count): void{$this->directionChanges=$count;}
	public function setRedistributionCount(int $count): void{$this->redistributionCount=$count;}
}