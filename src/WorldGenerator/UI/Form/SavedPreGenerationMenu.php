<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Generator\Debug\DebugExporter;
use WorldGenerator\Generator\WorldGeneratorManager;
use WorldGenerator\UI\Form\DebugViewerForm;
use WorldGenerator\Language\Translator;

final class SavedPreGenerationMenu{

    public function __construct(
        private WorldGenerator $plugin
    ){}

	public function send(Player $player): void{
		$plugin = $this->plugin;
		$manager = $plugin->getWorldGeneratorManager();
		$generationsPath = rtrim($plugin->getDataFolder(),"/\\") . DIRECTORY_SEPARATOR . "generations";
		$savedWorlds = [];
		$session = $plugin->getSessionManager()->get($player);
		$preset  = $session->getPreset();
        $world = $preset->getWorld(); 
		$t = $plugin->getTranslator();
		
		if(is_dir($generationsPath)){
			$entries = scandir($generationsPath);
			if($entries !== false){
				foreach($entries as $entry){
					if($entry === "." || $entry === ".."){
						continue;
					}
					$generationPath = $generationsPath. DIRECTORY_SEPARATOR. $entry;
					if(!is_dir($generationPath)){
						continue;
					}
					if(!$manager->hasGenerationSave($generationPath)){
						continue;
					}
					$savedWorlds[] = ["name" => $entry,"path" => $generationPath];
				}
			}
		}

		$form = new SimpleForm(
			function(Player $player, ?int $data) use($savedWorlds, $plugin, $t): void{
				if($data === null){
					return;
				}
				if($data === count($savedWorlds)){
					FormNavigator::back($player);	// Последняя кнопка — возврат назад.
					return;
				}
				if(!isset($savedWorlds[$data])){
					return;
				}
				$selected = $savedWorlds[$data];
				$manager = $plugin->getWorldGeneratorManager();
				if(!$manager->loadGeneration($selected["path"])){
					$player->sendMessage($t->get("menu.savedpregeneration.send2", ["selectedname" => $selected["name"]]));
					$this->send($player);
					return;
				}
				$player->sendMessage($t->get("menu.savedpregeneration.send1", ["selectedname" => $selected["name"]]));
				DebugViewerForm::clear($player);
				FormNavigator::open($player,function(Player $player) use($plugin): void{DebugViewerForm::send($plugin, $player);});
			}
		);
		$form->setTitle("Saved Pre Generation Menu");
		if(count($savedWorlds) === 0){
			$form->setContent($t->get("menu.savedpregeneration.setcontent2", ["generationspath" => $generationsPath]));
		}else{
			$form->setContent($t->get("menu.savedpregeneration.setcontent1", ["savedworlds" => count($savedWorlds)]));
			foreach($savedWorlds as $world){
				$form->addButton($world["name"]);
			}
		}
		$form->addButton($t->get("menu.buck"));
		$player->sendForm($form);
	}
}