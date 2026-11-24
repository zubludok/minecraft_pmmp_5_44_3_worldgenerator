<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Geology;

final class RockType{
/* 	RockType
	│
	├── id
	├── category
	│
	├── density			- плотность материала
	├── strength		- сопротивление разрушению
	├── hardness		- сопротивление изменению поверхности/истиранию
	├── compressibility	- насколько легко материал уплотняется
	├── brittleness		- насколько легко при напряжении образуются трещины/обломки
	├── cohesion		- насколько частицы стремятся держаться вместе
	│
	├── initial
	├── generated
	└── solid */

    private string $id;
    private string $category;
    // Плотность материала.
    private float $density;
    // Сопротивление разрушению.
    private float $strength;
    // Сопротивление истиранию и изменению поверхности.
    private float $hardness;
    // Способность материала уплотняться под нагрузкой.
    private float $compressibility;
    // Склонность к хрупкому разрушению.
    private float $brittleness;
    // Способность частиц удерживаться вместе.
    private float $cohesion;
    // Материал присутствует в исходной генерации.
    private bool $initial;
    // Материал может быть результатом геологических процессов.
    private bool $generated;
    // Материал является твёрдым.
    private bool $solid;


    public function __construct(
        string $id,
        string $category,
        float $density,
        float $strength,
        float $hardness,
        float $compressibility,
        float $brittleness,
        float $cohesion,
        bool $initial,
        bool $generated,
        bool $solid = true
    ){
        $this->id = $id;
        $this->category = $category;

        $this->density = max(0.0, $density);
        $this->strength = max(0.0, min(1.0, $strength));
        $this->hardness = max(0.0, min(1.0, $hardness));
        $this->compressibility = max(0.0, min(1.0, $compressibility));
        $this->brittleness = max(0.0, min(1.0, $brittleness));
        $this->cohesion = max(0.0, min(1.0, $cohesion));

        $this->initial = $initial;
        $this->generated = $generated;
        $this->solid = $solid;
    }


    public function getId(): string{return $this->id;}
    public function getCategory(): string{return $this->category;}
    public function getDensity(): float{return $this->density;}
    public function getStrength(): float{return $this->strength;}
    public function getHardness(): float{return $this->hardness;}
    public function getCompressibility(): float{return $this->compressibility;}
    public function getBrittleness(): float{return $this->brittleness;}
    public function getCohesion(): float{return $this->cohesion;}
    public function isInitial(): bool{return $this->initial;}
    public function isGenerated(): bool{return $this->generated;}
    public function isSolid(): bool{return $this->solid;}
    public function isWater(): bool{return $this->id === "WATER";}
    public function isEmpty(): bool{return $this->id === "EMPTY";}
}