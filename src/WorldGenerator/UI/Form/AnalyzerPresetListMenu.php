<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\world\WorldCreationOptions;
use WorldGenerator\WorldGenerator;
use WorldGenerator\Generator\Generators\LandscapeGenerator;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Language\Translator;

final class AnalyzerPresetListMenu{

    public function __construct(private WorldGenerator $plugin){}

    public function send(Player $player): void{
		$t = $this->plugin->getTranslator();
        $basePath = rtrim($this->plugin->getDataFolder(),DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "generations";
        $maps = [];
        if(is_dir($basePath)){
            foreach(scandir($basePath) ?: [] as $name){
                if($name === "." || $name === ".."){continue;}
                $directory = $basePath . DIRECTORY_SEPARATOR . $name;
                if(!is_dir($directory)){continue;}
                $jsonPath = $directory . DIRECTORY_SEPARATOR . "debug" . DIRECTORY_SEPARATOR . "formation.json";
                if(!is_file($jsonPath) || !is_readable($jsonPath)){continue;}
                $json = file_get_contents($jsonPath);
                if($json === false){continue;}
                try{$data = json_decode($json,true,512,JSON_THROW_ON_ERROR);
                }catch(\JsonException){continue;}
                if(!is_array($data) || ($data["type"] ?? null) !== "formation" || !isset($data["sizeX"], $data["sizeZ"], $data["cells"]) || !is_array($data["cells"])){continue;}
                $maps[$name] = ["path" => $jsonPath,"worldHeight" => max(1,(int)($data["worldHeight"] ?? 320)),"seed" => (int)($data["seed"] ?? 0),];
            }
        }
        ksort($maps, SORT_NATURAL | SORT_FLAG_CASE);
        $names = array_keys($maps);
        $form = new SimpleForm(
            function(Player $player, ?int $data) use($names, $maps, $t): void{
                if($data === null){return;}
                if($data === count($names)){
                    FormNavigator::back($player);
                    return;
                }
                if(!isset($names[$data])){return;}
                $name = $names[$data];
                $map = $maps[$name];
                try{$optionsJson = json_encode(["landscapePath" => $map["path"],"worldHeight" => $map["worldHeight"],], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);
                    $options = WorldCreationOptions::create()->setGeneratorClass(LandscapeGenerator::class)->setSeed($map["seed"])->setGeneratorOptions($optionsJson);
                    $created = $this->plugin->getServer()->getWorldManager()->generateWorld($name, $options, true);
                    if(!$created){
                        $player->sendMessage($t->get("menu.analyzerpresetlist.send1", ["name" => $name]));
                        return;
                    }
                    $player->sendMessage($t->get("menu.analyzerpresetlist.send2", ["name" => $name]));
                }catch(\Throwable $e){
                    $this->plugin->getLogger()->error($t->get("menu.analyzerpresetlist.send3", ["name" => $name]) . $e->getMessage());
                    $player->sendMessage($t->get("menu.analyzerpresetlist.send4", ["name" => $name]));
                }
            }
        );

        $form->setTitle("Analyzer Preset List Menu");
        if($names === []){
            $form->setContent($t->get("menu.analyzerpresetlist.send5") . $basePath);
        }else{
            $form->setContent($t->get("menu.analyzerpresetlist.send6"));
        }
        foreach($names as $name){$form->addButton($name);}
        $form->addButton($t->get("menu.buck"));
		
        $player->sendForm($form);
    }
}