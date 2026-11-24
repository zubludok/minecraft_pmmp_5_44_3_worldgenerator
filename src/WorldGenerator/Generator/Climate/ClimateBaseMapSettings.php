<?php

declare(strict_types=1);

namespace WorldGenerator\Generator\Climate;

final class ClimateBaseMapSettings{

    public const TEMP_SCALE = "TEMP_SCALE";
    public const MOVE_HOT_POLAR_PULL = "MOVE_HOT_POLAR_PULL";
    public const MOVE_HOT_EQUATOR_LIFT = "MOVE_HOT_EQUATOR_LIFT";
    public const MOVE_COLD_POLAR_DROP = "MOVE_COLD_POLAR_DROP";
    public const MOVE_COLD_EQUATOR_PULL = "MOVE_COLD_EQUATOR_PULL";
    public const LANDSCAPE_WIND_FACTOR = "LANDSCAPE_WIND_FACTOR";

    /* Описания параметров для автоматического построения формы.
     * @return array<string, array{
     *     label:string,
     *     min:float,
     *     max:float,
     *     step:float,
     *     default:float
     * }> */
    public static function definitions(): array{
        return [
            self::TEMP_SCALE => [
                "label" => "menu.climatebasemapsettings.label1",
                "min" => 1.0,
                "max" => 200.0,
                "step" => 1.0,
                "default" => 50.0
            ],
            self::MOVE_HOT_POLAR_PULL => [
                "label" => "menu.climatebasemapsettings.label2",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.55
            ],
            self::MOVE_HOT_EQUATOR_LIFT => [
                "label" => "menu.climatebasemapsettings.label3",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.15
            ],
            self::MOVE_COLD_POLAR_DROP => [
                "label" => "menu.climatebasemapsettings.label4",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.15
            ],
            self::MOVE_COLD_EQUATOR_PULL => [
                "label" => "menu.climatebasemapsettings.label4",
                "min" => 0.0,
                "max" => 1.0,
                "step" => 0.01,
                "default" => 0.55
            ],
            self::LANDSCAPE_WIND_FACTOR => [
                "label" => "menu.climatebasemapsettings.label5",
                "min" => 0.0,
                "max" => 0.1,
                "step" => 0.001,
                "default" => 0.01
            ]
        ];
    }

    /* @param array<string, mixed> $values
     * @return array<string, float> */
    public static function normalize(array $values): array{
        $result = [];
        foreach(self::definitions() as $key => $definition){
            $value = isset($values[$key]) && is_numeric($values[$key]) ? (float) $values[$key] : $definition["default"];
            $value = max($definition["min"],min($definition["max"], $value));
            $result[$key] = $value;
        }
        return $result;
    }

    public static function get(array $values,string $key): float{
        $normalized = self::normalize($values);
        return $normalized[$key];
    }
}