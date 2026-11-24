<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\CustomForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Navigator\FormNavigator;

final class AnalyzerSettingsForm{

	public function __construct(private WorldGenerator $plugin){}

	public function send(Player $player): void{
		$plugin = $this->plugin;
		$world = $preset->getWorld(); $t = $plugin->getTranslator(); // Для перевода

		// TODO: подставь реальные значения из настроек анализатора.
		$currentDeep  = 32;
		$currentMode  = 0;

		$form = new CustomForm(function(Player $player, ?array $data) use($plugin): void{
			if($data === null){
				FormNavigator::back($player);
				return;
			}
			$deep = (int)($data[0] ?? 32);
			$mode = (int)($data[1] ?? 0);

			// TODO: сохранить в настройки анализатора.
			$player->sendMessage("§aНастройки анализатора: глубина $deep, режим $mode (заглушка).");
			FormNavigator::back($player);
		});

		$form->setTitle("Настройки анализатора");
		$form->addSlider("Глубина анализа", 1, 256, 1, $currentDeep);
		$form->addDropdown("Режим анализа", ["Полный", "Быстрый", "Только породы"], $currentMode);

		$player->sendForm($form);
	}
}