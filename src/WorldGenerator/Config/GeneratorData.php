<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

use InvalidArgumentException;
use WorldGenerator\Generator\Climate\ClimateBaseMapSettings;
use WorldGenerator\Generator\Climate\ClimateDayNightSettings;
use WorldGenerator\Generator\Climate\ClimatePhenomenonSettings;

final class GeneratorData{

    private int $mountainRangesPercent = 0;
    private int $peninsulasPercent = 0;
    private int $capesPercent = 0;
    private int $basinsPercent = 0;
    private int $polarShift = 0;
    private float $equatorTemperature = 10.0;
    private float $polarTemperature = -5.0;
    private int $isthmusesPercent = 0;
	/** @var array<string, float> */
	private array $dayNightSettings = [];
	/** @var array<string, float> */
	private array $phenomenonSettings = [];

    private const YEAR_TYPES = ["normal", "hot", "cold"];
    private const SEASONS = ["spring", "summer", "autumn", "winter"];
    private const HEMISPHERES = ["north", "south"];

    /**
     * Влажность для каждого типа года, сезона и полушария.
     *
     * @var array<string, array<string, array<string, float>>>
     */
    private array $seasonalHumiditySettings = [];

    /** @var array<string, float> */
    private array $annualClimateSettings = [];

    public function getMountainRangesPercent(): int{
        return $this->mountainRangesPercent;
    }

    public function setMountainRangesPercent(int $value): void{
        $this->mountainRangesPercent = max(0, min(50, $value));
    }

    public function getPeninsulasPercent(): int{
        return $this->peninsulasPercent;
    }

    public function setPeninsulasPercent(int $value): void{
        $this->peninsulasPercent = max(0, min(50, $value));
    }

    public function getCapesPercent(): int{
        return $this->capesPercent;
    }

    public function setCapesPercent(int $value): void{
        $this->capesPercent = max(0, min(50, $value));
    }

    public function getBasinsPercent(): int{
        return $this->basinsPercent;
    }

    public function setBasinsPercent(int $value): void{
        $this->basinsPercent = max(0, min(50, $value));
    }

    public function getIsthmusesPercent(): int{
        return $this->isthmusesPercent;
    }

    public function setIsthmusesPercent(int $value): void{
        $this->isthmusesPercent = max(0, min(50, $value));
    }

    public function getPolarShift(): int{
        return $this->polarShift;
    }

    public function setPolarShift(int $value): void{
        $this->polarShift = $value;
    }

    public function getEquatorTemperature(): float{
        return $this->equatorTemperature;
    }

    public function setEquatorTemperature(float $value): void{
        $this->equatorTemperature = $value;
    }

    public function getPolarTemperature(): float{
        return $this->polarTemperature;
    }

    public function setPolarTemperature(float $value): void{
        $this->polarTemperature = $value;
    }

    public function getAnnualClimateSettings(): array{
        return ClimateBaseMapSettings::normalize(
            $this->annualClimateSettings
        );
    }

	public function getDayNightSettings(): array{
		return ClimateDayNightSettings::normalize($this->dayNightSettings);
	}

	public function setDayNightSettings(array $settings): void{
		$this->dayNightSettings = ClimateDayNightSettings::normalize($settings);
	}
	
    public function setAnnualClimateSettings(array $settings): void{
        $this->annualClimateSettings =
            ClimateBaseMapSettings::normalize($settings);
    }

	public function getPhenomenonSettings(): array{return ClimatePhenomenonSettings::normalize($this->phenomenonSettings);}
	public function setPhenomenonSettings(array $settings): void{$this->phenomenonSettings = ClimatePhenomenonSettings::normalize($settings);}
	
    /**
     * Возвращает настройки всех 24 параметров влажности.
     *
     * @return array<string, array<string, array<string, float>>>
     */
    public function getSeasonalHumiditySettings(): array{
        $result = [];

        foreach(self::YEAR_TYPES as $yearType){
            foreach(self::SEASONS as $season){
                foreach(self::HEMISPHERES as $hemisphere){
                    $result[$yearType][$season][$hemisphere] =
                        $this->seasonalHumiditySettings[$yearType][$season][$hemisphere]
                        ?? 0.0;
                }
            }
        }

        return $result;
    }

    public function getHumidityFor(
        string $yearType,
        string $season,
        string $hemisphere
    ): float{
        $this->validateHumidityKey($yearType, $season, $hemisphere);

        return $this->seasonalHumiditySettings[$yearType][$season][$hemisphere]
            ?? 0.0;
    }

    public function setHumidityFor(
        string $yearType,
        string $season,
        string $hemisphere,
        float $humidity
    ): void{
        $this->validateHumidityKey($yearType, $season, $hemisphere);

        if(!is_finite($humidity)){
            throw new InvalidArgumentException(
                "Humidity must be a finite number."
            );
        }

        $this->seasonalHumiditySettings[$yearType][$season][$hemisphere] =
            max(-1.0, min(1.0, $humidity));
    }

    /**
     * @param array<string, mixed> $settings
     */
    public function setSeasonalHumiditySettings(array $settings): void{
        $normalized = [];

        foreach(self::YEAR_TYPES as $yearType){
            foreach(self::SEASONS as $season){
                foreach(self::HEMISPHERES as $hemisphere){
                    $value =
                        $settings[$yearType][$season][$hemisphere] ?? 0.0;

                    if(!is_numeric($value) || !is_finite((float) $value)){
                        $value = 0.0;
                    }

                    $normalized[$yearType][$season][$hemisphere] =
                        max(-1.0, min(1.0, (float) $value));
                }
            }
        }

        $this->seasonalHumiditySettings = $normalized;
    }

    private function validateHumidityKey(
        string $yearType,
        string $season,
        string $hemisphere
    ): void{
        if(
            !in_array($yearType, self::YEAR_TYPES, true)
            || !in_array($season, self::SEASONS, true)
            || !in_array($hemisphere, self::HEMISPHERES, true)
        ){
            throw new InvalidArgumentException(
                "Invalid climate year type, season or hemisphere."
            );
        }
    }

    public function toArray(): array{
        return [
            "mountainRangesPercent" => $this->mountainRangesPercent,
            "peninsulasPercent" => $this->peninsulasPercent,
            "capesPercent" => $this->capesPercent,
            "basinsPercent" => $this->basinsPercent,
            "isthmusesPercent" => $this->isthmusesPercent,
            "polarShift" => $this->polarShift,
            "equatorTemperature" => $this->equatorTemperature,
            "polarTemperature" => $this->polarTemperature,
            "seasonalHumiditySettings" => $this->getSeasonalHumiditySettings(),
            "annualClimateSettings" => $this->getAnnualClimateSettings(),
			"dayNightSettings" => $this->getDayNightSettings(),
			"phenomenonSettings" => $this->getPhenomenonSettings(),
        ];
    }

    public static function fromArray(array $data): self{
        $generator = new self();

        if(isset($data["mountainRangesPercent"])){
            $generator->setMountainRangesPercent(
                (int) $data["mountainRangesPercent"]
            );
        }

        if(isset($data["peninsulasPercent"])){
            $generator->setPeninsulasPercent(
                (int) $data["peninsulasPercent"]
            );
        }

        if(isset($data["capesPercent"])){
            $generator->setCapesPercent((int) $data["capesPercent"]);
        }

        if(isset($data["basinsPercent"])){
            $generator->setBasinsPercent((int) $data["basinsPercent"]);
        }

        if(isset($data["isthmusesPercent"])){
            $generator->setIsthmusesPercent(
                (int) $data["isthmusesPercent"]
            );
        }

        if(isset($data["polarShift"])){
            $generator->setPolarShift((int) $data["polarShift"]);
        }

        if(isset($data["equatorTemperature"])){
            $generator->setEquatorTemperature(
                (float) $data["equatorTemperature"]
            );
        }

        if(isset($data["polarTemperature"])){
            $generator->setPolarTemperature(
                (float) $data["polarTemperature"]
            );
        }

        if(
            isset($data["seasonalHumiditySettings"])
            && is_array($data["seasonalHumiditySettings"])
        ){
            $generator->setSeasonalHumiditySettings(
                $data["seasonalHumiditySettings"]
            );
        }else{
            // Новые настройки по умолчанию: все 24 значения равны 0.
            $generator->setSeasonalHumiditySettings([]);
        }

        if(
            isset($data["annualClimateSettings"])
            && is_array($data["annualClimateSettings"])
        ){
            $generator->setAnnualClimateSettings(
                $data["annualClimateSettings"]
            );
        }
		
		if(
			isset($data["dayNightSettings"])
			&& is_array($data["dayNightSettings"])
		){
			$generator->setDayNightSettings($data["dayNightSettings"]);
		}
		
		if(isset($data["phenomenonSettings"]) && is_array($data["phenomenonSettings"])){
			$generator->setPhenomenonSettings($data["phenomenonSettings"]);
		}
		
        return $generator;
    }
}