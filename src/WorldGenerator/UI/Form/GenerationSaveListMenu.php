<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\WorldGenerator;

final class GenerationSaveListMenu{

    public function __construct(
        private WorldGenerator $plugin
    ){}

    public function send(Player $player): void{
        $basePath = rtrim($this->plugin->getDataFolder(),DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . "generations";
        $worldPaths = [];
        foreach(glob($basePath . DIRECTORY_SEPARATOR . "*", GLOB_ONLYDIR) ?: [] as $directory){
            $debugPath = $directory . DIRECTORY_SEPARATOR . "debug";
            $saveFile = $debugPath . DIRECTORY_SEPARATOR . "generation_save.json";
            $formationFile = $debugPath . DIRECTORY_SEPARATOR . "formation.json";
            // Для создания игрового мира нужны оба файла.
            if(!is_file($saveFile) || !is_file($formationFile)){
                continue;
            }
            $worldName = basename($directory);
            $worldPaths[] = ["name" => $worldName,"path" => $directory,];
        }

        usort(
            $worldPaths,
            static fn(array $a, array $b): int =>
                strnatcasecmp($a["name"], $b["name"])
        );
        $form = new SimpleForm(
            function(Player $player, ?int $data) use($worldPaths): void{
                if($data === null){
                    return;
                }
                // Последняя кнопка — возврат назад.
                if($data === count($worldPaths)){
                    FormNavigator::back($player);
                    return;
                }
                if(!isset($worldPaths[$data])){
                    return;
                }
                $selected = $worldPaths[$data];
                $manager = $this->plugin->getWorldGeneratorManager();
			$player->sendMessage("§l§eВосстановление предгенерации: «§r".$selected["name"]."»");
                if(!$manager->loadGeneration($selected["path"])){
                    $error = $manager->getWorldCreationError();
                    $player->sendMessage("§l§cНе удалось загрузить предгенерацию: §r".$error);
                    return;
                }
                if(!$manager->createPlayableWorld()){
                    $error = $manager->getWorldCreationError();
                    $player->sendMessage("§l§cНе удалось создать игровой мир: §r".$error);
                    return;
                }
                $player->sendMessage("§l§aИгровой мир успешно создан: §r".$selected["name"]);
            }
        );

        $form->setTitle("Generation Save List Menu");
        if($worldPaths === []){
            $form->setContent("§l§cСохранённые предгенерации не найдены. \n\nДля каждой предгенерации должны существовать файлы: \n§fdebug/generation_save.json \n debug/formation.json§r");
        }else{
            $form->setContent("§lВыберите предгенерацию для создания игрового мира: §r");
            foreach($worldPaths as $entry){$form->addButton($entry["name"]);}
        }
        $form->addButton("Назад");
		
        $player->sendForm($form);
    }
}