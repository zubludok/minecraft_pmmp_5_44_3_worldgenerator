<?php

declare(strict_types=1);

namespace WorldGenerator\UI;

use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;
use WorldGenerator\UI\Form\MainMenuForm;

final class UIManager{

    private WorldGenerator $plugin;

    public function __construct(WorldGenerator $plugin){
        $this->plugin=$plugin;
    }

    // Главное меню
	public function openMainMenu(Player $player): void{
		$plugin=$this->plugin;
		\WorldGenerator\UI\Navigator\FormNavigator::open($player,function(Player $player) use($plugin): void{
			MainMenuForm::send($plugin,$player);
		});
	}
}