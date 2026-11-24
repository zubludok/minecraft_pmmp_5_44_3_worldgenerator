<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateDayNightSettings{

    /* @return array<string, array{
     *     label: string,
     *     min: float,
     *     max: float,
     *     step: float,
     *     default: float
     * }> */
    public static function definitions(): array{
        return [
            "maxWindSpeed" => [
                "label" => "menu.climatedaynightsettings.label1",
                "min" => 1.0,
                "max" => 60.0,
                "step" => 1.0,
                "default" => 30.0
            ],

            "waterDayTemperatureOffset" => [
                "label" => "menu.climatedaynightsettings.label2",
                "min" => -10.0,
                "max" => 10.0,
                "step" => 0.5,
                "default" => 1.5
            ],
            "waterNightTemperatureOffset" => [
                "label" => "menu.climatedaynightsettings.label3",
                "min" => -10.0,
                "max" => 10.0,
                "step" => 0.5,
                "default" => -1.5
            ],

            "mountainDayTemperatureOffset" => [
                "label" => "menu.climatedaynightsettings.label4",
                "min" => -15.0,
                "max" => 15.0,
                "step" => 0.5,
                "default" => 2.0
            ],
            "mountainNightTemperatureOffset" => [
                "label" => "menu.climatedaynightsettings.label5",
                "min" => -15.0,
                "max" => 15.0,
                "step" => 0.5,
                "default" => -4.0
            ],

            "landDayTemperatureOffset" => [
                "label" => "menu.climatedaynightsettings.label6",
                "min" => -15.0,
                "max" => 15.0,
                "step" => 0.5,
                "default" => 4.5
            ],
            "landNightTemperatureOffset" => [
                "label" => "menu.climatedaynightsettings.label7",
                "min" => -15.0,
                "max" => 15.0,
                "step" => 0.5,
                "default" => -5.0
            ],

            "heightTemperatureFactor" => [
                "label" => "menu.climatedaynightsettings.label8",
                "min" => -10.0,
                "max" => 10.0,
                "step" => 0.5,
                "default" => -2.0
            ],

            "mountainWindFactor" => [
                "label" => "menu.climatedaynightsettings.label9",
                "min" => 0.0,
                "max" => 2.0,
                "step" => 0.05,
                "default" => 0.8
            ],

            "waterDayWindFactor" => [
                "label" => "menu.climatedaynightsettings.label10",
                "min" => 0.0,
                "max" => 2.0,
                "step" => 0.05,
                "default" => 0.95
            ],
            "waterNightWindFactor" => [
                "label" => "menu.climatedaynightsettings.label11",
                "min" => 0.0,
                "max" => 2.0,
                "step" => 0.05,
                "default" => 1.05
            ],

            "landDayWindFactor" => [
                "label" => "menu.climatedaynightsettings.label12",
                "min" => 0.0,
                "max" => 2.0,
                "step" => 0.05,
                "default" => 1.05
            ],
            "landNightWindFactor" => [
                "label" => "menu.climatedaynightsettings.label13",
                "min" => 0.0,
                "max" => 2.0,
                "step" => 0.05,
                "default" => 0.95
            ],

            "dayBreezeWeight" => [
                "label" => "menu.climatedaynightsettings.label14",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.05,
                "default" => 0.35
            ],
            "nightBreezeWeight" => [
                "label" => "menu.climatedaynightsettings.label15",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.05,
                "default" => 0.25
            ]
        ];
    }

    // @return array<string, float>
    public static function defaults(): array{
        $result = [];
        foreach(self::definitions() as $key => $definition){
            $result[$key] = $definition["default"];
        }
        return $result;
    }

    /* @param array<string, mixed> $settings
     * @return array<string, float> */
    public static function normalize(array $settings): array{
        $result = self::defaults();
        foreach(self::definitions() as $key => $definition){
            if(!isset($settings[$key]) || !is_numeric($settings[$key])){
                continue;
            }
            $value = (float) $settings[$key];
            $value = max($definition["min"], min($definition["max"], $value));
            $result[$key] = $value;
        }
        return $result;
    }
}