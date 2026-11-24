<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Language\Translator;

final class PlaceholderForm{

    // Временное окно
    public static function send(Player $player, string $title): void{
        $t = $plugin->getTranslator();
        $form=new SimpleForm(function(Player $player,?int $data): void{
			if($data===null){
				return;
			} // закрытие формы без сохранения (быстрый выход)
			if($data === 0){	// Кнопка "Назад"
				FormNavigator::back($player);
			}
        });
        $form->setTitle($title);
        $form->setContent("Раздел находится в разработке.");
        $form->addButton($t->get("menu.buck"));
        $player->sendForm($form);
    }
}