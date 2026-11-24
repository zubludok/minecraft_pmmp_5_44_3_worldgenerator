<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use WorldGenerator\WorldGenerator;
use pocketmine\scheduler\ClosureTask;
use WorldGenerator\Language\Translator;

final class FormationStageTask extends Task{

    private float $lastLoggedProgress = -1.0;
    private string $lastLoggedStatus = '';

    public function __construct(
        private WorldGenerator $plugin,
        private Player $player
    ){}

    public function onRun(): void{
		$session = $this->plugin->getSessionManager()->get($this->player);
		$t = $this->plugin->getTranslator();
        if(!$this->player->isConnected()){
            $this->stop();
            return;
        }
        $manager = $this->plugin->getWorldGeneratorManager();
        // Бюджет ограничивает число операций итеративного процесса
        // за один тик. При необходимости его можно уменьшить.
        $result = $manager->advanceFormationStage(100);
        $progress = max(0.0,min(1.0, $manager->getFormationProgress()));
        $status = $manager->getFormationStatus();
        // Не засоряем лог: записываем изменения статуса
        // и прогресс с шагом примерно в 5 процентов.
        $progressPercent = (int) floor($progress * 100);
        $lastPercent = $this->lastLoggedProgress < 0 ? -5 : (int) floor($this->lastLoggedProgress * 100);
        if($status !== $this->lastLoggedStatus || $progressPercent >= $lastPercent + 5 || $result !== null){
            $this->plugin->getLogger()->info($t->get("menu.formationstagetask.text1"). $progressPercent . '% — ' . $status);
            $this->lastLoggedProgress = $progress;
            $this->lastLoggedStatus = $status;
        }
        // null означает, что процесс ещё выполняется.
        if($result === null){
            return;
        }
        $this->stop();
        if(!$this->player->isConnected()){
            return;
        }
        if($result){
            $this->player->sendMessage($t->get("menu.formationstagetask.send1"));
        }else{
            $this->player->sendMessage($t->get("menu.formationstagetask.send2"));
            $this->plugin->getLogger()->warning($t->get("menu.formationstagetask.send3"));
        }
		// Даём клиенту время закрыть заглушку и принять новую форму.
		$plugin = $this->plugin;
		$player = $this->player;
		$plugin->getScheduler()->scheduleDelayedTask(
			new ClosureTask(
				static function() use($plugin, $player): void{
					if(!$player->isConnected()){
						return;
					}
					DebugViewerForm::send($plugin, $player);
				}
			),
			10
		);
    }

    private function stop(): void{$this->getHandler()?->cancel();DebugViewerForm::clearFormationTask($this->player);}
}