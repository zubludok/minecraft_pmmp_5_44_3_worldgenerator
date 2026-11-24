<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Language\Translator;

final class FinalGenerationMenu{

	public function __construct(
		private WorldGenerator $plugin
	){}

	public function send(Player $player): void{
		$plugin = $this->plugin;
		$t = $plugin->getTranslator();

		$actions = [
			// Запуск анализатора
			0 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new GenerationSaveListMenu($plugin))->send($player);});
			},

			// Настройки анализатора
			1 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new AnalyzerSettingsForm($plugin))->send($player);});
			},

			// Сохранить настройки — новая функция
			2 => function(Player $player) use($plugin): void{
				$this->saveAnalyzerSettings($player);
				$this->send($player);
			},

			// Загрузить настройки
			3 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new AnalyzerPresetListMenu($plugin))->send($player);});
			},

			// Назад
			4 => function(Player $player) use($plugin): void{
				FormNavigator::back($player);
			},
		];

		$form = new SimpleForm(function(Player $player, ?int $data) use($actions): void{
			if($data === null){ return; }
			if(isset($actions[$data])){
				$actions[$data]($player);
			}
		});

		$form->setTitle("Final Generation Menu");
		$form->setContent($t->get("menu.finalgeneration.setcontent"));
		$form->addButton($t->get("menu.finalgeneration.addbutton1"));
		$form->addButton($t->get("menu.finalgeneration.addbutton2"));
		$form->addButton($t->get("menu.finalgeneration.addbutton3"));
		$form->addButton($t->get("menu.finalgeneration.addbutton4"));
		$form->addButton($t->get("menu.buck"));
		$player->sendForm($form);
	}

	/* Новая функция: сохранение настроек анализатора.
	   TODO: заменить на реальное хранилище настроек анализатора. */
	private function saveAnalyzerSettings(Player $player): void{
		// TODO: $plugin->getConfigManager()->saveAnalyzerSettings(...);
		$player->sendMessage("§aНастройки анализатора сохранены (заглушка).");
	}
}