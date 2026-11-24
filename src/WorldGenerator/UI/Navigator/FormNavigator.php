<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Navigator;

use pocketmine\player\Player;

final class FormNavigator{

    /** @var array<string,array<int,callable>> */
    private static array $history=[];

    // Открыть новую форму
	public static function open(Player $player,callable $page): void{
		self::$history[$player->getName()][]=$page;
		$page($player);
	}

    // Заменить текущую форму
    public static function replace(Player $player,callable $form): void{
        $name=$player->getName();
        array_pop(self::$history[$name]);
        self::$history[$name][]=$form;
        $form($player);
    }

    // Вернуться назад
	public static function back(Player $player): void{
		$name=$player->getName();
		if(!isset(self::$history[$name]))return;
		if(count(self::$history[$name])<=1)return;
		array_pop(self::$history[$name]);
		$page=end(self::$history[$name]);
		if($page!==false){
			$page($player);
		}
	}

	// Обновить текущую страницу
	public static function refresh(Player $player): void{
		$name=$player->getName();
		if(empty(self::$history[$name]))return;
		$page=end(self::$history[$name]);
		if($page!==false){
			$page($player);
		}
	}
	
    // Очистить историю
    public static function clear(Player $player): void{
        unset(self::$history[$player->getName()]);
    }
}