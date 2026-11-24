<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form\Base;

use pocketmine\player\Player;
use FormAPI\CustomForm;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Form\DebugViewerForm;
use WorldGenerator\Generator\Simulation\SimulationState;
use WorldGenerator\Generator\Climate\ClimateBaseMapSettings;
use WorldGenerator\Generator\Climate\ClimateDayNightSettings;
use WorldGenerator\Generator\Climate\ClimatePhenomenonSettings;
use WorldGenerator\Language\Translator;

final class ClimateSettingsForm{

    public static function send(WorldGenerator $plugin, Player $player): void{
        $manager = $plugin->getWorldGeneratorManager();
        $context = $manager->getGenerationContext();
        $simulation = $manager->getSimulationState();
        $t = $plugin->getTranslator();
        if($context === null || $simulation === null){
            $player->sendMessage($t->get("menu.climatesettingsform.send1"));
            return;
        }
        switch($simulation->getStage()){
            case SimulationState::STAGE_YEARS:
                self::sendYearSettings($plugin, $player);
                return;

            case SimulationState::STAGE_SEASONS:
                self::sendSeasonSettings($plugin, $player);
                return;

            case SimulationState::STAGE_DAY_NIGHT:
                self::sendDayNightSettings($plugin, $player);
                return;

            case SimulationState::STAGE_PHENOMENA:
                self::sendPhenomenonSettings($plugin, $player);
                return;
        }
        $player->sendMessage($t->get("menu.climatesettingsform.send2"));
    }

    // Этап 3: годовые климатические карты.
    private static function sendYearSettings(WorldGenerator $plugin,Player $player): void{
        $context = $plugin->getWorldGeneratorManager()->getGenerationContext();
        $t = $plugin->getTranslator();
        if($context === null){
            $player->sendMessage($t->get("menu.climatesettingsform.send1"));
            return;
        }
        $generator = $context->getPreset()->getGenerator();
        $maxPolarShift = max(0,intdiv($context->getSectorCountZ(), 2) - 3);
        $annualSettings = $generator->getAnnualClimateSettings();
        $parameters = [
            "equatorTemperature" => [
                "label" => $t->get("menu.climatesettingsform.year1"),
                "min" => -50,
                "max" => 50,
                "step" => 1,
                "default" => $generator->getEquatorTemperature()
            ],
            "polarTemperature" => [
                "label" => $t->get("menu.climatesettingsform.year2"),
                "min" => -50,
                "max" => 50,
                "step" => 1,
                "default" => $generator->getPolarTemperature()
            ],
            "polarShift" => [
                "label" => $t->get("menu.climatesettingsform.year3"),
                "min" => -$maxPolarShift,
                "max" => $maxPolarShift,
                "step" => 1,
                "default" => max(
                    -$maxPolarShift,
                    min($maxPolarShift, $generator->getPolarShift())
                )
            ]
        ];
        foreach(ClimateBaseMapSettings::definitions() as $key => $definition){
            $scale = self::getScale($definition["step"]);
            $parameters[$key] = [
                "label" => $t->get($definition["label"]),
                "min" => (int) round($definition["min"] * $scale),
                "max" => (int) round($definition["max"] * $scale),
                "step" => 1,
                "default" => (int) round(($annualSettings[$key] ?? $definition["default"]) * $scale),
                "scale" => $scale
            ];
        }
        self::sendParameterForm($plugin,$player,"Year Settings Form",$parameters,"year");
    }

    // Этап 4: сезонные карты и влажность.
    private static function sendSeasonSettings(WorldGenerator $plugin,Player $player): void{
        $context = $plugin->getWorldGeneratorManager()->getGenerationContext();
        $t = $plugin->getTranslator();
        if($context === null){
            $player->sendMessage($t->get("menu.climatesettingsform.send1"));
            return;
        }
        $generator = $context->getPreset()->getGenerator();
        $parameters = [];
        $humidityKeys = [];
        $yearTypes = [
            "normal" => $t->get("menu.climatesettingsform.year4"),
            "hot" => $t->get("menu.climatesettingsform.year5"),
            "cold" => $t->get("menu.climatesettingsform.year6")
        ];
        $seasons = [
            "spring" => $t->get("menu.climatesettingsform.seasons1"),
            "summer" => $t->get("menu.climatesettingsform.seasons2"),
            "autumn" => $t->get("menu.climatesettingsform.seasons3"),
            "winter" => $t->get("menu.climatesettingsform.seasons4")
        ];
        $hemispheres = [
            "north" => $t->get("menu.climatesettingsform.hemispheres1"),
            "south" => $t->get("menu.climatesettingsform.hemispheres2")
        ];
        foreach($yearTypes as $yearType => $yearLabel){
            foreach($seasons as $season => $seasonLabel){
                foreach($hemispheres as $hemisphere => $hemisphereLabel){
                    $key = "humidity_{$yearType}_{$season}_{$hemisphere}";
                    $humidityKeys[$key] = [
                        "year" => $yearType,
                        "season" => $season,
                        "hemisphere" => $hemisphere
                    ];
                    $parameters[$key] = [
                        "label" => $t->get("menu.climatesettingsform.humidity") . "{$yearLabel}, {$seasonLabel}, {$hemisphereLabel}",
                        "min" => -100,
                        "max" => 100,
                        "step" => 1,
                        "default" => (int) round($generator->getHumidityFor($yearType,$season,$hemisphere) * 100),
                        "scale" => 100
                    ];
                }
            }
        }
        self::sendParameterForm($plugin,$player,"Season Settings form",$parameters,"season",$humidityKeys);
    }

    // Этап 5: дневные и ночные карты.
    private static function sendDayNightSettings(WorldGenerator $plugin,Player $player): void{
        $context = $plugin->getWorldGeneratorManager()->getGenerationContext();
        $t = $plugin->getTranslator();
        if($context === null){
            $player->sendMessage($t->get("menu.climatesettingsform.send1"));
            return;
        }
        $generator = $context->getPreset()->getGenerator();
        $settings = $generator->getDayNightSettings();
        $parameters = [];
        foreach(ClimateDayNightSettings::definitions() as $key => $definition){
            $scale = self::getScale($definition["step"]);
            $parameters[$key] = [
                "label" => $t->get($definition["label"]),
                "min" => (int) round($definition["min"] * $scale),
                "max" => (int) round($definition["max"] * $scale),
                "step" => 1,
                "default" => (int) round(($settings[$key] ?? $definition["default"]) * $scale),
                "scale" => $scale
            ];
        }
        self::sendParameterForm($plugin,$player,"Day Night Settings Form",$parameters,"dayNight");
    }

    // Этап 6: климатические явления.
    private static function sendPhenomenonSettings(WorldGenerator $plugin,Player $player): void{
        $context = $plugin->getWorldGeneratorManager()->getGenerationContext();
        $t = $plugin->getTranslator();
        if($context === null){
            $player->sendMessage($t->get("menu.climatesettingsform.send1"));
            return;
        }
        $generator = $context->getPreset()->getGenerator();
        $settings = $generator->getPhenomenonSettings();
        $parameters = [];
        foreach(ClimatePhenomenonSettings::definitions() as $key => $definition){
            $scale = self::getScale($definition["step"]);
            $parameters[$key] = [
                "label" => $t->get($definition["label"]),
                "min" => (int) round($definition["min"] * $scale),
                "max" => (int) round($definition["max"] * $scale),
                "step" => 1,
                "default" => (int) round(($settings[$key] ?? $definition["default"]) * $scale),
                "scale" => $scale
            ];
        }
        self::sendParameterForm($plugin,$player,"Phenomenon Settings Form",$parameters,"phenomena");
    }

    /* Общий обработчик слайдеров.
     * $group:
     * year      — годовые карты;
     * season    — сезонная влажность;
     * dayNight  — суточные карты;
     * phenomena — климатические явления.
     * @param array<string, array<string, mixed>> $parameters
     * @param array<string, array{year:string, season:string, hemisphere:string}> $humidityKeys */
    private static function sendParameterForm(WorldGenerator $plugin,Player $player,string $title,array $parameters,string $group,array $humidityKeys = []): void{
        $keys = array_keys($parameters);
        $t = $plugin->getTranslator();
		$form = new CustomForm(
            function(Player $player, ?array $data) use($plugin,$keys,$parameters,$group,$humidityKeys,$t): void{
                if($data === null){
                    DebugViewerForm::send($plugin, $player);
                    return;
                }
                $values = [];
                foreach($keys as $index => $key){
                    if(!array_key_exists($index, $data) || !is_numeric($data[$index])){
                        continue;
                    }
                    $scale = (int) ($parameters[$key]["scale"] ?? 1);
                    $values[$key] = (float) $data[$index] / max(1, $scale);
                }
                switch($group){
                    case "year":
                        break;
                    case "season":
                        $humiditySettings = [];
                        foreach($humidityKeys as $key => $parts){
                            if(!isset($values[$key])){
                                continue;
                            }
                            $humiditySettings[$parts["year"]]
                                [$parts["season"]]
                                [$parts["hemisphere"]] = $values[$key];
                            unset($values[$key]);
                        }
                        $values["seasonalHumiditySettings"] = $humiditySettings;
                        break;
                    case "dayNight":
                        $dayNightSettings = [];
                        foreach(ClimateDayNightSettings::definitions() as $key => $definition){
                            if(isset($values[$key])){
                                $dayNightSettings[$key] = $values[$key];
                                unset($values[$key]);
                            }
                        }
                        $values["dayNightSettings"] = $dayNightSettings;
                        break;
                    case "phenomena":
                        $phenomenonSettings = [];
                        foreach(ClimatePhenomenonSettings::definitions() as $key => $definition){
                            if(isset($values[$key])){
                                $phenomenonSettings[$key] = $values[$key];
                                unset($values[$key]);
                            }
                        }
                        $values["phenomenonSettings"] = $phenomenonSettings;
                        break;
                }
                if(!$plugin->getWorldGeneratorManager()->updateClimateSettings($values)){
                    $player->sendMessage($t->get("menu.climatesettingsform.send3"));
                }else{
                    $player->sendMessage($t->get("menu.climatesettingsform.send4"));
                }
                DebugViewerForm::send($plugin, $player);
            }
        );
        $form->setTitle($title);
        foreach($parameters as $parameter){
            $form->addSlider(
                $parameter["label"],
                (int) $parameter["min"],
                (int) $parameter["max"],
                (int) $parameter["step"],
                (int) round($parameter["default"])
            );
        }
        $player->sendForm($form);
    }

    // Преобразует дробный шаг слайдера в целочисленный.
    private static function getScale(int|float $step): int{
        if($step <= 0 || $step >= 1){
            return 1;
        }
        return (int) round(1 / $step);
    }
}