<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use pocketmine\Server;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\UI\Form\Base\BaseForm;
use WorldGenerator\WorldGenerator;

final class WorldListMenu extends BaseForm{

    public function send(Player $player): void{
        $worldPath=Server::getInstance()->getDataPath()."worlds/";
        $worlds=[];
		$t = $this->plugin->getTranslator();
		
        foreach(scandir($worldPath) as $file){
            if($file==="."||$file===".."){continue;}
            if(is_dir($worldPath.$file)){
                $worlds[]=$file;
            }
        }
        $form=new SimpleForm(function(Player $player,?int $data) use($worlds, $t): void{
            if($data===null){return;}
            if($data===count($worlds)){
                FormNavigator::back($player);
                return;
            }
            $name=$worlds[$data];
            $manager=Server::getInstance()->getWorldManager();
            if(!$manager->isWorldLoaded($name)){
                $manager->loadWorld($name);
            }
            $world=$manager->getWorldByName($name);
            if($world!==null){
                $player->teleport($world->getSpawnLocation());
                $player->sendMessage($t->get("menu.worldlist.send1", ["name" => $name]));
            }else{
                $player->sendMessage($t->get("menu.worldlist.send2"));
            }
        });
        $form->setTitle("World List Menu");
        foreach($worlds as $world){
            $form->addButton($world);
        }
        $form->addButton($t->get("menu.buck"));
        $player->sendForm($form);
    }

}