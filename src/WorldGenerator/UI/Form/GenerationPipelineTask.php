<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use Closure;
use Throwable;
use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use WorldGenerator\WorldGenerator;
use WorldGenerator\Generator\Simulation\IncrementalStage;
use WorldGenerator\Language\Translator;

final class GenerationPipelineTask extends Task{

    private static array $activeTasks = [];

    private bool $completed = false;

    private function __construct(
		private WorldGenerator $plugin,
        private Player $player,
        private IncrementalStage $stage
    ){}

    public static function start(WorldGenerator $plugin,Player $player,IncrementalStage $stage): bool{
        $key = strtolower($player->getName());
        if(isset(self::$activeTasks[$key])){
            return false;
        }
        $task = new self($plugin, $player, $stage);
        self::$activeTasks[$key] = $task;
        StageProgressForm::send($player, $stage->getName());
        $plugin->getScheduler()->scheduleRepeatingTask($task, 1);
        return true;
    }

    public function onRun(): void{
        if(!$this->player->isConnected()){
            $this->stop();
            return;
        }
        try{
            if(!$this->stage->advance(128)){
                return;
            }
            $this->completed = true;
            $this->stop();
            DebugViewerForm::send($this->player);
        }catch(Throwable $e){
            $this->stop();
            $this->player->sendMessage($this->plugin->getTranslator()->get("menu.generationpipelinetask.send1", ["getname" => $this->stage->getName(), "error" => $e->getMessage())];
            $this->player->getServer()->getLogger()->logException($e);
            DebugViewerForm::send($this->player);
        }
    }

    public function cancelPipeline(): void{$this->stop();}
	
    private function stop(): void{
        $key = strtolower($this->player->getName());
        if(isset(self::$activeTasks[$key]) && self::$activeTasks[$key] === $this){
            unset(self::$activeTasks[$key]);
        }
        $handler = $this->getHandler();
        if($handler !== null){
            $handler->cancel();
        }
    }

    public static function isRunning(Player $player): bool{return isset(self::$activeTasks[strtolower($player->getName())]);}
}