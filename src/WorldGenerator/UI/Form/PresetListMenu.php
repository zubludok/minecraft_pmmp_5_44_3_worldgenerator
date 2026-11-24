<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\UI\Form\Base\BaseForm;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\WorldGenerator;
use WorldGenerator\Language\Translator;

final class PresetListMenu extends BaseForm{

	public function send(Player $player): void{
		$plugin = $this->plugin;
		$presetManager = $plugin->getConfigManager()->getPresetManager();
		$presets = $presetManager->getList();
		$t = $plugin->getTranslator();
		$form = new SimpleForm(
			function(Player $player,?int $data) use($plugin,$presets,$presetManager,$t): void{
				if($data===null){
					return;
				}
				if($data===count($presets)){
					FormNavigator::back($player);
					return;
				}
				$worldName=$presets[$data];
				$preset=$presetManager->load($worldName);
				if($preset===null){
					$player->sendMessage($t->get("menu.presetlistmenu.send1").$worldName);
					FormNavigator::refresh($player);
					return;
				}
				$session=$plugin->getSessionManager()->get($player);
				$session->setPreset($preset);
			$player->sendMessage($t->get("menu.presetlistmenu.send2", ["worldname" => $worldName]));
				FormNavigator::back($player);
			}
		);
		
		$form->setTitle("Preset List Menu");
		if(count($presets)===0){
			$form->setContent($t->get("menu.presetlistmenu.send3"));
		}else{
			$form->setContent($t->get("menu.presetlistmenu.send4"));
		}
		foreach($presets as $preset){
			$form->addButton($preset);
		}
		$form->addButton($t->get("menu.buck"));
		
		$player->sendForm($form);
	}
}