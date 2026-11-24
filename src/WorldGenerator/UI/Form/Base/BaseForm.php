<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form\Base;

use pocketmine\player\Player;
use WorldGenerator\Config\PlayerSession;
use WorldGenerator\Config\Preset;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\WorldGenerator;

abstract class BaseForm{

    protected WorldGenerator $plugin;

    public function __construct(WorldGenerator $plugin){
        $this->plugin=$plugin;
    }

    // Открытие формы
    abstract public function send(Player $player): void;
    // Получить сессию игрока
    protected function getSession(Player $player): PlayerSession{return $this->plugin->getConfigManager()->getSessionManager()->get($player);}
    // Получить текущий пресет
    protected function getPreset(Player $player): Preset{return $this->getSession($player)->getPreset();}
    // Возврат назад
    protected function back(Player $player): void{FormNavigator::back($player);}
}