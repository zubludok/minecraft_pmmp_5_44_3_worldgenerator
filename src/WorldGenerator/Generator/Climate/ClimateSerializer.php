<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use JsonException;
use RuntimeException;

final class ClimateSerializer{

    // @throws JsonException
    public function serialize(string $path,SectorClimateMap $map,ClimateSettings $settings): void{
        $sizeX = $map->getSizeX();
        $sizeZ = $map->getSizeZ();
        $sectors = [];
        for($z = 0; $z < $sizeZ; $z++){
            $row = [];
            for($x = 0; $x < $sizeX; $x++){
                $climate = $map->get($x, $z);
                if($climate === null){
                    $row[] = null;
                    continue;
                }
                $row[] = $this->serializeSector($climate);
            }
            $sectors[] = $row;
        }
        $data = [
            "type" => "weather",
            "version" => 1,
            "sizeX" => $sizeX,
            "sizeZ" => $sizeZ,
            "climate" => [
                "polarTemperature" => $this->round($settings->getPolarTemperature()),
                "equatorTemperature" => $this->round($settings->getEquatorTemperature()),
                "polarShift" => $settings->getPolarShift(),
            ],
            "sectors" => $sectors,
        ];
        $directory = dirname($path);
        if(!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)){
            throw new RuntimeException("The wind force should be within the range of 0 to 10." . $directory);
        }
        file_put_contents(
            $path,
            json_encode(
                $data,
                JSON_PRETTY_PRINT |
                JSON_UNESCAPED_UNICODE |
                JSON_UNESCAPED_SLASHES |
                JSON_THROW_ON_ERROR
            )
        );
    }

    // @return array<string, mixed>
    private function serializeSector(SectorClimate $climate): array{
        $states = [];
        foreach(SectorClimate::getYearTypes() as $yearType){
            foreach(SectorClimate::getSeasons() as $season){
                foreach(SectorClimate::getTimes() as $time){
                    $state = $climate->getState($yearType,$season,$time);
                    if($state === null){
                        continue;
                    }
                    $states[$yearType][$season][$time] = [
                        "temperature" => $this->round($state->getTemperature()),
                        "wind" => [
                            "direction" => $this->round($state->getWind()->getDirection()),
                            "strength" => $this->round($state->getWind()->getStrength()),
                        ],
                    ];
                }
            }
        }
        $phenomena = [];
        foreach($climate->getPhenomena() as $phenomenon){
            $phenomena[] = [
                "type" => $phenomenon->getType(),
                "conditions" => $phenomenon->getConditions(),
            ];
        }
        return [
            "x" => $climate->getX(),
            "z" => $climate->getZ(),
            "humidity" => $this->round($climate->getHumidity()),
            "states" => $states,
            "phenomena" => $phenomena,
        ];
    }

    private function round(float $value): float|int{
        $rounded = round($value, 1);
        if(fmod($rounded, 1.0) === 0.0){
            return (int)$rounded;
        }
        return $rounded;
    }
}