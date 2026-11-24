<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateGenerator{

    public function __construct(
        private ClimateTemperatureGradientGenerator $gradientGenerator,
        private ClimateCycloneRouteGenerator $routeGenerator,
        private ClimateCyclone $cyclone
    ){}

    /* Создаёт базовую температурную карту климата.*/
    public function generate(ClimateLandscapeMap $landscapeMap,float $polarTemperature,float $equatorTemperature,int $polarShift,int $windStrength): ClimateTemperatureMap{
        $sizeX = $landscapeMap->getSizeX();
        $sizeZ = $landscapeMap->getSizeZ();
        // 1. Создаём исходный температурный градиент.
        $temperatureMap = $this->gradientGenerator->generate(
            $sizeX,
            $sizeZ,
            $polarTemperature,
            $equatorTemperature,
            $polarShift
        );
        // 2. Формируем маршрут базового Cyclone.
        $route = $this->routeGenerator->generate(
            $sizeX,
            $sizeZ
        );
        // 3. Применяем перенос температурного влияния.
        return $this->cyclone->apply(
            $temperatureMap,
            $landscapeMap,
            $route,
            $windStrength
        );
    }
}