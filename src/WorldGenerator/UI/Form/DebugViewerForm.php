<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\CustomForm;
use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\UI\Form\Base\ClimateSettingsForm;
use WorldGenerator\UI\Form\PreGenerationMenu;
use WorldGenerator\Generator\Simulation\SimulationState;
use WorldGenerator\WorldGenerator;

final class DebugViewerForm{

    private static array $viewports = [];		// @var array<string, MapViewport>
	private static array $cycleTasks = [];		// @var array<string, \pocketmine\scheduler\TaskHandler>
	private static array $formationTasks = [];	// @var array<string, \pocketmine\scheduler\TaskHandler>
	
	public static function send(WorldGenerator $plugin, Player $player): void{
        $manager = $plugin->getWorldGeneratorManager();
        $map = $manager->getDebugMassMap();

        if($map === null){
            $player->sendMessage("§l§cКарта ещё не сгенерирована.§r");
            return;
        }

        $simulation = $manager->getSimulationState();
        $name = $player->getName();

        if(!isset(self::$viewports[$name])){	// Сохранение настроек игрока на сессию
            self::$viewports[$name] = new MapViewport($map);
        }

        $viewport = self::$viewports[$name];
		$viewport->setWaterLevel($manager->getWaterLevel());
        $viewport->setSimulation($simulation);
        $viewport->setClimateSeasonalMaps($simulation?->getClimateSeasonalMaps() ?? []);
        $viewport->setClimateTemperatureMap($simulation?->getClimateTemperatureMap());
        $viewport->setClimateBaseMaps($simulation?->getClimateBaseMaps() ?? []);
        $viewport->setClimateHumidityMaps($simulation?->getClimateHumidityMaps() ?? []);
		$viewport->setClimateDayNightMaps($simulation?->getClimateDayNightMaps() ?? []);
		$viewport->setClimatePhenomenonRegions($simulation?->getClimatePhenomenonRegions() ?? []);

        $stage = $simulation?->getStage();
		$isPhenomenaView = $stage === SimulationState::STAGE_PHENOMENA && $viewport->getCurrentDisplayMode() === 'phenomena';
        $modeName = $viewport->getDisplayModeName();

        $form = new SimpleForm(
            function(Player $player, ?int $data) use($plugin): void{
                if($data === null){
                    return;
                }
                $viewport = self::$viewports[$player->getName()] ?? null;
                if($viewport === null){
                    return;
                }
				
				if($data >= 0 && $data < 100){
					$localX = $data % 10;
					$localZ = intdiv($data, 10);

					$x = $viewport->getX() + $localX;
					$z = $viewport->getZ() + $localZ;

					self::openCellDetails($plugin, $player, $x, $z);
					return;
				}

                $manager = $plugin->getWorldGeneratorManager();
                $simulation = $manager->getSimulationState();
                $stage = $simulation?->getStage();

                switch($data){
					
					// Первая колонка
                    case 100:	// Шаг 
						if($stage === SimulationState::STAGE_PHENOMENA && $viewport->getCurrentDisplayMode() === 'phenomena'){
							$viewport->previousPhenomenon();
							self::send($plugin, $player);
							return;
						}
                        $manager->nextSimulationStep();
                        self::send($plugin, $player);
                        return;
					
					case 101:	// Открыть отчёт по клетке
						self::openCellPositionForm($plugin, $player);
						return;
					
                    case 102:	// Просмотр Влево
                        $viewport->move(-1, 0);
                        self::send($plugin, $player);
                        return;
						
					case 103:return; // Заглушка
					
                    case 104:	// Предыдущий режим отображения.
                        $viewport->previousDisplayMode();
                        self::send($plugin, $player);
                        return;
					
                    case 105:	// Выбор года.
						$viewport->nextYear();
                        self::send($plugin, $player);
                        return;
					
					// Вторая колонка
					case 106: // Завершить цикл
						$name = $player->getName();
						if($stage === SimulationState::STAGE_PHENOMENA && $viewport->getCurrentDisplayMode() === 'phenomena'){
							PhenomenaForm::send($plugin, $player);
							return;
						}
						// Не допускаем параллельного запуска нескольких симуляций.
						if(isset(self::$cycleTasks[$name])){
							return;
						}
						if($simulation !== null && $simulation->canStep()){
							$task = new SimulationCycleTask($plugin, $player);
							self::$cycleTasks[$name] = $plugin->getScheduler()->scheduleRepeatingTask($task, 1);
						}
						return;
						
                    case 107:	// Просмотр Вверх.
                        $viewport->move(0, -1);
                        self::send($plugin, $player);
                        return;
						
                    case 108:	// Открыть координаты просмтора
                        self::openPositionForm($plugin, $player);
                        return;

                    case 109:	// Просмотр Вниз
                        $viewport->move(0, 1);
                        self::send($plugin, $player);
                        return;
					
					case 110: // Легенда режима
						$mode = $viewport->getCurrentDisplayMode();
						LegendForm::send($plugin, $player, $mode);
						return;
						
					case 111: // Выбор сезона
						$viewport->nextSeason();
						self::send($plugin, $player);
						return;
						
					// Третья колонка
					case 112: // Завершить текущий этап.    
						if($stage === SimulationState::STAGE_PHENOMENA && $viewport->getCurrentDisplayMode() === 'phenomena'){
							$viewport->nextPhenomenon();
							self::send($plugin, $player);
							return;
						}
						
						if($simulation === null){
							self::send($plugin, $player);
							return;
						}
		
						if($stage === SimulationState::STAGE_FORMATION){
							$name = $player->getName();
							if(isset(self::$formationTasks[$name])){
								return;
							}
							$fragmentCount = $simulation->getObjectCount();
							$manager = $plugin->getWorldGeneratorManager();
							if(!$manager->startFormationStage($fragmentCount)){
								$player->sendMessage("§l§cНе удалось запустить разделение на острова.");
								self::send($plugin, $player);
								return;
							}
							$task = new FormationStageTask($plugin, $player);
							self::$formationTasks[$name] = $plugin->getScheduler()->scheduleRepeatingTask($task, 1);
							$plugin->getLogger()->info("Начато разделение массы на острова.");
							return;
						}

						if($stage === SimulationState::STAGE_ISLANDS){
							if(!$manager->completeYearMapsStage()){
								$player->sendMessage("§l§cНе удалось создать годовые климатические карты.");
							}else{
								$player->sendMessage("§l§aЭтап 3: годовые климатические карты созданы.");
							}
							self::send($plugin, $player);
							return;
						}

						if($stage === SimulationState::STAGE_YEARS){
							if(!$manager->completeSeasonalStage()){
								$player->sendMessage("§l§cНе удалось создать сезонные климатические карты.");
							}else{
								$player->sendMessage("§l§aЭтап 4: сезонные карты и влажность созданы.");
							}
							self::send($plugin, $player);
							return;
						}
						
						if($stage === SimulationState::STAGE_SEASONS){
							if(!$manager->completeDayNightStage()){
								$player->sendMessage("§l§cНе удалось создать карты дня и ночи.");
							}else{
								$player->sendMessage("§l§aЭтап 5: карты дня и ночи созданы.");
							}
							self::send($plugin, $player);
							return;
						}
						
						if($stage === SimulationState::STAGE_DAY_NIGHT){
							if(!$manager->completePhenomenaStage()){
								$player->sendMessage("§l§cНе удалось создать карты явлений.");
							}else{
								$player->sendMessage("§l§aЭтап 6: карты явлений созданы.");
							}
							self::send($plugin, $player);
							return;
						}
						
						self::send($plugin, $player);
						return;
					
                    case 113: // Настройка области отображения
                        self::openSettingsForm($plugin, $player);
                        return;
					
                    case 114: // Просмтор в Право
                        $viewport->move(1, 0);
                        self::send($plugin, $player);
                        return;
					
					case 115: // Настройки климата
						ClimateSettingsForm::send($plugin, $player);
						return;
						
                    case 116: // Следующий режим отображения легенды.
                        $viewport->nextDisplayMode();
                        self::send($plugin, $player);
                        return;
					
                    case 117: // День/ночь    
						$viewport->toggleDaytime();
						self::send($plugin, $player);
						return;
						
                    // Нижнее меню
                    case 118:
                        self::clear($player);
                        FormNavigator::open($player, function(Player $player) use($plugin): void{(
							new PreGenerationMenu($plugin))->send($player);}
						);
                        return;
                }
            }
        );

		// Текстуры для кнопок по этапам
		$stepTexture = match($stage){
			SimulationState::STAGE_FORMATION 	=> 'textures/ui/terrain_step',
			SimulationState::STAGE_ISLANDS 		=> 'textures/ui/islands_step',
			default 							=> 'textures/ui/legend_default'
		};
		$cicleTexture = match($stage){
			SimulationState::STAGE_FORMATION 	=> 'textures/ui/terrain_cicle',
			SimulationState::STAGE_ISLANDS 		=> 'textures/ui/islands_cicle',
			default 							=> 'textures/ui/legend_default'
		};
		$stagetexture = match($stage){
			SimulationState::STAGE_FORMATION 	=> 'textures/ui/stage_2',
			SimulationState::STAGE_ISLANDS 		=> 'textures/ui/stage_3',
			SimulationState::STAGE_YEARS 		=> 'textures/ui/stage_4',
			SimulationState::STAGE_SEASONS 		=> 'textures/ui/stage_5',
			SimulationState::STAGE_DAY_NIGHT 	=> 'textures/ui/stage_6',
			default 							=> 'textures/ui/legend_default'
		};
		$yearTexture = match($stage){
			SimulationState::STAGE_YEARS,
			SimulationState::STAGE_SEASONS,
			SimulationState::STAGE_DAY_NIGHT,
			SimulationState::STAGE_PHENOMENA => match($viewport->getYearKey()){
				'hot' 		=> 'textures/ui/year_hot',
				'normal' 	=> 'textures/ui/year_normal',
				'cold' 		=> 'textures/ui/year_cold',
				default 	=> 'textures/ui/year_normal'
			},
			default => 'textures/ui/legend_default'
		};
		$seasonsTexture = match($stage){
			SimulationState::STAGE_SEASONS,
			SimulationState::STAGE_DAY_NIGHT,
			SimulationState::STAGE_PHENOMENA => match($viewport->getSeasonKey()){
				'summer' 	=> 'textures/ui/seasons_summer',
				'autumn' 	=> 'textures/ui/seasons_autumn',
				'winter' 	=> 'textures/ui/seasons_winter',
				'spring' 	=> 'textures/ui/seasons_spring',
				default 	=> 'textures/ui/seasons_summer'
			},
			default => 'textures/ui/legend_default'
		};
		$daynighttexture = match($stage){
			SimulationState::STAGE_DAY_NIGHT,
			SimulationState::STAGE_PHENOMENA=> match($viewport->getTimeKey()){
				'day' 	=> 'textures/ui/day',
				'night' => 'textures/ui/night',
				default => 'textures/ui/legend_default'
			},
			default 	=> 'textures/ui/legend_default'
		};
		$legendTexture = match($viewport->getCurrentDisplayMode()){
			'height' 			=> 'textures/ui/legend_height',
			'terrain_objects' 	=> 'textures/ui/legend_terrain',
			'islands_water' 	=> 'textures/ui/legend_islands',
			'wind' 				=> 'textures/ui/legend_wind',
			'direction' 		=> 'textures/ui/legend_direction',
			'temperature' 		=> 'textures/ui/legend_temperature',
			'humidity' 			=> 'textures/ui/legend_humidity',
			'phenomena' 		=> 'textures/ui/stage_6',
			default 			=> 'textures/ui/legend_default'
		};
		$phenomenaTexture = match($viewport->getSelectedPhenomenonType()){
			'rain'  => 'textures/ui/phenomena_rain',
			'snow'  => 'textures/ui/phenomena_snow',
			'fog'   => 'textures/ui/phenomena_fog',
			'storm',
			'sand_storm' => 'textures/ui/phenomena_storm',
			default => 'textures/ui/legend_default'
		};
		
		// Сама форма
        $form->setTitle("Map Viewer Form");
		
        $form->setContent(
			"§lКарта:".$map->getSizeX()."x".$map->getSizeZ()."§r Область: ". $viewport->getX()."/".$viewport->getEndX()."§r\n"	// Надо переделать
		);
		// Рендер карты кнопками
		for($localZ = 0; $localZ < 10; ++$localZ){
			for($localX = 0; $localX < 10; ++$localX){
				$x = $viewport->getX() + $localX;
				$z = $viewport->getZ() + $localZ;
				$texture = $viewport->getCellTexture($x, $z);
				$form->addButton("∎", 0, $texture);
			}
		}
		// 1 колонка
		if(in_array($stage, [ 									// Кнопка 100
			SimulationState::STAGE_FORMATION,
			SimulationState::STAGE_ISLANDS,
		], true)){
			$form->addButton("☐", 0, $stepTexture);	// Шаг
		}else if($isPhenomenaView){
			$form->addButton("☐", 0, "textures/ui/arrow_f_l");	// Предыдущее явление 
		}else{
			$form->addButton(""); // Заглушка
		}
		$form->addButton("☐", 0, "textures/ui/cell_v"); 		// Кнопка 101
        $form->addButton("☐", 0, "textures/ui/arrow_l"); 		// Кнопка 102
		$form->addButton(""); 									// Заглушка кнопка 103
        $form->addButton("☐", 0, "textures/ui/arrow_f_l"); 		// Кнопка 104
		if(in_array($stage, [ 									// Кнопка 105
			SimulationState::STAGE_FORMATION,
			SimulationState::STAGE_ISLANDS,
		], true)){
			$form->addButton(""); // Заглушка
		}else{
			$form->addButton("☐", 0, $yearTexture);	// Смена года
		}
		// 2 колонка
		if(in_array($stage, [									// Кнопка 106
			SimulationState::STAGE_YEARS,
			SimulationState::STAGE_SEASONS,
			SimulationState::STAGE_DAY_NIGHT,
		], true)){
			$form->addButton("");	// Заглушка
		}else if($isPhenomenaView){
			$form->addButton("☐", 0, $phenomenaTexture);	// Выбранный феномен
		}else if(in_array($stage, [							
			SimulationState::STAGE_PHENOMENA,
		], true)){
			$form->addButton("");	// Заглушка
		}else{
			$form->addButton("☐", 0, $cicleTexture);	// Цикл
		}
        $form->addButton("☐", 0, "textures/ui/arrow_u");		// Кнопка 107
        $form->addButton("☐", 0, "textures/ui/xyc");			// Кнопка 108
        $form->addButton("☐", 0, "textures/ui/arrow_d");		// Кнопка 109
        $form->addButton("☐", 0, $legendTexture);				// Кнопка 110
		if(in_array($stage, [ 									// Кнопка 111
			SimulationState::STAGE_FORMATION,
			SimulationState::STAGE_ISLANDS,
			SimulationState::STAGE_YEARS,
		], true)){
			$form->addButton(""); // Заглушка
		}else{
			$form->addButton("☐", 0, $seasonsTexture);	// Смена сезона
		}		
		// 3 колонка
		if(in_array($stage, [ 									// Кнопка 112
			SimulationState::STAGE_FORMATION,
			SimulationState::STAGE_ISLANDS,
			SimulationState::STAGE_YEARS,
			SimulationState::STAGE_SEASONS,
			SimulationState::STAGE_DAY_NIGHT,
		], true)){
			$form->addButton("☐", 0, $stagetexture);	// Следующий этап
		}else if($isPhenomenaView){
			$form->addButton("☐", 0, "textures/ui/arrow_f_r");	// Следующий явление
		}else{
			$form->addButton(""); // Заглушка
		}
        $form->addButton("☐", 0, "textures/ui/ew");				// Кнопка 113
        $form->addButton("☐", 0, "textures/ui/arrow_r");		// Кнопка 114
		if(in_array($stage, [									// Кнопка 115
			SimulationState::STAGE_YEARS,
			SimulationState::STAGE_SEASONS,
			SimulationState::STAGE_DAY_NIGHT,
			SimulationState::STAGE_PHENOMENA
		], true)){
			$form->addButton("☐", 0, "textures/ui/climat_settings");
		}else{
			$form->addButton("");
		}
        $form->addButton("☐", 0, "textures/ui/arrow_f_r");		// Кнопка 116
		if(in_array($stage, [ 									// Кнопка 117
			SimulationState::STAGE_FORMATION,
			SimulationState::STAGE_ISLANDS,
			SimulationState::STAGE_YEARS,
			SimulationState::STAGE_SEASONS,
		], true)){
			$form->addButton(""); // Заглушка
		}else{
			$form->addButton("☐", 0, $daynighttexture);	// Смена суток
		}
        $form->addButton("Назад");								// Кнопка 118
		
        $player->sendForm($form);
    }

	// Открыть конкретное положение просмотра.
    private static function openPositionForm(WorldGenerator $plugin, Player $player): void{
        $viewport = self::$viewports[$player->getName()] ?? null;
        if($viewport === null){
            self::send($plugin, $player);
            return;
        }
        $form = new CustomForm(
            function(Player $player, ?array $data) use($plugin): void{
                if($data === null){
                    self::send($plugin, $player);
                    return;
                }
                $viewport = self::$viewports[$player->getName()] ?? null;
                if($viewport === null){
                    return;
                }
                $viewport->setPosition(
                    (int)($data[0] ?? 0),
                    (int)($data[1] ?? 0)
                );
                self::send($plugin, $player);
            }
        );
        $form->setTitle("Open Position Form");
        $form->addSlider("X", 0, $viewport->getMaxX(), 1, $viewport->getX());
        $form->addSlider("Z", 0, $viewport->getMaxZ(), 1, $viewport->getZ());
        $player->sendForm($form);
    }

	// Настройка перемещения области отображения
    private static function openSettingsForm(WorldGenerator $plugin, Player $player): void{
		$t = $plugin->getTranslator();
        $viewport = self::$viewports[$player->getName()] ?? null;
        if($viewport === null){
            self::send($plugin, $player);
            return;
        }
        $form = new CustomForm(
            function(Player $player, ?array $data) use($plugin): void{
                if($data === null){
                    self::send($plugin, $player);
                    return;
                }
                $viewport = self::$viewports[$player->getName()] ?? null;
                if($viewport === null){
                    return;
                }
                $viewport->setStep((int)($data[0] ?? 10));
                self::send($plugin, $player);
            }
        );
        $form->setTitle("Open Settings Form");
        $form->addSlider($t->get("menu.debugviewer.settings2"), 1, 15, 1, $viewport->getStep());
        $player->sendForm($form);
    }
	
    public static function clear(Player $player): void{unset(self::$viewports[$player->getName()]);}
	public static function clearCycleTask(Player $player): void{unset(self::$cycleTasks[$player->getName()]);}
	public static function clearFormationTask(Player $player): void{unset(self::$formationTasks[$player->getName()]);}
	
	// Форма открытой клетки, тут вообще всё
	private static function openCellDetails(WorldGenerator $plugin,Player $player,int $x,int $z): void{
		$viewport = self::$viewports[$player->getName()] ?? null;
		if($viewport === null){
			self::send($plugin, $player);
			return;
		}
		$cell = $viewport->getCell($x, $z);
		if($cell === null){
			return;
		}
		$simulation = $plugin->getWorldGeneratorManager()->getSimulationState();
		$missing = "Информация отсутствует";
		$format = static function(mixed $value, int $precision = 2) use($missing): string{
			if($value === null){
				return $missing;
			}
			if(is_bool($value)){
				return $value ? "Да" : "Нет";
			}
			if(is_int($value) || is_float($value)){
				return (string) round((float) $value, $precision);
			}
			if($value instanceof \BackedEnum){
				return (string) $value->value;
			}
			if($value instanceof \UnitEnum){
				return $value->name;
			}
			if(is_string($value)){
				return $value;
			}
			return $missing;
		};
		$content = "";

		// Координаты
		$content .= "§l§6Координаты§r\n";
		$content .= "X: ".$x."\n";
		$content .= "Z: ".$z."\n";

		// Этап симуляции
		$stageName = match($simulation?->getStage()){
			SimulationState::STAGE_FORMATION => "Формирование",
			SimulationState::STAGE_ISLANDS => "Формирование островов",
			SimulationState::STAGE_YEARS => "Годовые карты",
			SimulationState::STAGE_SEASONS => "Сезонные карты",
			SimulationState::STAGE_DAY_NIGHT => "День и ночь",
			SimulationState::STAGE_PHENOMENA => "Погодные явления",
			default => $missing,
		};

		$content .= "\n§l§6Симуляция§r\n";
		$content .= "Этап: ".$stageName."\n";
		$content .= "Цикл: ".$format($simulation?->getCycle())."\n";
		$content .= "Шаг: ".$format($simulation?->getStep())."\n";
		$content .= "Всего шагов: ".$format($simulation?->getTotalSteps())."\n";

		// Масса и формирование
		$content .= "\n§l§6Масса и формирование§r\n";
		$content .= "Плотность: ".$format($cell->getDensity())."\n";
		$content .= "Вес: ".$format($cell->getWeight())."\n";
		$content .= "Количество материала: ".$format($cell->getMaterialAmount())."\n";
		$content .= "ID острова: ".$format($cell->getFragmentId())."\n";
		$content .= "Суша: ".$format($cell->isLand())."\n";
		$formation = $cell->getFormationState();
		$content .= "Напряжение: ".$format($formation->getStress())."\n";
		$content .= "Деформация: ".$format($formation->getDeformation())."\n";
		$content .= "Перераспределения: ".$format($formation->getRedistributionCount())."\n";

		// Слои
		$content .= "\n§l§6Слои§r\n";
		$layers = $cell->getLayers();
		if($layers === []){
			$content .= $missing."\n";
		}else{
			foreach($layers as $index => $layer){
				$content .= "\n§eСлой ".($index + 1)."§r\n";
				$content .= "Порода: ".$format($layer->getRock()?->getId())."\n";
				$content .= "Количество: ".$format($layer->getAmount())."\n";
				$content .= "Вес: ".$format($layer->getWeight())."\n";
				$content .= "Сжатие: ".$format($layer->getCompression())."\n";
			}
		}

		// Структуры
		$content .= "\n§l§6Структуры§r\n";
		$structures = $cell->getStructures();
		if($structures === []){
			$content .= $missing."\n";
		}else{
			foreach($structures as $structure){
				$content .= "\nID: ".$format($structure->getId())."\n";
				$content .= "Тип: ".$format($structure->getType())."\n";
				$neighbours = $cell->getStructureNeighbours($structure->getId());
				if($neighbours === []){
					$content .= "Соседи: ".$missing."\n";
				}else{
					$neighbourNames = [];
					foreach($neighbours as $neighbour){
						$neighbourNames[] = "(".$neighbour[0].", ".$neighbour[1].")";
					}
					$content .= "Соседи: ".implode(", ", $neighbourNames)."\n";
				}
			}
		}

		// Ландшафт
		$content .= "\n§l§6Ландшафт§r\n";
		$landscape = $simulation?->getClimateLandscapeMap()?->get($x, $z);
		$content .= "Высота поверхности: ".$format($landscape?->getSurfaceHeight())."\n";
		$content .= "Вода: ".$format($landscape?->isWater())."\n";
		$content .= "Гора: ".$format($landscape?->isMountain())."\n";
		$content .= "Относительная высота: ".$format($landscape?->getRelativeHeight())."\n";
		$content .= "Перепад высоты: ".$format($landscape?->getHeightDifference())."\n";
		$content .= "Порода поверхности: ".$format($landscape?->getSurfaceRockId())."\n";
		$content .= "Тип климата: ".$format($landscape?->getClimateType())."\n";

		// Отдельная температурная карта
		$content .= "\n§l§6Базовая температурная карта§r\n";
		$temperatureCell = $simulation?->getClimateTemperatureMap()?->get($x, $z);
		$content .= "Температура: ".$format($temperatureCell?->getTemperature())."\n";
		
		// Все годовые карты
		$content .= "\n§l§6Годовые карты§r\n";
		$baseMaps = $simulation?->getClimateBaseMaps() ?? [];
		if($baseMaps === []){
			$content .= $missing."\n";
		}else{
			foreach($baseMaps as $year => $map){
				$climateCell = $map->get($x, $z);
				$content .= "\n§eГод: ".$year."§r\n";
				$content .= "Температура: ".$format($climateCell?->getTemperature())."\n";
				$content .= "Коэффициент ветра: ".$format($climateCell?->getWindCoefficient())."\n";
			}
		}

		// Все сезонные карты
		$content .= "\n§l§6Сезонные карты§r\n";
		$seasonalMaps = $simulation?->getClimateSeasonalMaps() ?? [];
		if($seasonalMaps === []){
			$content .= $missing."\n";
		}else{
			foreach($seasonalMaps as $year => $seasons){
				$content .= "\n§eГод: ".$year."§r\n";
				foreach($seasons as $season => $map){
					$climateCell = $map->get($x, $z);
					$content .= "\nСезон: ".$season."\n";
					$content .= "Температура: ".$format($climateCell?->getTemperature())."\n";
					$content .= "Коэффициент ветра: ".$format($climateCell?->getWindCoefficient())."\n";
					$content .= "Направление ветра: ".$format($climateCell?->getWindDirection())."\n";
				}
			}
		}

		// Все карты дня и ночи
		$content .= "\n§l§6Карты дня и ночи§r\n";
		$dayNightMaps = $simulation?->getClimateDayNightMaps() ?? [];
		if($dayNightMaps === []){
			$content .= $missing."\n";
		}else{
			foreach($dayNightMaps as $year => $seasons){
				$content .= "\n§eГод: ".$year."§r\n";
				foreach($seasons as $season => $times){
					$content .= "\nСезон: ".$season."\n";
					foreach($times as $time => $map){
						$climateCell = $map->get($x, $z);
						$content .= "\nВремя: ".$time."\n";
						$content .= "Температура: ".$format($climateCell?->getTemperature())."\n";
						$content .= "Коэффициент ветра: ".$format($climateCell !== null ? $climateCell->getWindSpeed() / 30.0 : null)."\n";
						$content .= "Скорость ветра: ".$format($climateCell?->getWindSpeed())."\n";
						$content .= "Направление ветра: ".$format($climateCell?->getWindDirection())."\n";
					}
				}
			}
		}

		// Все карты влажности
		$content .= "\n§l§6Карты влажности§r\n";
		$humidityMaps = $simulation?->getClimateHumidityMaps() ?? [];
		if($humidityMaps === []){
			$content .= $missing."\n";
		}else{
			foreach($humidityMaps as $year => $seasons){
				$content .= "\n§eГод: ".$year."§r\n";
				foreach($seasons as $season => $map){
					$humidityCell = $map->get($x, $z);
					$content .= "Сезон: ".$season."\n";
					$content .= "Влажность: ".$format($humidityCell?->getHumidity())."\n";
				}
			}
		}

		// Погодные явления в выбранной клетке
		$content .= "\n§l§6Погодные явления§r\n";
		$phenomena = $viewport->getPhenomenaAt($x, $z);
		if($phenomena === []){
			$content .= $missing."\n";
		}else{
			$content .= "Типы: ".implode(", ", $phenomena)."\n";
			foreach($simulation?->getClimatePhenomenonRegions() ?? [] as $region){
				if(!$region->hasCell($x, $z)){
					continue;
				}
				$content .= "\nРегион: ".$format($region->getId())."\n";
				$content .= "Явление: ".$format($region->getPhenomenon())."\n";
				$content .= "Год: ".$format($region->getYearType())."\n";
				$content .= "Сезон: ".$format($region->getSeason())."\n";
				$content .= "Полушарие: ".$format($region->getHemisphere())."\n";
				$content .= "Клеток в регионе: ".$format($region->getTotalCells())."\n";
			}
		}

		// Кнопка возврата
		$form = new SimpleForm(
			function(Player $player, ?int $data) use($plugin): void{
				if($data !== 0){
					return;
				}
				FormNavigator::open(
					$player,
					function(Player $player) use($plugin): void{
						self::send($plugin, $player);
					}
				);
			}
		);
		
		$form->setTitle("Сектор: ".$x."|".$z);
		$form->setContent($content);
		$form->addButton("Назад");

		$player->sendForm($form);
	}
	
	// Открывает клетку по указанным координатам.
	private static function openCellPositionForm(WorldGenerator $plugin,Player $player): void{
		$viewport = self::$viewports[$player->getName()] ?? null;
		if($viewport === null){
			self::send($plugin, $player);
			return;
		}
		$manager = $plugin->getWorldGeneratorManager();
		$map = $manager->getDebugMassMap();
		if($map === null){
			self::send($plugin, $player);
			return;
		}
		$maxX = max(0, $map->getSizeX() - 1);
		$maxZ = max(0, $map->getSizeZ() - 1);
		$form = new CustomForm(
			function(Player $player, ?array $data) use($plugin, $maxX, $maxZ): void{
				if($data === null){
					self::send($plugin, $player);
					return;
				}
				$x = max(0, min($maxX, (int)($data[0] ?? 0)));
				$z = max(0, min($maxZ, (int)($data[1] ?? 0)));
				self::openCellDetails($plugin, $player, $x, $z);
			}
		);
		$form->setTitle("Открыть клетку");
		$form->addSlider("Координата X",0,$maxX,1,min($viewport->getX(), $maxX));
		$form->addSlider("Координата Z",0,$maxZ,1,min($viewport->getZ(), $maxZ));
		$player->sendForm($form);
	}
}