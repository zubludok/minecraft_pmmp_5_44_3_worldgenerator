<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateSeasonalPassGenerator{

    private const TRANSFER_BASE = 0.35;
    private const TEMP_REFERENCE = 10.0;
    private const WIND_SPEED_BOOST = 0.5;
    private const WIND_SPEED_MAX = 1.5;

    private const SEASONS = [
        "spring" => [
            "equatorial" => 2,
            "polar" => 1,
        ],
        "summer" => [
            "equatorial" => 3,
            "polar" => 1,
        ],
        "autumn" => [
            "equatorial" => 1,
            "polar" => 2,
        ],
        "winter" => [
            "equatorial" => 1,
            "polar" => 3,
        ],
    ];

    private const OPPOSITE_SEASON = [
        "spring" => "autumn",
        "summer" => "winter",
        "autumn" => "spring",
        "winter" => "summer",
    ];

    /* @param array<string, ClimateBaseMap> $baseMaps
     * @return array<string, array<string, ClimateSeasonalMap>> */
    public function generate(array $baseMaps): array{
        $types = [
			ClimateBaseMapGenerator::NORMAL,
            ClimateBaseMapGenerator::HOT,
            ClimateBaseMapGenerator::COLD,
        ];
        foreach($types as $type){
            if(!isset($baseMaps[$type]) || !$baseMaps[$type] instanceof ClimateBaseMap){
                throw new InvalidArgumentException("The basic climate map is missing: {$type}.");
            }
        }
        $sizeX = $baseMaps[$types[0]]->getSizeX();
        $sizeZ = $baseMaps[$types[0]]->getSizeZ();
        if($sizeX < 6 || $sizeZ < 6 || $sizeX % 2 !== 0 || $sizeZ % 2 !== 0){
            throw new InvalidArgumentException("The dimensions of the climate map must be even and must be at least 10 x 10.");
        }
        foreach($types as $type){
            if($baseMaps[$type]->getSizeX() !== $sizeX || $baseMaps[$type]->getSizeZ() !== $sizeZ){
                throw new InvalidArgumentException("All basic climate maps must have the same dimensions.");
            }
        }
        $result = [];
        foreach($types as $type){
            $source = $baseMaps[$type];
            foreach(self::SEASONS as $season => $limits){
                $map = $this->createInitialMap($source);
                // Северное полушарие получает выбранный сезон.
                $this->processHemisphere($source,$map,$season,true);
                // Южное полушарие получает противоположный сезон.
                $southSeason = self::OPPOSITE_SEASON[$season];
                $this->processHemisphere($source,$map,$southSeason,false);
                $result[$type][$season] = $map;
            }
        }
        return $result;
    }

    private function createInitialMap(ClimateBaseMap $source): ClimateSeasonalMap{
        $sizeX = $source->getSizeX();
        $sizeZ = $source->getSizeZ();
        $map = new ClimateSeasonalMap($sizeX, $sizeZ);
        for($z = 0; $z < $sizeZ; $z++){
            for($x = 0; $x < $sizeX; $x++){
                $cell = $source->get($x, $z);
                if($cell === null){
                    continue;
                }
                $map->set(new ClimateSeasonalCell($x,$z,$cell->getTemperature(),$cell->getWindCoefficient(),0.0));
            }
        }
        return $map;
    }

    private function processHemisphere(ClimateBaseMap $source,ClimateSeasonalMap $result,string $season,bool $north): void{
        $sizeX = $source->getSizeX();
        $sizeZ = $source->getSizeZ();
        $halfHeight = intdiv($sizeZ, 2);
        $equatorZ = $north ? $halfHeight - 1 : $halfHeight;
        $poleZ = $north ? 0 : $sizeZ - 1;
        $limits = self::SEASONS[$season];
        // Число диагональных ходов.
        $equatorialMoves = intdiv($halfHeight * $limits["equatorial"],4);
        $polarMoves = intdiv($halfHeight * $limits["polar"],4);
        // Сначала цепочки от каждого сектора экватора.
        for($x = 0; $x < $sizeX; $x++){
            $this->processChain($source,$result,$x,$equatorZ,1,$north ? -1 : 1,$equatorialMoves);
        }
        // Затем цепочки от каждого сектора полюса.
        // Если есть наложение, этот проход перезаписывает предыдущий.
        for($x = 0; $x < $sizeX; $x++){
            $this->processChain($source,$result,$x,$poleZ,-1,$north ? 1 : -1,$polarMoves);
        }
    }

    /* @param int $stepX Направление движения по X: -1 или +1.
     * @param int $stepZ Направление движения по Z: -1 или +1. */
    private function processChain(ClimateBaseMap $source,ClimateSeasonalMap $result,int $startX,int $startZ,int $stepX,int $stepZ,int $moves): void{
        $sizeX = $source->getSizeX();
        // S в начале находится слева от T.
        $sx = $this->wrapX($startX - 1, $sizeX);
        $sz = $startZ;
        $tx = $startX;
        $tz = $startZ;
        for($step = 0; $step <= $moves; $step++){
            // N вычисляется, но на последнем шаге не становится T.
            $nx = $this->wrapX($tx + $stepX, $sizeX);
            $nz = $tz + $stepZ;
            $this->processTarget($source,$result,$sx,$sz,$tx,$tz,$nx,$nz);
            if($step === $moves){
                break;
            }
            // Следующий ход: старый T становится S.
            // Координаты переходят, значения берутся из исходной карты.
            $sx = $tx;
            $sz = $tz;
            $tx = $nx;
            $tz = $nz;
        }
    }

    private function processTarget(ClimateBaseMap $source,ClimateSeasonalMap $result,int $sx,int $sz,int $tx,int $tz,int $nx,int $nz): void{
        $sizeX = $source->getSizeX();
        $target = $source->get($tx, $tz);
        if($target === null){
            return;
        }
        $s = $source->get($this->wrapX($sx, $sizeX),$sz);
        $n = null;
        if($nz >= 0 && $nz < $source->getSizeZ()){
            $n = $source->get($nx, $nz);
        }
        $st = $s?->getTemperature() ?? $target->getTemperature();
        $tt = $target->getTemperature();
        $nt = $n?->getTemperature() ?? $tt;
        $si = max(0.0, min(1.0,$s?->getWindCoefficient() ?? 0.0));
        $ti = max(0.0, min(1.0,$target->getWindCoefficient()));
        $ni = max(0.0, min(1.0,$n?->getWindCoefficient() ?? 0.0));
        $sum = $si + $ti + $ni;
        if($sum > 0.0){
            $flowTemperature = $st * ($si / $sum) + $tt * ($ti / $sum) + $nt * ($ni / $sum);
        }else{
            // При нулевой сумме ИКСВ поток совпадает с целью.
            $flowTemperature = $tt;
        }
        $delta = $flowTemperature - $tt;
        $temperature = $tt + $delta * self::TRANSFER_BASE * $ti;
        $gradient = abs($delta);
        $gradientBoost = max(0.0,min(1.0, $gradient / self::TEMP_REFERENCE));
        $windSpeed = max(0.0,min(self::WIND_SPEED_MAX,$ti + $gradientBoost * self::WIND_SPEED_BOOST));
        // Направление касательной S -> N.
        // Кратчайшее смещение учитывает кольцевую карту по X.
        $dx = $this->wrappedDeltaX($sx, $nx, $sizeX);
        $dz = $nz - $sz;
        if($dx === 0 && $dz === 0){
            $dx = $this->wrappedDeltaX($sx, $tx, $sizeX);
            $dz = $tz - $sz;
        }
        $direction = rad2deg(atan2($dz, $dx));
        $direction = fmod($direction + 360.0, 360.0);
        // Последняя обработанная цепочка перезаписывает сектор.
        $result->set(new ClimateSeasonalCell($tx,$tz,$temperature,$windSpeed,$direction));
    }
	
    private function wrapX(int $x, int $sizeX): int{return (($x % $sizeX) + $sizeX) % $sizeX;}

    private function wrappedDeltaX(int $fromX,int $toX,int $sizeX): int{
        $delta = $toX - $fromX;
        $half = intdiv($sizeX, 2);
        if($delta > $half){
            $delta -= $sizeX;
        }elseif($delta < -$half){
            $delta += $sizeX;
        }
        return $delta;
    }
}