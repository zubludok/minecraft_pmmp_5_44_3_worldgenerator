<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Language\Translator;

final class MainMenuForm{
	
	public static function send(WorldGenerator $plugin, Player $player): void{
		$t = $plugin->getTranslator();

		$actions = [
			// Предгенерация
			0 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new PreGenerationMenu($plugin))->send($player);});
			},
			// Финальная генерация
			1 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new FinalGenerationMenu($plugin))->send($player);});
			},
			// Перемещение
			2 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new WorldListMenu($plugin))->send($player);});
			},
		];

		$form = new SimpleForm(function(Player $player, ?int $data) use($actions): void{
			if($data === null){return;}
			if(isset($actions[$data])){
				$actions[$data]($player);
			}
		});

		$form->setTitle("World Generator");
		$form->addButton($t->get("menu.main.pregeneration"));
		$form->addButton($t->get("menu.main.final_generation"));
		$form->addButton($t->get("menu.main.teleport"));

		$player->sendForm($form);
	}
}