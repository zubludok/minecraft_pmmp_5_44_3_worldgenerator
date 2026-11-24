<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

use InvalidArgumentException;

final class SectorClimate{

    public const YEAR_NORMAL = "normal";
    public const YEAR_HOT = "hot";
    public const YEAR_COLD = "cold";

    public const SEASON_SPRING = "spring";
    public const SEASON_SUMMER = "summer";
    public const SEASON_AUTUMN = "autumn";
    public const SEASON_WINTER = "winter";

    public const TIME_DAY = "day";
    public const TIME_NIGHT = "night";

    private const YEAR_TYPES = [
        self::YEAR_NORMAL,
        self::YEAR_HOT,
        self::YEAR_COLD,
    ];

    private const SEASONS = [
        self::SEASON_SPRING,
        self::SEASON_SUMMER,
        self::SEASON_AUTUMN,
        self::SEASON_WINTER,
    ];

    private const TIMES = [
        self::TIME_DAY,
        self::TIME_NIGHT,
    ];

    private array $states = [];	// @var array<string, array<string, array<string, ClimateState>>>
    private array $phenomena = [];	// @var ClimatePhenomenon[]

    public function __construct(
        private int $x,
        private int $z,
        private float $humidity
    ){
        if($humidity < 0.0 || $humidity > 100.0){
            throw new InvalidArgumentException(Humidity should be within the range of 0..100.);
        }
    }

    public function getX(): int{return $this->x;}
    public function getZ(): int{return $this->z;}
    public function getHumidity(): float{return $this->humidity;}
	
    public function setState(
        string $yearType,
        string $season,
        string $time,
        ClimateState $state
    ): void{
        $this->validateYearType($yearType);
        $this->validateSeason($season);
        $this->validateTime($time);
        $this->states[$yearType][$season][$time] = $state;
    }

    public function getState(
        string $yearType,
        string $season,
        string $time
    ): ?ClimateState{
        $this->validateYearType($yearType);
        $this->validateSeason($season);
        $this->validateTime($time);
        return $this->states[$yearType][$season][$time] ?? null;
    }

    public function hasState(
        string $yearType,
        string $season,
        string $time
    ): bool{
        $this->validateYearType($yearType);
        $this->validateSeason($season);
        $this->validateTime($time);
        return isset($this->states[$yearType][$season][$time]);
    }

    public function getStates(): array{return $this->states;}	// @return array<string, array<string, array<string, ClimateState>>>
    public function addPhenomenon(ClimatePhenomenon $phenomenon): void{$this->phenomena[] = $phenomenon;}
    public function getPhenomena(): array{return $this->phenomena;}			// @return ClimatePhenomenon[]
    public static function getYearTypes(): array{return self::YEAR_TYPES;}	// @return string[]
    public static function getSeasons(): array{return self::SEASONS;}		// @return string[]
    public static function getTimes(): array{return self::TIMES;}			// @return string[]
    private function validateYearType(string $yearType): void{
        if(!in_array($yearType, self::YEAR_TYPES, true)){
            throw new InvalidArgumentException("Unknown year type: {$yearType}.");
        }
    }

    private function validateSeason(string $season): void{
        if(!in_array($season, self::SEASONS, true)){
            throw new InvalidArgumentException("Unknown season: {$season}.");
        }
    }

    private function validateTime(string $time): void{
        if(!in_array($time, self::TIMES, true)){
            throw new InvalidArgumentException("Unknown time: {$time}.");
        }
    }
}