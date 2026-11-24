<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Mass;

use Random\Engine\Mt19937;
use Random\Randomizer;
use WorldGenerator\Generator\Objects\ObjectLayout;
use WorldGenerator\Generator\Objects\ObjectPlacement;
use WorldGenerator\Generator\Geology\RockLayer;
use WorldGenerator\Generator\Geology\RockRegistry;
use WorldGenerator\Generator\Geology\RockType;
use WorldGenerator\Generator\Structure\Structure;
use WorldGenerator\Generator\Structure\StructureType;

final class MassGenerator{
/* 	layers[0]     = самый нижний слой
	layers[1]     = выше него
	...
	layers[last]  = самый верхний слой */
	
    private MassMap $map;
    private int $landPercent;
    private int $targetMass;
    private ObjectLayout $objectLayout;
    private Randomizer $randomizer;
    private RockRegistry $rockRegistry;
	
	/** @var array<string,int> */
	private array $structurePercents=[];
	/** @var array<int,Structure> */
	private array $structures=[];
	private int $nextStructureId=1;
    private int $aquiferLevel;

	private const MIN_BASIN_MASS=20;
	private const MAX_BASIN_MASS=30;
	
	// @param array<string,int> $structurePercents
	public function __construct(
		MassMap $map,
		int $landPercent,
		int $aquiferLevel,
		ObjectLayout $objectLayout,
		int $seed,
		RockRegistry $rockRegistry,
		array $structurePercents=[]
	){
		$this->map=$map;
		$this->landPercent=max(0,min(100,$landPercent));
		$this->aquiferLevel=max(1,min(10,$aquiferLevel));
		$this->objectLayout=$objectLayout;
		$this->rockRegistry=$rockRegistry;
		$this->structurePercents=$structurePercents;
		$this->randomizer=new Randomizer(new Mt19937($seed));
		$total=$map->getSizeX()*$map->getSizeZ();
		$this->targetMass=(int)round($total*($this->landPercent/100));
	}
	
	public function generate(): MassMap{
		for($x=0;$x<$this->map->getSizeX();$x++){
			for($z=0;$z<$this->map->getSizeZ();$z++){
				$cell=$this->map->get($x,$z);
				if($cell===null)continue;
					$this->fillCell($cell,false);
			}
		}
		$this->fillLandArea();
		$this->generateStructures();
		return $this->map;
	}

    // @param ObjectPlacement[] $objects
    private function generateMass(array $objects): void{
        $potentialCells=[];
        foreach($objects as $object){
            foreach($this->getPotentialCells($object) as $cell){
                $key=$cell->getX().":".$cell->getZ();
                if(!isset($potentialCells[$key])){
                    $potentialCells[$key]=["cell"=>$cell,"objects"=>[]];
                }
                $potentialCells[$key]["objects"][]=$object;
            }
        }
        if($potentialCells===[]){
            return;
        }
        $seedCells=$this->createObjectSeeds($objects,$potentialCells);
        $remainingMass=$this->targetMass-$this->map->countMass();
        if($remainingMass<=0){
            return;
        }
        $candidates=$this->createCandidates($potentialCells,$seedCells);
        if($candidates===[]){
            return;
        }
        $this->fillRemainingMass($candidates,$remainingMass);
    }

    /* @param ObjectPlacement[] $objects
     * @param array<string,array{cell:MassCell,objects:ObjectPlacement[]}> $potentialCells
     * @return array<string,MassCell> */
    private function createObjectSeeds(array $objects,array $potentialCells): array{
        $seeds=[];
        foreach($objects as $object){
            $bestCell=null;
            $bestDistance=PHP_INT_MAX;
            foreach($potentialCells as $entry){
                /** @var MassCell $cell */
                $cell=$entry["cell"];
                $belongsToObject=false;
                foreach($entry["objects"] as $candidateObject){
                    if($candidateObject->getId()===$object->getId()){
                        $belongsToObject=true;
                        break;
                    }
                }

                if(!$belongsToObject){
                    continue;
                }
                $distance=abs($cell->getX()-$object->getPositionX()) + abs($cell->getZ()-$object->getPositionZ());
                if($distance<$bestDistance){
                    $bestDistance=$distance;
                    $bestCell=$cell;
                }
            }
            if($bestCell===null){
                continue;
            }
            if($bestCell->isEmpty()){
                $this->fillCell($bestCell);
            }
            $key=$bestCell->getX().":".$bestCell->getZ();$seeds[$key]=$bestCell;
        }
        return $seeds;
    }

    /* @param array<string,array{cell:MassCell,objects:ObjectPlacement[]}> $potentialCells
     * @param array<string,MassCell> $seedCells
     * @return array<int,array{cell:MassCell,score:float}> */
    private function createCandidates(array $potentialCells,array $seedCells): array{
        $candidates=[];
        foreach($potentialCells as $key=>$entry){
            $cell=$entry["cell"];	// @var MassCell $cell
            if(!$cell->isEmpty()){
                continue;
            }
            $bestScore=PHP_FLOAT_MAX;
            foreach($entry["objects"] as $object){
                $distance=abs($cell->getX()-$object->getPositionX()) + abs($cell->getZ()-$object->getPositionZ());
                $objectScore=$distance;
                if($objectScore<$bestScore){
                    $bestScore=$objectScore;
                }
            }
            if($bestScore===PHP_FLOAT_MAX){
                continue;
            }
            $overlapBonus=max(0,count($entry["objects"])-1);
            $score=$bestScore-($overlapBonus*2);
            $score+=$this->randomizer->getInt(0,1000)/10000;
            $candidates[]=["cell"=>$cell,"score"=>$score];
        }

        usort($candidates,function(array $a,array $b): int{return $a["score"]<=>$b["score"];});
        return $candidates;
    }

    // @param array<int,array{cell:MassCell,score:float}> $candidates
    private function fillRemainingMass(array $candidates,int $remainingMass): void{

        $count=min($remainingMass,count($candidates));
        for($i=0;$i<$count;$i++){
            $cell=$candidates[$i]["cell"];	// @var MassCell $cell
            if(!$cell->isEmpty()){
                continue;
            }
            $this->fillCell($cell);
        }
    }

	// Создаёт начальную материю в клетке
	private function fillCell(MassCell $cell,bool $land=true): void{
		$cell->setLand($land);
		$cell->removeEmptyLayers();
		$density=$this->getDensityFromRandom();
		$amount=$land ? $this->randomizer->getInt(70,110) : $this->randomizer->getInt(20,30);
		$rock=$this->getInitialRock();
		$cell->setDensity($density);
		/* Под водой водоносного слоя пока нет.
		 * Вода появляется только внутри клеток суши.*/
		if(!$land){
			$cell->addLayer(new RockLayer($rock,(float)$amount));
			return;
		}

		/* Случайная глубина нижней границы
		 * водоносного слоя.*/
		$waterStart=$this->randomizer->getInt(5,15);

		/* Гарантируем, что вода полностью
		 * помещается внутрь исходной массы. */
		$waterStart=min($waterStart,$amount-$this->aquiferLevel);
		// Порода ниже воды.
		if($waterStart>0){
			$cell->addLayer(new RockLayer($rock,(float)$waterStart));
		}

		// Водоносный слой.
		$cell->addLayer(new RockLayer($this->rockRegistry->get("WATER"),(float)$this->aquiferLevel)
		);

		//Порода выше воды.
		$upperAmount=$amount-$waterStart-$this->aquiferLevel;
		if($upperAmount>0){
			$cell->addLayer(new RockLayer($rock,(float)$upperAmount));
		}
	}
			
    // Выбирает случайный материал, разрешённый для первичного формирования
    private function getInitialRock(): RockType{
        $rocks=[];
        foreach($this->rockRegistry->getAll() as $rock){
            if(!$rock->isInitial()){
                continue;
            }
            $rocks[]=$rock;
        }

        if($rocks===[]){
            throw new \RuntimeException("RockRegistry does not contain initial rocks");
        }
        return $rocks[$this->randomizer->getInt(0,count($rocks)-1)];
    }

    // @return MassCell[]
    private function getPotentialCells(ObjectPlacement $object): array{
        $columns=$this->objectLayout->getColumns();
        $rows=$this->objectLayout->getRows();
        $sectorWidth=$this->map->getSizeX()/$columns;
        $sectorHeight=$this->map->getSizeZ()/$rows;
        $centerX=($object->getGridX()+0.5)*$sectorWidth;
        $centerZ=($object->getGridZ()+0.5)*$sectorHeight;
        $radiusX=max(1.0,$sectorWidth*0.85);
        $radiusZ=max(1.0,$sectorHeight*0.85);
        $startX=max(0,(int)floor($centerX-$radiusX));
        $endX=min($this->map->getSizeX()-1,(int)ceil($centerX+$radiusX));
        $startZ=max(0,(int)floor($centerZ-$radiusZ));
        $endZ=min($this->map->getSizeZ()-1,(int)ceil($centerZ+$radiusZ));
        $cells=[];
        for($x=$startX;$x<=$endX;$x++){
            for($z=$startZ;$z<=$endZ;$z++){
                $dx=($x-$centerX)/$radiusX;
                $dz=($z-$centerZ)/$radiusZ;
                $distance=sqrt(($dx*$dx)+($dz*$dz));
                if($distance>1.0){continue;}
                $cell=$this->map->get($x,$z);
                if($cell===null){
                    continue;
                }
                $cells[]=$cell;
            }
        }
        return $cells;
    }

    private function getDensityFromRandom(): int{
        $value=$this->randomizer->getInt(1,100);
        if($value<=10){
            return MassCell::DENSE;
        }
        if($value<=35){
            return MassCell::MEDIUM;
        }
        if($value<=70){
            return MassCell::WEAK;
        }
        return MassCell::LOOSE;
    }

	// Заполняет центральную область повышенной массой согласно проценту суши
	private function fillLandArea(): void{
		$sizeX=$this->map->getSizeX();
		$sizeZ=$this->map->getSizeZ();
		$total=$sizeX*$sizeZ;
		$target=(int)round($total*($this->landPercent/100));
		if($target<=0){
			return;
		}
		$startX=intdiv($sizeX,2);
		$startZ=intdiv($sizeZ,2);
		/** @var array<string,bool> $land */
		$land=[];
		/** @var array<int,array{0:int,1:int}> $frontier */
		$frontier=[[$startX,$startZ]];
		while(count($land)<$target && $frontier!==[]){
			/* Выбираем случайную клетку из фронтира.
			 * Это не даёт форме превращаться в квадрат. */
			$index=$this->randomizer->getInt(0,count($frontier)-1);
			[$x,$z]=array_splice($frontier,$index,1)[0];
			if($x<0 || $x>=$sizeX || $z<0 || $z>=$sizeZ){
				continue;
			}
			$key=$x.":".$z;
			if(isset($land[$key])){
				continue;
			}
			$cell=$this->map->get($x,$z);
			if($cell===null){
				continue;
			}
			$land[$key]=true;
			$this->fillCell($cell,true);
			// Добавляем соседей.
			$neighbours=[[$x-1,$z],[$x+1,$z],[$x,$z-1],[$x,$z+1]];
			// Перемешиваем соседей, чтобы направление роста не было постоянным.
			for($i=count($neighbours)-1;$i>0;$i--){
				$j=$this->randomizer->getInt(0,$i);
				$tmp=$neighbours[$i];
				$neighbours[$i]=$neighbours[$j];
				$neighbours[$j]=$tmp;
			}
			foreach($neighbours as [$nextX,$nextZ]){
				if($nextX<0 || $nextX>=$sizeX || $nextZ<0 || $nextZ>=$sizeZ){
					continue;
				}
				$nextKey=$nextX.":".$nextZ;
				if(isset($land[$nextKey])){
					continue;
				}
				// Не добавляем одну и ту же клетку во фронтир повторно.
				$alreadyQueued=false;
				foreach($frontier as [$frontX,$frontZ]){
					if($frontX===$nextX && $frontZ===$nextZ){
						$alreadyQueued=true;
						break;
					}
				}
				if(!$alreadyQueued){
					$frontier[]=[$nextX,$nextZ];
				}
			}
		}
	}

	// @return MassCell[]
	private function getLandCells(): array{
		$cells=[];
		for($x=0;$x<$this->map->getSizeX();$x++){
			for($z=0;$z<$this->map->getSizeZ();$z++){
				$cell=$this->map->get($x,$z);
				if($cell===null || !$cell->isLand()){
					continue;
				}
				$cells[]=$cell;
			}
		}
		return $cells;
	}

	
	// Границы реально сформированной суши.
	private function getLandBounds(): array{
		$landCells=$this->getLandCells();
		if($landCells===[]){
			return [0,-1,0,-1];
		}
		$startX=PHP_INT_MAX;
		$endX=PHP_INT_MIN;
		$startZ=PHP_INT_MAX;
		$endZ=PHP_INT_MIN;
		foreach($landCells as $cell){
			$startX=min($startX,$cell->getX());
			$endX=max($endX,$cell->getX());
			$startZ=min($startZ,$cell->getZ());
			$endZ=max($endZ,$cell->getZ());
		}
		return [$startX,$endX,$startZ,$endZ];
	}

	// Структуры
	private function getStructurePercent(string $type): int{return max(0,min(50,(int)($this->structurePercents[$type]??0)));}
	private function createStructure(string $type): Structure{
		$structure=new Structure($this->nextStructureId++,$type);
		$this->structures[$structure->getId()]=$structure;
		return $structure;
	}
	/** @return array<int,Structure>*/
	public function getStructures(): array{return $this->structures;}
	private function generateStructures(): void{
		$mountainPercent=$this->getStructurePercent(StructureType::MOUNTAIN_RANGE);
		if($mountainPercent>0){
			$this->generateMountainRange($mountainPercent);
		}
		$capePercent=$this->getStructurePercent(StructureType::CAPE);
		if($capePercent>0){
			$this->generateCape($capePercent);
		}
		$basinPercent=$this->getStructurePercent(StructureType::BASIN);
		if($basinPercent>0){
			$this->generateBasins($basinPercent);
		}
	}
	
	private function addStructureCell(Structure $structure, MassCell $cell): void{
		$x=$cell->getX();
		$z=$cell->getZ();
		$structureId=$structure->getId();

		$cell->addStructure($structure);
		$structure->addCell($x,$z);

		$directions=[
			[-1,-1],[0,-1],	[1,-1],
			[-1,0],			[1,0],
			[-1,1],	[0,1],	[1,1],
		];
		foreach($directions as [$dx,$dz]){
			$neighbour=$this->map->get($x+$dx,$z+$dz);
			if($neighbour===null){
				continue;
			}
			if(!$neighbour->hasStructure($structureId)){
				continue;
			}
			$cell->addStructureNeighbour($structureId,[$dx,$dz]);
			$neighbour->addStructureNeighbour($structureId,[-$dx,-$dz]);
		}
	}
	
	// Расчёт в проентах от процентов суши
	private function getStructureTargetMass(int $structurePercent): int{
		$structurePercent=max(0,min(50,$structurePercent));
		if($structurePercent<=0){
			return 0;
		}
		$landCells=$this->getLandCells();
		if($landCells===[]){
			return 0;
		}
		return max(1,(int)round(count($landCells)*($structurePercent/100)));
	}
	
	// Горы
	private function generateMountainRange(int $structurePercent): void{
		$targetMass=$this->getStructureTargetMass($structurePercent);
		if($targetMass<=0){
			return;
		}
		$landCells=$this->getLandCells();
		if($landCells===[]){
			return;
		}
		$structure=$this->createStructure(StructureType::MOUNTAIN_RANGE);
		// Случайная стартовая клетка суши.
		$startCell=$landCells[$this->randomizer->getInt(0,count($landCells)-1)];
		/** @var array<string,bool> $used */
		$used=[];
		/** @var array<int,MassCell> $frontier */
		$frontier=[$startCell];
		while(count($structure->getCells())<$targetMass && $frontier!==[]){
			$index=$this->randomizer->getInt(0,count($frontier)-1);
			$cell=array_splice($frontier,$index,1)[0];
			$key=$cell->getX().":".$cell->getZ();
			if(isset($used[$key])){
				continue;
			}
			if(!$cell->isLand()){
				continue;
			}
			$used[$key]=true;
			$this->addMountainMaterial($cell);
			$this->addStructureCell($structure,$cell);
			$neighbours=[
				[$cell->getX()-1,$cell->getZ()],
				[$cell->getX()+1,$cell->getZ()],
				[$cell->getX(),$cell->getZ()-1],
				[$cell->getX(),$cell->getZ()+1]
			];
			// Немного случайных неслучайновтей в направлении хребта.
			for($i=count($neighbours)-1;$i>0;$i--){
				$j=$this->randomizer->getInt(0,$i);
				$tmp=$neighbours[$i];
				$neighbours[$i]=$neighbours[$j];
				$neighbours[$j]=$tmp;
			}
			foreach($neighbours as [$x,$z]){
				$next=$this->map->get($x,$z);
				if($next===null || !$next->isLand()){
					continue;
				}
				$nextKey=$x.":".$z;
				if(isset($used[$nextKey])){
					continue;
				}
				$frontier[]=$next;
			}
		}
	}

		// Материал гор
		private function addMountainMaterial(MassCell $cell): void{
			$rock=$this->getInitialRock();
			$amount=$this->randomizer->getInt(20,50);
			$cell->addSolidMass([new RockLayer($rock,(float)$amount)]);
		}
	
	// Бассейн — углубление в массе
	private function generateBasins(int $structurePercent): void{
		$targetMass=$this->getStructureTargetMass($structurePercent);
		if($targetMass<=0){
			return;
		}
		$landCells=$this->getLandCells();
		if($landCells===[]){
			return;
		}
		$structure=$this->createStructure(StructureType::BASIN);
		// Случайный центр внутри суши.
		$center=$landCells[$this->randomizer->getInt(0,count($landCells)-1)];
		$centerX=$center->getX();
		$centerZ=$center->getZ();
		// Площадь эллипса ≈ π * rx * rz. Для простоты делаем примерно круг.
		$radius=max(1,(int)round(sqrt($targetMass/M_PI)));
		/** @var array<string,bool> $used */
		$used=[];
		for($x=$centerX-$radius;$x<=$centerX+$radius;$x++){
			for($z=$centerZ-$radius;$z<=$centerZ+$radius;$z++){
				if(count($structure->getCells())>=$targetMass){
					break 2;
				}
				$cell=$this->map->get($x,$z);
				if($cell===null || !$cell->isLand()){
					continue;
				}
				$dx=$x-$centerX;
				$dz=$z-$centerZ;
				if(($dx*$dx)+($dz*$dz)>($radius*$radius)){
					continue;
				}
				$key=$x.":".$z;
				if(isset($used[$key])){
					continue;
				}
				$used[$key]=true;
				$basinMass=$this->randomizer->getInt(self::MIN_BASIN_MASS,self::MAX_BASIN_MASS);
				$this->setBasinMass($cell,$basinMass);
				$this->addStructureCell($structure,$cell);
			}
		}
	}
	
		private function setBasinMass(MassCell $cell,int $targetMass): void{
			$targetMass=max(self::MIN_BASIN_MASS,min(self::MAX_BASIN_MASS,$targetMass));
			$currentMass=$cell->getMaterialAmount();
			if($currentMass<=$targetMass){
				return;
			}
			$removeAmount=$currentMass-$targetMass;
			$layers=$cell->getLayers();
			// Убираем массу сверху вниз. Воду не трогаем: Basin изменяет именно сформированную твёрдую массу.
			for($i=count($layers)-1;$i>=0 && $removeAmount>0.0;$i--){
				$layer=$layers[$i];
				if($layer->isEmpty()){ // Было if($cell===null || !$layer->isEmpty()){
					continue;
				}
				$available=$layer->getAmount();
				if($available<=0.0){
					continue;
				}
				$take=min($available,$removeAmount);
				// Удаляем часть слоя пропорционально,чтобы его физическое состояние оставшейся массы не менялось.
				$ratio=$available>0.0 ? $take/$available : 0.0;
				$layer->setAmount($available-$take);
				$layer->setStress($layer->getStress()*(1.0-$ratio));
				$layer->setWaterContent($layer->getWaterContent()*(1.0-$ratio));
				$removeAmount-=$take;
			}
			$cell->setLayers($layers);
		}
		
	// Мыс — выступ суши от берега в воду.
	private function generateCape(int $structurePercent): void{
		$targetMass=$this->getStructureTargetMass($structurePercent);
		if($targetMass<=0){
			return;
		}
		$landCells=$this->getLandCells();
		if($landCells===[]){
			return;
		}
		[$startX,$endX,$startZ,$endZ]=$this->getLandBounds();
		$landWidth=$endX-$startX+1;
		$landHeight=$endZ-$startZ+1;
		// Выбираем случайную сторону:
		// 0 = север, 1 = юг, 2 = запад, 3 = восток.
		$side=$this->randomizer->getInt(0,3);
		// Размер основания мыса.
		$baseWidth=$side<=1 ? max(1,(int)round($landWidth*0.15)) : max(1,(int)round($landHeight*0.15));
		// Длина выступа в воду.
		$length=$side<=1 ? max(1,(int)round($landHeight*0.12)) : max(1,(int)round($landWidth*0.12));
		$structure=$this->createStructure(StructureType::CAPE);
		if($side===0){
			// Северная сторона.
			$centerX=$this->randomizer->getInt($startX+$baseWidth,max($startX+$baseWidth,$endX-$baseWidth));
			for($distance=1;$distance<=$length;$distance++){
				$z=$startZ-$distance;
				if($z<0){
					break;
				}
				// Постепенно сужаем мыс к концу.
				$width=max(1,(int)round($baseWidth*(1-($distance/$length))));
				for($offset=-$width;$offset<=$width;$offset++){
					$x=$centerX+$offset;
					if($x<$startX || $x>$endX){
						continue;
					}
					$cell=$this->map->get($x,$z);
					if($cell===null){
						continue;
					}
					$cell->setLand(true);
					$this->addCapeMaterial($cell);
					$this->addStructureCell($structure,$cell);
				}
			}
			return;
		}
		if($side===1){
			// Южная сторона.
			$centerX=$this->randomizer->getInt($startX+$baseWidth,max($startX+$baseWidth,$endX-$baseWidth));
			for($distance=1;$distance<=$length;$distance++){
				$z=$endZ+$distance;
				if($z>=$this->map->getSizeZ()){
					break;
				}
				$width=max(1,(int)round($baseWidth*(1-($distance/$length))));
				for($offset=-$width;$offset<=$width;$offset++){
					$x=$centerX+$offset;
					if($x<$startX || $x>$endX){
						continue;
					}
					$cell=$this->map->get($x,$z);
					if($cell===null){
						continue;
					}
					$cell->setLand(true);
					$this->addCapeMaterial($cell);
					$this->addStructureCell($structure,$cell);
				}
			}
			return;
		}
		if($side===2){
			// Западная сторона.
			$centerZ=$this->randomizer->getInt($startZ+$baseWidth,max($startZ+$baseWidth,$endZ-$baseWidth));
			for($distance=1;$distance<=$length;$distance++){
				$x=$startX-$distance;
				if($x<0){
					break;
				}
				$width=max(1,(int)round($baseWidth*(1-($distance/$length))));
				for($offset=-$width;$offset<=$width;$offset++){
					$z=$centerZ+$offset;
					if($z<$startZ || $z>$endZ){
						continue;
					}
					$cell=$this->map->get($x,$z);
					if($cell===null){
						continue;
					}
					$cell->setLand(true);
					$this->addCapeMaterial($cell);
					$this->addStructureCell($structure,$cell);
				}
			}
			return;
		}
		// Восточная сторона.
		$centerZ=$this->randomizer->getInt($startZ+$baseWidth,max($startZ+$baseWidth,$endZ-$baseWidth));
		for($distance=1;$distance<=$length;$distance++){
			$x=$endX+$distance;
			if($x>=$this->map->getSizeX()){
				break;
			}
			$width=max(1,(int)round($baseWidth*(1-($distance/$length))));
			for($offset=-$width;$offset<=$width;$offset++){
				$z=$centerZ+$offset;
				if($z<$startZ || $z>$endZ){
					continue;
				}
				$cell=$this->map->get($x,$z);
				if($cell===null){
					continue;
				}
				$cell->setLand(true);
				$this->addCapeMaterial($cell);
				$this->addStructureCell($structure,$cell);
			}
		}
	}

		// Материал мыса.
		private function addCapeMaterial(MassCell $cell): void{
			$rock=$this->getInitialRock();
			$amount=$this->randomizer->getInt(35,65);
			$cell->removeEmptyLayers();
			$cell->setDensity(MassCell::MEDIUM);
			$cell->addSolidMass([new RockLayer($rock,(float)$amount)]);
		}	
}