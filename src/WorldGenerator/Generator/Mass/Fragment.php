<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Mass;

final class Fragment{

    private int $id;
    private int $directionX=0;
    private int $directionZ=0;

    /** @var MassCell[] */
    private array $cells=[];

    public function __construct(int $id){$this->id=$id;}
    public function getId(): int{return $this->id;}
    public function addCell(MassCell $cell): void{$this->cells[]=$cell; $cell->setFragmentId($this->id);}
    /** @return MassCell[] */
    public function getCells(): array{return $this->cells;}
	public function setCells(array $cells): void{
		$this->cells=array_values($cells);
		foreach($this->cells as $cell){
			$cell->setFragmentId($this->id);
		}
	}
    public function getSize(): int{return count($this->cells);}
    public function setDirection(int $x,int $z): void{$this->directionX=$x;$this->directionZ=$z;}
    public function getDirectionX(): int{return $this->directionX;}
    public function getDirectionZ(): int{return $this->directionZ;}
	public function removeCell(MassCell $cell): void{
		foreach($this->cells as $index=>$fragmentCell){
			if($fragmentCell!==$cell){
				continue;
			}
			unset($this->cells[$index]);
			$this->cells=array_values($this->cells);
			break;
		}
	}
}