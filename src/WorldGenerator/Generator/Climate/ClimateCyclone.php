<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateCyclone{

    private const TRANSFER_BASE = 0.35;
    private const TEMP_REFERENCE = 10.0;
    private const WIND_SPEED_BOOST = 0.5;
    private const WIND_SPEED_MAX = 1.5;

    /* Рассчитывает одну сезонную карту на основе исходной климатической
     * карты и цепочек, сформированных ClimateCycloneRouteGenerator.
     * @param array{
     *     north: array{
     *         equatorial: array<int, array<int, array{x:int,z:int}>>,
     *         polar: array<int, array<int, array{x:int,z:int}>>
     *     },
     *     south: array{
     *         equatorial: array<int, array<int, array{x:int,z:int}>>,
     *         polar: array<int, array<int, array{x:int,z:int}>>
     *     }
     * } $seasonChains */
    public function apply(ClimateBaseMap $sourceMap,array $seasonChains): ClimateSeasonalMap{
        $sizeX = $sourceMap->getSizeX();
        $sizeZ = $sourceMap->getSizeZ();
        if($sizeX < 1 || $sizeZ < 1){
            throw new InvalidArgumentException("Climate map dimensions must be positive.");
        }
        $result = new ClimateSeasonalMap($sizeX, $sizeZ);
        // Сначала копируем исходную погоду во все секторы.
        // Непройденные сектора сохраняют исходные значения.
        for($z = 0; $z < $sizeZ; $z++){
            for($x = 0; $x < $sizeX; $x++){
                $cell = $sourceMap->get($x, $z);
                if($cell === null){
                    continue;
                }
                $result->set(new ClimateSeasonalCell($x,$z,$cell->getTemperature(),$cell->getWindCoefficient(),0.0));
            }
        }
        // Последовательно обрабатываем цепочки.
        // При наложении последняя цепочка перезаписывает результат.
        foreach(["north", "south"] as $hemisphere){
            foreach(["equatorial", "polar"] as $movementType){
                $chains = $seasonChains[$hemisphere][$movementType] ?? [];
                foreach($chains as $chain){
                    $this->processChain($sourceMap,$result,$chain);
                }
            }
        }
        return $result;
    }

    /* @param array<int, array{x:int,z:int}> $chain */
    private function processChain(ClimateBaseMap $sourceMap,ClimateSeasonalMap $result,array $chain): void{
        $count = count($chain);
        if($count === 0){
            return;
        }
        $sizeX = $sourceMap->getSizeX();
        $sizeZ = $sourceMap->getSizeZ();
        foreach($chain as $index => $target){
            $tx = $target["x"];
            $tz = $target["z"];
            if($tx < 0 || $tx >= $sizeX || $tz < 0 || $tz >= $sizeZ){
                continue;
            }
            // Для первого T источник S — сектор слева.
            // Для следующих T источником становится предыдущий T.
            if($index === 0){
                $sx = ($tx - 1 + $sizeX) % $sizeX;
                $sz = $tz;
            }else{
                $sx = $chain[$index - 1]["x"];
                $sz = $chain[$index - 1]["z"];
            }
            // N — следующий сектор цепочки.
            // В конце цепочки продолжаем последнее направление,
            // если оно известно. Для цепочки из одного сектора
            // используем T как N.
            if(isset($chain[$index + 1])){
                $nx = $chain[$index + 1]["x"];
                $nz = $chain[$index + 1]["z"];
            }elseif($index > 0){
                $previous = $chain[$index - 1];
                $nx = ($tx + $tx - $previous["x"] + $sizeX) % $sizeX;
                $nz = $tz + ($tz - $previous["z"]);
            }else{
                $nx = $tx;
                $nz = $tz;
            }
            if($nz < 0 || $nz >= $sizeZ){
                $nx = $tx;
                $nz = $tz;
            }
            $s = $sourceMap->get($sx, $sz);
            $t = $sourceMap->get($tx, $tz);
            $n = $sourceMap->get($nx, $nz);
            if($s === null || $t === null || $n === null){
                continue;
            }
            $st = $s->getTemperature();
            $tt = $t->getTemperature();
            $nt = $n->getTemperature();
            $si = $s->getWindCoefficient();
            $ti = $t->getWindCoefficient();
            $ni = $n->getWindCoefficient();
            $sum = $si + $ti + $ni;
            if($sum > 0.0){
                $ws = $si / $sum;
                $wt = $ti / $sum;
                $wn = $ni / $sum;
                $flowTemperature = ($st * $ws) + ($tt * $wt) + ($nt * $wn);
            }else{
                $flowTemperature = $tt;
            }
            $delta = $flowTemperature - $tt;
            $transferRate = self::TRANSFER_BASE * $ti;
            $newTemperature = $tt + ($delta * $transferRate);
            $gradient = abs($flowTemperature - $tt);
            $gradientBoost = max(0.0,min(1.0, $gradient / self::TEMP_REFERENCE));
            $windCoefficient = max(0.0,min(self::WIND_SPEED_MAX,$ti + ($gradientBoost * self::WIND_SPEED_BOOST)));
            // Направление — касательная S -> N.
            // Учитываем циклический переход через края X.
            $dx = $this->wrappedDeltaX($sx,$nx,$sizeX);
            $dz = $nz - $sz;
            if($dx === 0 && $dz === 0){
                $dx = $this->wrappedDeltaX($sx, $tx, $sizeX);
                $dz = $tz - $sz;
            }
            $direction = $this->calculateDirection($dx, $dz);
            $result->set(new ClimateSeasonalCell($tx,$tz,$newTemperature,$windCoefficient,$direction));
        }
    }

    private function wrappedDeltaX(int $fromX,int $toX,int $sizeX): int{
        $dx = $toX - $fromX;
        $half = $sizeX / 2;
        if($dx > $half){
            $dx -= $sizeX;
        }elseif($dx < -$half){
            $dx += $sizeX;
        }
        return $dx;
    }

    /* 0° = север, 90° = восток. */
    private function calculateDirection(int $dx,int $dz): float{
        if($dx === 0 && $dz === 0){
            return 0.0;
        }
        $degrees = rad2deg(atan2($dx, -$dz));
        return fmod($degrees + 360.0, 360.0);
    }
}