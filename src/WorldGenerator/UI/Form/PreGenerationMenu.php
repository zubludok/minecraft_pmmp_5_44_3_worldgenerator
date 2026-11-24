<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Generator\GenerationContext;
use WorldGenerator\UI\Form\DebugViewerForm;
use WorldGenerator\Language\Translator;

final class PreGenerationMenu{

	public function __construct(private WorldGenerator $plugin){}

	public function send(Player $player): void{
		$plugin = $this->plugin;
		$session = $plugin->getSessionManager()->get($player);
		$preset  = $session->getPreset();
		$t = $plugin->getTranslator();

		$actions = [
			// 0. Предгенерация мира
			0 => function(Player $player) use($plugin, $preset, $t): void{
				$context = new GenerationContext($preset);
				$manager = $plugin->getWorldGeneratorManager();
				if($manager->generate($context)){
					$player->sendMessage($t->get("menu.pregeneration.send1"));
					$plugin->getServer()->getWorldManager()->loadWorld($preset->getWorld()->getName());
				}else{
					$player->sendMessage($t->get("menu.pregeneration.send2"));
				}
				$this->send($player);
			},

			// 1. Загрузить предгенерацию (если недоступно → открыть это же меню)
			1 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new SavedPreGenerationMenu($plugin))->send($player);});
			},

			// 2. Предпросмотр карты
			2 => function(Player $player) use($plugin, $t): void{
				$map = $plugin->getWorldGeneratorManager()->getDebugMassMap();
				if($map === null){
					$player->sendMessage($t->get("menu.pregeneration.send3"));	// Недоступно → открываем «Загрузить предгенерацию»
					FormNavigator::open($player, function(Player $player) use($plugin): void{$this->send($player);});
					return;
				}
				FormNavigator::open($player, function(Player $player) use($plugin): void{DebugViewerForm::send($plugin, $player);});
			},

			// 3. Основные настройки
			3 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new WorldSettingsMenu($plugin))->send($player);});
			},

			// 4. Дополнительные настройки
			4 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new AdditionalGenerationForm($plugin))->send($player);});
			},

			// 5. Сохранить настройки
			5 => function(Player $player) use($plugin, $preset, $t): void{
				$worldName = $preset->getWorld()->getName();
				$plugin->getConfigManager()->getPresetManager()->save($worldName, $preset);
				$player->sendMessage($t->get("menu.pregeneration.send4", ["worldname" => $worldName]));
				$this->send($player);
			},

			// 6. Загрузить настройки
			6 => function(Player $player) use($plugin): void{
				FormNavigator::open($player, function(Player $player) use($plugin): void{(new PresetListMenu($plugin))->send($player);});
			},

			// 7. Назад
			7 => function(Player $player) use($plugin): void{FormNavigator::back($player);},
		];

		$form = new SimpleForm(function(Player $player, ?int $data) use($actions): void{
			if($data === null){ return; }
			if(isset($actions[$data])){
				$actions[$data]($player);
			}
		});

		$form->setTitle($t->get("Pre Generation Menu"));
		$form->setContent($t->get("menu.pregeneration.setcontent", ["getname" => $preset->getWorld()->getName(), "getseed" => $preset->getWorld()->getSeed()]));
		$form->addButton($t->get("menu.pregeneration.addbutton0"));
		$form->addButton($t->get("menu.pregeneration.addbutton1"));
		$form->addButton($t->get("menu.pregeneration.addbutton2"));
		$form->addButton($t->get("menu.pregeneration.addbutton3"));
		$form->addButton($t->get("menu.pregeneration.addbutton4"));
		$form->addButton($t->get("menu.pregeneration.addbutton5"));
		$form->addButton($t->get("menu.pregeneration.addbutton6"));
		$form->addButton($t->get("menu.buck"));

		$player->sendForm($form);
	}
}