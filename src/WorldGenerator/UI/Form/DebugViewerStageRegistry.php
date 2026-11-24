<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use WorldGenerator\Generator\Simulation\SimulationState;

final class DebugViewerStageRegistry{

    public const MODE_HEIGHT = "height";
    public const MODE_TERRAIN = "terrain_objects";
    public const MODE_ISLANDS = "islands";
    public const MODE_WIND = "wind";
    public const MODE_DIRECTION = "direction";
    public const MODE_TEMPERATURE = "temperature";
    public const MODE_HUMIDITY = "humidity";
    public const MODE_PHENOMENA = "phenomena";

    /**
     * Конфигурация легенды для каждого режима отображения.
     *
     * У каждого режима есть описание и две примерные текстуры.
     * Текстуры можно менять независимо для каждого режима.
     *
     * @return array{
     *     description: string,
     *     buttons: list<array{text: string, texture: string}>
     * }
     */
    public static function getLegendConfig(string $mode): array{
        return match($mode){
            self::MODE_HEIGHT => [
                "description" => "Карта высот     0 =>\n               10 =>\n               20 =>\n               30 =>\n               40 =>\n               50 =>\n               60 =>\n               70 =>\n               80 =>\n               90 =>\n              100 =>\n              110 =>\n              120 =>\n              130 =>\n              140 =>\n              150 =>",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/h0"],
                    ["text" => "❏10", "texture" => "textures/map/h1"],
                    ["text" => "❏10", "texture" => "textures/map/h2"],
                    ["text" => "❏10", "texture" => "textures/map/h3"],
                    ["text" => "❏10", "texture" => "textures/map/h4"],
                    ["text" => "❏10", "texture" => "textures/map/h5"],
                    ["text" => "❏10", "texture" => "textures/map/h6"],
                    ["text" => "❏10", "texture" => "textures/map/h7"],
                    ["text" => "❏10", "texture" => "textures/map/h8"],
                    ["text" => "❏10", "texture" => "textures/map/h9"],
                    ["text" => "❏10", "texture" => "textures/map/h10"],
                    ["text" => "❏10", "texture" => "textures/map/h11"],
                    ["text" => "❏10", "texture" => "textures/map/h12"],
                    ["text" => "❏10", "texture" => "textures/map/h13"],
                    ["text" => "❏10", "texture" => "textures/map/h14"],
                    ["text" => "❏10", "texture" => "textures/map/h15"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            self::MODE_TERRAIN => [
                "description" => "Рельеф и объекты\n            Земля = \n             Вода = \n          Бассейн = \n       Полуостров = \n             Гора = ",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/l"],
                    ["text" => "❏10", "texture" => "textures/map/w"],
                    ["text" => "❏10", "texture" => "textures/map/bas"],
                    ["text" => "❏10", "texture" => "textures/map/cap"],
                    ["text" => "❏10", "texture" => "textures/map/mou"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            self::MODE_ISLANDS => [
                "description" => "ID острова от 1 до 20\nЭтот сектор так же относиться к острову, но он под водой.",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/li1"],
                    ["text" => "❏10", "texture" => "textures/map/wi1"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            self::MODE_WIND => [
                "description" => "Коэфициент силы\nветра:          0 =>\n              0,1 =>\n              0,2 =>\n              0,3 =>\n              0,4 =>\n              0,5 =>\n              0,6 =>\n              0,7 =>\n              0,8 =>\n              0,9 =>\        Штормовой =>",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/lw0"],
                    ["text" => "❏10", "texture" => "textures/map/lw1"],
                    ["text" => "❏10", "texture" => "textures/map/lw2"],
                    ["text" => "❏10", "texture" => "textures/map/lw3"],
                    ["text" => "❏10", "texture" => "textures/map/lw4"],
                    ["text" => "❏10", "texture" => "textures/map/lw5"],
                    ["text" => "❏10", "texture" => "textures/map/lw6"],
                    ["text" => "❏10", "texture" => "textures/map/lw7"],
                    ["text" => "❏10", "texture" => "textures/map/lw8"],
                    ["text" => "❏10", "texture" => "textures/map/lw9"],
                    ["text" => "❏10", "texture" => "textures/map/lwf"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            self::MODE_DIRECTION => [
                "description" => "Направление ветра:\n               0° =>\n              45° =>\n              90° =>\n             135° =>\n             180° =>\n             225° =>\n             270° =>\n             315° =>\n",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/lwd0"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            self::MODE_TEMPERATURE => [
                "description" => "Температура: 34°C =>\n             29°C =>\n             24°C =>\n             19°C =>\n             14°C =>\n              9°C =>\n              4°C =>\n              2°C =>\n              0°C =>\n             -2°C =>\n             -5°C =>\n            -10°C =>\n            -15°C =>\n            -20°C =>\n            -25°C =>\n            -30°C =>\n            -35°C =>\n",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/lt34"],
                    ["text" => "❏10", "texture" => "textures/map/lt29"],
                    ["text" => "❏10", "texture" => "textures/map/lt24"],
                    ["text" => "❏10", "texture" => "textures/map/lt19"],
                    ["text" => "❏10", "texture" => "textures/map/lt14"],
                    ["text" => "❏10", "texture" => "textures/map/lt9"],
                    ["text" => "❏10", "texture" => "textures/map/lt4"],
                    ["text" => "❏10", "texture" => "textures/map/lt2"],
                    ["text" => "❏10", "texture" => "textures/map/lt0"],
                    ["text" => "❏10", "texture" => "textures/map/lt-2"],
                    ["text" => "❏10", "texture" => "textures/map/lt-5"],
                    ["text" => "❏10", "texture" => "textures/map/lt-10"],
                    ["text" => "❏10", "texture" => "textures/map/lt-15"],
                    ["text" => "❏10", "texture" => "textures/map/lt-20"],
                    ["text" => "❏10", "texture" => "textures/map/lt-25"],
                    ["text" => "❏10", "texture" => "textures/map/lt-30"],
                    ["text" => "❏10", "texture" => "textures/map/lt-35"],
                ],
            ],

            self::MODE_HUMIDITY => [
                "description" => "Влажность:          \n                0 =>\n              0,1 =>\n              0,2 =>\n              0,3 =>\n              0,4 =>\n              0,5 =>\n              0,6 =>\n              0,7 =>\n              0,8 =>\n              0,9 =>\n",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/lh0"],
                    ["text" => "❏10", "texture" => "textures/map/lh1"],
                    ["text" => "❏10", "texture" => "textures/map/lh2"],
                    ["text" => "❏10", "texture" => "textures/map/lh3"],
                    ["text" => "❏10", "texture" => "textures/map/lh4"],
                    ["text" => "❏10", "texture" => "textures/map/lh5"],
                    ["text" => "❏10", "texture" => "textures/map/lh6"],
                    ["text" => "❏10", "texture" => "textures/map/lh7"],
                    ["text" => "❏10", "texture" => "textures/map/lh8"],
                    ["text" => "❏10", "texture" => "textures/map/lh9"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            self::MODE_PHENOMENA => [
                "description" => "Погодные явления\n             Туман =\n             Дождь =\n              Снег =\n       Пылева буря =\n      Снежная буря =\n",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/lpf"],
                    ["text" => "❏10", "texture" => "textures/map/lps"],
                    ["text" => "❏10", "texture" => "textures/map/lpss"],
                    ["text" => "❏10", "texture" => "textures/map/lpst"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],

            default => [
                "description" => "Для этого режима легенда не настроена.",
                "buttons" => [
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                    ["text" => "❏10", "texture" => "textures/map/fon"],
                ],
            ],
        };
    }
}