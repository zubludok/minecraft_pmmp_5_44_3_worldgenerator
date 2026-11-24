<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\CustomForm;
use pocketmine\player\Player;
use WorldGenerator\UI\Form\Base\BaseForm;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Language\Translator;

final class AdditionalGenerationForm extends BaseForm{

    public function send(Player $player): void{
        $preset = $this->getPreset($player);
        $generator = $preset->getGenerator();
        $t = $this->plugin->getTranslator();

        $form=new CustomForm(
            function(Player $player, ?array $data) use($preset, $t): void{
                if($data===null){return;}
				$generator = $preset->getGenerator();
				$generator->setMountainRangesPercent((int)($data[1] ?? $generator->getMountainRangesPercent()));
				$generator->setCapesPercent((int)($data[2] ?? $generator->getCapesPercent()));
				$generator->setBasinsPercent((int)($data[3] ?? $generator->getBasinsPercent()));
				$player->sendMessage($t->get("menu.additionalgeneration.send1"));
				FormNavigator::back($player);
			}
		);

        $form->setTitle("Additional Generation Form");
        $form->addLabel($t->get("menu.additionalgeneration.addlabel1"));
        $form->addSlider($t->get("menu.additionalgeneration.addslider1"),0,50,1,$generator->getMountainRangesPercent());
        $form->addSlider($t->get("menu.additionalgeneration.addslider2"),0,50,1,$generator->getCapesPercent());
        $form->addSlider($t->get("menu.additionalgeneration.addslider3"),0,50,1,$generator->getBasinsPercent());
		
		$player->sendForm($form);
    }
}