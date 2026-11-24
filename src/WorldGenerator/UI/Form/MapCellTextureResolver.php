<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use WorldGenerator\Generator\Structure\StructureType;

final class MapCellTextureResolver{

    private const DEFAULT_TEXTURE = 'textures/map/default';

    public function resolve(array $cell): string{
        if(!($cell['exists'] ?? false)){
            return self::DEFAULT_TEXTURE;
        }
        $mode = $cell['mode'] ?? 'height';
        $water = (bool)($cell['water'] ?? false);
        $prefix = $water ? 'w' : 'l';
        return match($mode){
            'height' => $this->resolveHeight((float)($cell['height'] ?? 0.0)),
            'terrain_objects' => $this->resolveTerrain($cell),
            'islands_water' => $this->resolveIsland(
                $cell['fragment_id'] ?? null,
                $water
            ),
            'wind' => $this->resolveWind(
                (float)($cell['wind'] ?? 0.0),
                $prefix
            ),
            'direction' => $this->resolveDirection(
                (float)($cell['direction'] ?? 0.0),
                $prefix
            ),
            'temperature' => $this->resolveTemperature(
                (float)($cell['temperature'] ?? 0.0),
                $prefix
            ),
            'humidity' => $this->resolveHumidity(
                (float)($cell['humidity'] ?? 0.0),
                $prefix
            ),
            'phenomena' => $this->resolvePhenomenon(
                $cell['phenomenon'] ?? null,
                $water
            ),
            default => self::DEFAULT_TEXTURE,
        };
    }

    private function resolveHeight(float $height): string{
        if($height <= 0.0){
            return 'textures/map/h0';
        }
        for($level = 1; $level <= 15; ++$level){
            if($height <= $level * 10){
                return 'textures/map/h' . $level;
            }
        }
        return 'textures/map/h15';
    }
	
	private function resolveTerrain(array $cell): string{
		if($cell['water'] ?? false){
			return 'textures/map/w';
		}
		return match($cell['terrain_objects'] ?? null){
			StructureType::BASIN => 'textures/map/bas',
			StructureType::CAPE => 'textures/map/cap',
			StructureType::MOUNTAIN_RANGE => 'textures/map/mou',
			default => 'textures/map/l',
		};
	}
	
    private function resolveIsland(mixed $fragmentId, bool $water): string{
        if($fragmentId === null || !is_numeric($fragmentId)){
            return $water ? 'textures/map/w' : 'textures/map/l';
        }
        $id = (int)$fragmentId;
        if($id < 1 || $id > 20){
            return $water ? 'textures/map/w' : 'textures/map/l';
        }
        return 'textures/map/' . ($water ? 'wi' : 'li') . $id;
    }

    private function resolveWind(float $wind, string $prefix): string{
        $wind = max(0.0, min(1.0, $wind));
        if($wind <= 0.0){
            $level = 0;
        }else{
            $level = (int)ceil($wind * 10);
        }
        $suffix = $level >= 10 ? 'f' : (string)$level;
        return 'textures/map/' . $prefix . 'w' . $suffix;
    }

    private function resolveDirection(float $degrees, string $prefix): string{
        $index = (int)round(fmod($degrees + 360.0, 360.0) / 45.0) % 8;
        return 'textures/map/' . $prefix . 'wd' . $index;
    }

    private function resolveTemperature(float $temperature, string $prefix): string{
        if($temperature < -35){
            $suffix = '-35';
        }elseif($temperature <= -30){
            $suffix = '-30';
        }elseif($temperature <= -25){
            $suffix = '-25';			
		}elseif($temperature <= -20){
            $suffix = '-20';
        }elseif($temperature <= -15){
            $suffix = '-15';
        }elseif($temperature <= -10){
            $suffix = '-10';
        }elseif($temperature <= -5){
            $suffix = '-5';
		}elseif($temperature <= -2){
            $suffix = '-2';
        }elseif($temperature === 0.0){
            $suffix = '0';
        }elseif($temperature <= 2){
            $suffix = '2';        
		}elseif($temperature <= 4){
            $suffix = '4';
        }elseif($temperature <= 9){
            $suffix = '9';
        }elseif($temperature <= 14){
            $suffix = '14';
        }elseif($temperature <= 19){
            $suffix = '19';
        }elseif($temperature <= 24){
            $suffix = '24';
        }elseif($temperature <= 29){
            $suffix = '29';
        }else{
            $suffix = '34';
        }
        return 'textures/map/' . $prefix . 't' . $suffix;
    }

    private function resolveHumidity(float $humidity, string $prefix): string{
        $humidity = max(0.0, min(1.0, $humidity));
        $level = min(9, (int)floor($humidity * 10) + 1);
        return 'textures/map/' . $prefix . 'h' . $level;
    }

    private function resolvePhenomenon(?string $phenomenon, bool $water): string{
        $prefix = $water ? 'w' : 'l';
        $suffix = match($phenomenon){
            'rain' => 'pr',
            'snow' => 'ps',
            'fog' => 'pf',
            'storm' => 'pst',
            'sand_storm' => 'pss',
            default => null,
        };
        if($suffix === null){
            return $water ? 'textures/map/w' : 'textures/map/l';
        }
        return 'textures/map/' . $prefix . $suffix;
    }
}