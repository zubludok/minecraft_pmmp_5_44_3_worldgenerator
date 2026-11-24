<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use pocketmine\player\Player;
use pocketmine\scheduler\Task;
use WorldGenerator\WorldGenerator;
use WorldGenerator\Generator\Simulation\SimulationState;

final class SimulationCycleTask extends Task{

    private int $stage;
    private int $cycle;

    public function __construct(
        private WorldGenerator $plugin,
        private Player $player
    ){
        $simulation = $plugin->getWorldGeneratorManager()->getSimulationState();

        $this->stage = $simulation?->getStage() ?? -1;
        $this->cycle = $simulation?->getCycle() ?? -1;
    }

    public function onRun(): void{
        $manager = $this->plugin->getWorldGeneratorManager();
        $simulation = $manager->getSimulationState();

        if(
            !$this->player->isConnected() ||
            $simulation === null ||
            $simulation->getStage() !== $this->stage ||
            $simulation->getCycle() !== $this->cycle ||
            !$simulation->canStep()
        ){
            $this->finish();
            return;
        }

        // Не более пяти шагов за тик.
        for($i = 0; $i < 5; ++$i){
            if(!$manager->nextSimulationStep()){
                $this->finish();
                return;
            }

            $simulation = $manager->getSimulationState();

            if(
                $simulation === null ||
                $simulation->getStage() !== $this->stage ||
                $simulation->getCycle() !== $this->cycle ||
                !$simulation->canStep()
            ){
                $this->finish();
                return;
            }
        }
    }

	private function finish(): void{
		$this->getHandler()?->cancel();
		DebugViewerForm::clearCycleTask($this->player);
		if($this->player->isConnected()){
			DebugViewerForm::send($this->plugin, $this->player);
		}
	}
}