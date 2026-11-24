<?php

declare(strict_types=1);

namespace WorldGenerator\Config;

use pocketmine\player\Player;

final class SessionManager{

    /** @var PlayerSession[] */
    private array $sessions=[];

    // Получить сессию игрока
    public function get(Player $player): PlayerSession{
        $name=strtolower($player->getName());
        if(!isset($this->sessions[$name])){
            $this->sessions[$name]=new PlayerSession();
        }
        return $this->sessions[$name];
    }

    // Закрыть сессию
    public function remove(Player $player): void{
        unset($this->sessions[strtolower($player->getName())]);
    }
}