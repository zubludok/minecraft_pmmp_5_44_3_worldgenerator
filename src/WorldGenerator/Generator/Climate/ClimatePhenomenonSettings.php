<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimatePhenomenonSettings{

    public static function definitions(): array{
        return [
            "minRegionSize" => [
                "label" => "menu.climatephenomenonsettings.label1",
                "min" => 1,
                "max" => 64,
                "step" => 1,
                "default" => 8
            ],
            "regionExpansionRatio" => [
                "label" => "menu.climatephenomenonsettings.label2",
                "min" => 0.0,
                "max" => 2.0,
                "step" => 0.05,
                "default" => 0.35
            ],

            "humidityRainMin" => [
                "label" => "menu.climatephenomenonsettings.label3",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.40
            ],
            "humiditySnowMin" => [
                "label" => "menu.climatephenomenonsettings.label4",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.35
            ],
            "humidityFogMin" => [
                "label" => "menu.climatephenomenonsettings.label5",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.50
            ],
            "humidityStormMax" => [
                "label" => "menu.climatephenomenonsettings.label6",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.20
            ],

            "temperatureFreeze" => [
                "label" => "menu.climatephenomenonsettings.label7",
                "min" => -20.0,
                "max" => 20.0,
                "step" => 0.5,
                "default" => 0.0
            ],
            "temperatureSnowMax" => [
                "label" => "menu.climatephenomenonsettings.label8",
                "min" => -20.0,
                "max" => 20.0,
                "step" => 0.5,
                "default" => 2.0
            ],

            "windRainMax" => [
                "label" => "menu.climatephenomenonsettings.label9",
                "min" => 0.0,
                "max" => 1.5,
                "step" => 0.05,
                "default" => 1.0
            ],
            "windFogMax" => [
                "label" => "menu.climatephenomenonsettings.label10",
                "min" => 0.0,
                "max" => 1.5,
                "step" => 0.05,
                "default" => 0.40
            ],
            "windStormMin" => [
                "label" => "menu.climatephenomenonsettings.label11",
                "min" => 0.0,
                "max" => 1.5,
                "step" => 0.05,
                "default" => 0.80
            ]
        ];
    }

    public static function defaults(): array{
        $result = [];
        foreach(self::definitions() as $key => $definition){
            $result[$key] = $definition["default"];
        }
        return $result;
    }

    public static function normalize(array $settings): array{
        $result = self::defaults();
        foreach(self::definitions() as $key => $definition){
            if(!isset($settings[$key]) || !is_numeric($settings[$key])){
                continue;
            }
            $value = (float) $settings[$key];
            $value = max(
                (float) $definition["min"],
                min((float) $definition["max"], $value)
            );
            if($key === "minRegionSize"){
                $value = (float) (int) round($value);
            }
            $result[$key] = $value;
        }
        return $result;
    }
}