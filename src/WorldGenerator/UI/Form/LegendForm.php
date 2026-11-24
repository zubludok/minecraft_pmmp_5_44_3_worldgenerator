<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;

final class LegendForm{

    public static function send(WorldGenerator $plugin,Player $player,string $mode): void{
        $translator = $plugin->getTranslator();
        $config = DebugViewerStageRegistry::getLegendConfig($mode);
        $buttons = $config["buttons"];
        $form = new SimpleForm(
            function(Player $player, ?int $data) use($plugin,$mode,$buttons): void{
                if($data === null || $data >= count($buttons)){
                    DebugViewerForm::send($plugin, $player);
                    return;
                }
            }
        );

        $form->setTitle("Legend Form");
        $form->setContent($config["description"]);
        // Примерные текстуры режима.
        foreach($buttons as $button){
            $form->addButton($button["text"],0,$button["texture"]);
        }
        // Кнопка возврата располагается последней.
        $form->addButton($translator->get("menu.buck"));

        $player->sendForm($form);
    }
}