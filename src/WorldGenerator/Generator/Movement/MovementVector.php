<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Movement;

use Random\Randomizer;

final class MovementVector{

    private int $x;
    private int $z;

    public function __construct(int $x,int $z){
        $this->set($x,$z);
	}
	
    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function set(int $x,int $z): void{
        $x=max(-1,min(1,$x));
        $z=max(-1,min(1,$z));
        if($x===0 && $z===0){
            throw new \InvalidArgumentException("Movement vector cannot be zero");
        }
        $this->x=$x;
        $this->z=$z;
    }
    public function isDiagonal(): bool{return $this->x!==0 && $this->z!==0;}
    public function isHorizontal(): bool{return $this->x!==0 && $this->z===0;}
    public function isVertical(): bool{return $this->x===0 && $this->z!==0;}
    public function invertX(): self{return new self(-$this->x,$this->z);}
    public function invertZ(): self{return new self($this->x,-$this->z);}
    public function invert(): self{return new self(-$this->x,-$this->z);}
    // Решает, нужно ли начать вертикальное движение.
    public function next(int $sizeX,int $sizeZ,Randomizer $randomizer): self{
        if($this->z!==0){
            return new self($this->x,$this->z);
        }

        $sizeX=max(1,$sizeX);
        $sizeZ=max(1,$sizeZ);
        $verticalChance=$sizeZ/($sizeX+$sizeZ);
        $roll=$randomizer->getInt(0,1000000)/1000000;
        if($roll>=$verticalChance){
            return new self($this->x,0);
        }
        $z=$randomizer->getInt(0,1)===0?-1:1;
        return new self($this->x,$z);
    }

    // Возвращает координаты следующей клетки.
    public function move(int $x,int $z): array{
        return [$x+$this->x,$z+$this->z];
    }
}