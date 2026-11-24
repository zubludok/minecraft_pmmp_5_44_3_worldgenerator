<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use WorldGenerator\Generator\Mass\MassCell;
use WorldGenerator\Generator\Mass\MassMap;

final class ClimateLandscapeAnalyzer{

    private const MOUNTAIN_HEIGHT_DIFFERENCE=8.0;

    public function analyze(MassMap $massMap,int $waterLevel): ClimateLandscapeMap{
        $sizeX=$massMap->getSizeX();
        $sizeZ=$massMap->getSizeZ();
        $result=new ClimateLandscapeMap($sizeX,$sizeZ);
        /* Первый проход: определяем высоту поверхности и материал поверхности. */
        for($z=0;$z<$sizeZ;$z++){
            for($x=0;$x<$sizeX;$x++){
                $massCell=$massMap->get($x,$z);
                if($massCell===null){
                    continue;
                }
                $surfaceHeight=$this->getSurfaceHeight($massCell);
                $surfaceRock=$this->getSurfaceRock($massCell);
                /* waterLevel = номер слоя воды.
                 * Например:
                 * waterLevel = 61
                 * surfaceHeight = 60 -> вода
                 * surfaceHeight = 61 -> вода
                 * surfaceHeight = 62 -> суша */
                $isWater=$surfaceHeight<=$waterLevel;
                $result->set(new ClimateLandscapeCell($x,$z,$surfaceHeight,$isWater,$surfaceRock));
            }
        }

        /* Второй проход: сравниваем высоту клетки с соседями. */
        for($z=0;$z<$sizeZ;$z++){
            for($x=0;$x<$sizeX;$x++){
                $cell=$result->get($x,$z);
                if($cell===null){
                    continue;
                }
                $neighbours=$result->getNeighbours($x,$z);
                if($neighbours===[]){
                    continue;
                }
                $averageHeight=0.0;
                foreach($neighbours as $neighbour){
                    $averageHeight+=$neighbour->getSurfaceHeight();
                }
                $averageHeight/=count($neighbours);
                $relativeHeight=$cell->getSurfaceHeight()-$averageHeight;
                $cell->setRelativeHeight($relativeHeight);
                $cell->setHeightDifference(abs($relativeHeight));
                /* Вода не может быть горой. */
                if(!$cell->isWater() && $relativeHeight>=self::MOUNTAIN_HEIGHT_DIFFERENCE){
                    $cell->setMountain(true);
                }
            }
        }
        return $result;
    }
	
	private function getSurfaceHeight(MassCell $cell): float{
		$layers = $cell->getLayers();
		$top = count($layers) - 1;
		// Убираем только воду, находящуюся непосредственно на поверхности.
		while($top >= 0){
			$layer = $layers[$top];
			if($layer->isEmpty()){
				$top--;
				continue;
			}
			if($layer->isWater()){
				$top--;
				continue;
			}
			break;
		}
		$height = 0.0;
		for($i = 0; $i <= $top; $i++){
			$layer = $layers[$i];
			if($layer->isEmpty()){
				continue;
			}
			$height += $layer->getAmount();
		}
		return $height;
	}

	private function getSurfaceRock(MassCell $cell){
		$layers=$cell->getLayers();
		for($i=count($layers)-1;$i>=0;$i--){
			$layer=$layers[$i];
			if($layer->isEmpty()){
				continue;
			}
			if($layer->isWater()){
				continue;
			}
			return $layer->getRock();
		}
		return null;
	}
}