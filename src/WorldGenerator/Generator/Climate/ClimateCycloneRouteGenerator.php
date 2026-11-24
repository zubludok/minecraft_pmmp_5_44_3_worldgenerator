<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class ClimateCycloneRouteGenerator{
	
	private int $currentSizeZ;
    private const SEASONS = ["summer","autumn","winter","spring"];

    /* Доли перемещения для северного полушария.
     * @var array<string, array{equatorial:float, polar:float}> */
    private const NORTH_SEASON_RANGES = [
        "summer" => ["equatorial" => 0.75, "polar" => 0.25],
        "autumn" => ["equatorial" => 0.25, "polar" => 0.50],
        "winter" => ["equatorial" => 0.25, "polar" => 0.75],
        "spring" => ["equatorial" => 0.50, "polar" => 0.25],
    ];

    private const OPPOSITE_SEASON = [
        "summer" => "winter",
        "autumn" => "spring",
        "winter" => "summer",
        "spring" => "autumn",
    ];

    /* Создаёт сезонные цепочки для обоих полушарий.
     * Каждый элемент цепочки — целевой сектор T.
     * Начальный S будет вычисляться отдельно:
     * для первого T это сектор слева, далее S = предыдущий T.
     * @return array<string, array{
     *     north: array{
     *         equatorial: array<int, array<int, array{x:int,z:int}>>,
     *         polar: array<int, array<int, array{x:int,z:int}>>
     *     },
     *     south: array{
     *         equatorial: array<int, array<int, array{x:int,z:int}>>,
     *         polar: array<int, array<int, array{x:int,z:int}>>
     *     }
     * }> */
    public function generateSeasonalChains(int $sizeX,int $sizeZ): array{
		$this->currentSizeZ = $sizeZ;
        $this->validateDimensions($sizeX, $sizeZ);
        $halfHeight = intdiv($sizeZ, 2);
        $northEquatorZ = $halfHeight - 1;
        $southEquatorZ = $halfHeight;
        $result = [];
        foreach(self::SEASONS as $season){
            $northRanges = self::NORTH_SEASON_RANGES[$season];
            // Южное полушарие использует противоположный сезон.
            $southSeason = self::OPPOSITE_SEASON[$season];
            $southRanges = self::NORTH_SEASON_RANGES[$southSeason];
			$result[$season] = [
				"north" => [
					"equatorial" => $this->buildChains(
						$sizeX, $sizeZ, $northEquatorZ, -1, 1,
						$this->getChainLength($halfHeight, $northRanges["equatorial"])
					),
					"polar" => $this->buildChains(
						$sizeX, $sizeZ, 0, 1, 1,
						$this->getChainLength($halfHeight, $northRanges["polar"])
					),
				],
				"south" => [
					"equatorial" => $this->buildChains(
						$sizeX, $sizeZ, $southEquatorZ, 1, 1,
						$this->getChainLength($halfHeight, $southRanges["equatorial"])
					),
					"polar" => $this->buildChains(
						$sizeX, $sizeZ, $sizeZ - 1, -1, -1,
						$this->getChainLength($halfHeight, $southRanges["polar"])
					),
				],
			];
        }
        return $result;
    }

    /* Создаёт цепочку для каждого сектора стартового ряда.
     * $startZ — ряд старта.
     * $dx — направление движения по X: -1 или +1.
     * $dz — направление движения по Z: -1 или +1.
     * $length — число целевых секторов T в цепочке.
     * @return array<int, array<int, array{x:int,z:int}>> */
	private function buildChains(int $sizeX,int $sizeZ,int $startZ,int $dx,int $dz,int $length): array{
		$chains = [];
		for($startX = 0; $startX < $sizeX; $startX++){
			$chain = [];
			for($step = 0; $step < $length; $step++){
				$x = (($startX + ($dx * $step)) % $sizeX + $sizeX) % $sizeX;
				$z = $startZ + ($dz * $step);
				if($z < 0 || $z >= $sizeZ){
					break;
				}
				$chain[] = ["x" => $x, "z" => $z];
			}
			$chains[] = $chain;
		}
		return $chains;
	}

    private function getChainLength(int $halfHeight,float $fraction): int{return max(1, (int) round($halfHeight * $fraction));}

    private function validateDimensions(int $sizeX,int $sizeZ): void{
        if($sizeX < 6 || $sizeZ < 6 || $sizeX % 2 !== 0 || $sizeZ % 2 !== 0){
            throw new InvalidArgumentException("Climate map dimensions must be even and at least 6x6.");
        }
    }

    /* Совместимость со старым экспортом полного обхода карты.
     * Этот маршрут не используется сезонным расчётом.
     * @return array<array{x:int,z:int}> */
    public function generate(int $sizeX,int $sizeZ): array{
        $this->validateDimensions($sizeX, $sizeZ);
        $route = [];
        for($z = 0; $z < $sizeZ; $z++){
            $xs = range(0, $sizeX - 1);
            if($z % 2 !== 0){
                $xs = array_reverse($xs);
            }
            foreach($xs as $x){
                $route[] = ["x" => $x, "z" => $z];
            }
        }
        return $route;
    }
}
