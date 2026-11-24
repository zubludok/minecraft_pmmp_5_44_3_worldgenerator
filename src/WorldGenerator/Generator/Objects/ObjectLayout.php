<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Objects;

final class ObjectLayout{

    private int $columns;
    private int $rows;

    // @var ObjectPlacement[]
    private array $objects=[];

    // @var array<string,ObjectPlacement>
    private array $grid=[];

    public function __construct(
        int $columns,
        int $rows
    ){
        $this->columns=max(1,$columns);
        $this->rows=max(1,$rows);
    }

    public function addObject(ObjectPlacement $object): void{
        $this->objects[$object->getId()]=$object;
        $key=$object->getGridX().":".$object->getGridZ();
        $this->grid[$key]=$object;
    }

    public function getObject(int $id): ?ObjectPlacement{return $this->objects[$id]??null;}
    public function getAt(int $gridX,int $gridZ): ?ObjectPlacement{
        $key=$gridX.":".$gridZ;
        return $this->grid[$key]??null;
    }
	
    // @return ObjectPlacement[]
    public function getObjects(): array{return $this->objects;}
    public function getColumns(): int{return $this->columns;}
    public function getRows(): int{return $this->rows;}
    public function getCount(): int{return count($this->objects);}
    public function hasObject(int $gridX,int $gridZ): bool{return $this->getAt($gridX,$gridZ)!==null;}
}