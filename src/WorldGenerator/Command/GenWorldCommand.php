<?php

declare(strict_types=1);

namespace WorldGenerator\Command;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use WorldGenerator\WorldGenerator;

final class GenWorldCommand extends Command{

    private WorldGenerator $plugin;

    public function __construct(WorldGenerator $plugin){
        parent::__construct("genworld","Открыть меню генерации мира");
        $this->setPermission("worldgenerator.command");
        $this->plugin=$plugin;
    }

    // Открывает главное меню
	public function execute(CommandSender $sender,string $label,array $args): bool{
		if(!$sender instanceof Player)return true;
		if(!$this->testPermission($sender))return true;
		\WorldGenerator\UI\Navigator\FormNavigator::clear($sender);
		$this->plugin->getUIManager()->openMainMenu($sender);
		return true;
	}
}