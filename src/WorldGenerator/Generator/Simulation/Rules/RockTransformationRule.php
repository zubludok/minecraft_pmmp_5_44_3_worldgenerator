<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Rules;

use WorldGenerator\Generator\Mass\MassCell;

final class RockTransformationRule{
	/*	├── RockTransformationRule       ← дирижёр
		│
		├── FractureRule                 ← разрушение твёрдого материала
		├── MaterialSeparationRule       ← разделение рыхлого/фрагментированного материала
		├── CompactionRule               ← уплотнение
		├── MixingRule                   ← смешивание материалов
		├── CementationRule              ← скрепление / образование более связного материала
		├── WaterRule                    ← воздействие воды
		├── LayeringRule                 ← порядок и формирование слоёв
		└── CellFinalizationRule         ← приведение MassCell в валидное состояние */
    // @var RockTransformationProcess[]
    private array $processes = [];

    // @param RockTransformationProcess[] $processes
    public function __construct(array $processes = []){
        foreach($processes as $process){
            $this->addProcess($process);
        }
    }

    public function addProcess(RockTransformationProcess $process): void{$this->processes[] = $process;}
    
	public function apply(MassCell $cell): bool{
        $changed = false;
        foreach($this->processes as $process){
            if($process->apply($cell)){
                $changed = true;
            }
        }
        return $changed;
    }
}