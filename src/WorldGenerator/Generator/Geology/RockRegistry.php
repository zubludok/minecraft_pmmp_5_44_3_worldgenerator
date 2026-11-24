<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Geology;

final class RockRegistry{
/* 	id			идентификатор материала
	category	общая группа материала
	density		плотность материала
	strength	сопротивление разрушению/деформации
	hardness	сопротивление механическому воздействию
	compressibility	способность уплотняться
	brittleness	склонность к хрупкому разрушению
	cohesion	способность частиц удерживаться вместе
	initial		может появиться при первоначальной генерации
	generated	может образоваться в процессе
	solid		твёрдый материал или нет */

    // @var array<string,RockType>
    private array $rocks=[];

    public function __construct(){

        // Первичные материалы
        $this->register("STONE","IGNEOUS",2.5,0.83,0.83,0.15,0.65,0.85,true,false);
        $this->register("GRANITE","IGNEOUS",3.0,0.95,0.95,0.08,0.80,0.90,true,false);
        $this->register("DIORITE","IGNEOUS",2.8,0.90,0.90,0.10,0.70,0.88,true,false);
        $this->register("ANDESITE","IGNEOUS",2.7,0.87,0.87,0.12,0.68,0.86,true,false);
        $this->register("BASALT","VOLCANIC",3.1,0.98,0.98,0.07,0.90,0.92,true,true);
        $this->register("TUFF","VOLCANIC",2.2,0.60,0.55,0.35,0.55,0.55,true,true);
        $this->register("DEEPSLATE","IGNEOUS",3.2,1.00,1.00,0.05,0.85,0.95,true,false);
        $this->register("CALCITE","SEDIMENTARY",2.0,0.43,0.40,0.30,0.45,0.60,true,true);
        $this->register("SAND","SEDIMENT",1.0,0.17,0.10,0.80,0.15,0.10,true,true);
        $this->register("GRAVEL","SEDIMENT",1.4,0.27,0.20,0.65,0.25,0.20,true,true);
        $this->register("CLAY","SEDIMENT",1.2,0.23,0.13,0.75,0.20,0.70,true,true);
        $this->register("LIMESTONE","SEDIMENTARY",2.1,0.53,0.50,0.30,0.40,0.75,true,true);

        // Материалы, создаваемые процессами формирования
        $this->register("DRIPSTONE","SEDIMENTARY",2.2,0.57,0.60,0.25,0.45,0.78,false,true);
        $this->register("MUD","SEDIMENT",1.0,0.13,0.07,0.90,0.10,0.75,false,true);
        $this->register("SANDSTONE","SEDIMENTARY",1.8,0.50,0.47,0.35,0.35,0.70,false,true);
        $this->register("RED_SANDSTONE","SEDIMENTARY",1.8,0.50,0.47,0.35,0.35,0.70,false,true);
        $this->register("MARBLE","METAMORPHIC",2.7,0.90,0.93,0.10,0.55,0.90,false,true);
        $this->register("SHALE","SEDIMENTARY",2.0,0.40,0.33,0.45,0.70,0.65,false,true);
        $this->register("GNEISS","METAMORPHIC",3.0,0.97,0.97,0.07,0.75,0.94,false,true);
        $this->register("QUARTZITE","METAMORPHIC",3.1,1.00,1.00,0.04,0.85,0.96,false,true);

        // Специальные физические слои
        $this->register("EMPTY","VOID",0.0,0.0,0.0,0.0,0.0,0.0,false,false,false);
        $this->register("WATER","FLUID",1.0,0.0,0.0,0.0,0.0,0.0,false,false,false);
    }

    private function register(
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
        bool $solid=true
    ): void{
        $this->rocks[$id]=new RockType(
            $id,
            $category,
            $density,
            $strength,
            $hardness,
            $compressibility,
            $brittleness,
            $cohesion,
            $initial,
            $generated,
            $solid
        );
    }

    public function get(string $id): RockType{return $this->rocks[$id];}
    public function has(string $id): bool{return isset($this->rocks[$id]);}

    // @return RockType[]
    public function getAll(): array{return array_values($this->rocks);}
}