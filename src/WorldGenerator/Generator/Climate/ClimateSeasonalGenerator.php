<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateSeasonalGenerator{

    private const STATES = ["normal", "hot", "cold"];

    private const SEASONS = [
        "spring",
        "summer",
        "autumn",
        "winter"
    ];

    /* @param array<string, ClimateBaseMap> $baseMaps
     * @return array<string, array<string, ClimateSeasonalMap>> */
    public function generate(array $baseMaps): array{
        $normalizedMaps = [];
        foreach($baseMaps as $name => $map){
            if(!$map instanceof ClimateBaseMap){
                continue;
            }
            $normalizedMaps[strtolower($name)] = $map;
        }
        foreach(self::STATES as $state){
            if(!isset($normalizedMaps[$state])){
                throw new InvalidArgumentException("The basic climate map is missing: " . strtoupper($state));
            }
        }
        $firstMap = $normalizedMaps["normal"];
        $routeGenerator = new ClimateCycloneRouteGenerator();
        $cyclone = new ClimateCyclone();
        $chainsBySeason = $routeGenerator->generateSeasonalChains($firstMap->getSizeX(),$firstMap->getSizeZ());
        $result = [];
        foreach(self::STATES as $state){
            $sourceMap = $normalizedMaps[$state];
            foreach(self::SEASONS as $season){
                $result[$state][$season] = $cyclone->apply($sourceMap,$chainsBySeason[$season]);
            }
        }
        return $result;
    }
}