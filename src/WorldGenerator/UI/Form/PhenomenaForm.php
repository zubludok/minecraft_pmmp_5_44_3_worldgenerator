<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\Language\Translator;

final class PhenomenaForm{

    public static function send(WorldGenerator $plugin, Player $player): void{
        $t = $plugin->getTranslator();
        $form = new SimpleForm(
            function(Player $player, ?int $data) use($plugin): void{
                // Единственная кнопка — возврат к карте.
                DebugViewerForm::send($plugin, $player);
            }
        );

        $form->setTitle("Phenomena Form");
        $form->setContent("");
        $form->addButton($t->get("menu.buck"));

        $player->sendForm($form);
    }
}