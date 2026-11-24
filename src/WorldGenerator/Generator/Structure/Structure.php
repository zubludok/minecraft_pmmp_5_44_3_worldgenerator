<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Structure;

final class Structure{

    private int $id;
    private string $type;

    /* Каждая клетка структуры хранит связи с соседними клетками структуры.
     * Ключ — координата клетки.
     * @var array<string,array{x:int,z:int,neighbours:array<int,array{0:int,1:int}>}> */
    private array $cells=[];

    public function __construct(int $id,string $type){
        $this->id=$id;
        $this->type=$type;
    }

    public function getId(): int{return $this->id;}
    public function getType(): string{return $this->type;}
    public function addCell(int $x,int $z): void{
        $key=$x.":".$z;
        if(isset($this->cells[$key])){
            return;
        }
        $this->cells[$key]=["x"=>$x,"z"=>$z,"neighbours"=>[]];
        $this->updateNeighbours($x,$z);
    }

    // @return array<int,array{x:int,z:int,neighbours:array<int,array{0:int,1:int}>}>
    public function getCells(): array{return array_values($this->cells);}

    // @return array<int,array{0:int,1:int}>
    public function getNeighbours(int $x,int $z): array{
        $key=$x.":".$z;
        return $this->cells[$key]["neighbours"] ?? [];
    }

    private function updateNeighbours(int $x,int $z): void{
        $directions=[
            [-1,-1],[0,-1],	[1,-1],
            [-1,0],         [1,0],
            [-1,1],	[0,1],	[1,1],
        ];
        $key=$x.":".$z;
        foreach($directions as [$dx,$dz]){
            $neighbourKey=($x+$dx).":".($z+$dz);
            if(!isset($this->cells[$neighbourKey])){
                continue;
            }
            $this->cells[$key]["neighbours"][]=[$dx,$dz];
            $this->cells[$neighbourKey]["neighbours"][]=[-$dx,-$dz];
        }
    }
}