<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Formation;

use WorldGenerator\Generator\Mass\MassCell;

final class FormationSnapshot{

    private int $density;

    /** @var array<int,array{rock:string,amount:float,compression:float}> */
    private array $layers;

    public function __construct(int $density,array $layers){
        $this->density=$density;
        $this->layers=$layers;
    }

    // Создаёт независимый снимок текущего материала клетки
    public static function fromCell(MassCell $cell): self{
        $layers=[];
        foreach($cell->getLayers() as $layer){
            $layers[]=[
                "rock"=>$layer->getRock()->getId(),
                "amount"=>$layer->getAmount(),
                "compression"=>$layer->getCompression()
            ];
        }
        return new self(
            $cell->getDensity(),
            $layers
        );
    }

    public function getDensity(): int{
        return $this->density;
    }

    /** @return array<int,array{rock:string,amount:float,compression:float}> */
    public function getLayers(): array{
        return $this->layers;
    }
}