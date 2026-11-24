<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\SimpleForm;
use pocketmine\player\Player;

final class StageProgressForm{

    public static function send(Player $player, string $stageName): void{
		$form = new SimpleForm(
			static function(Player $player, ?int $data): void{
				// Игрок может закрыть заглушку вручную.
				// Вычисления при этом продолжаются.
			}
		);
		$form->setTitle('WorldGenerator');
		$form->setContent(
			"§lФормирование островов§r\n\n"
			. "Выполняется разделение массы на острова.\n"
			. "Прогресс вычислений записывается в лог сервера.\n\n"
			. "§eПожалуйста, закрой это окно вручную.§r\n"
			. "§7После завершения этапа окно просмотра карты "
			. "будет открыто автоматически."
		);
		$form->addButton('Понятно');
		$this->player->sendForm($form);
    }
}