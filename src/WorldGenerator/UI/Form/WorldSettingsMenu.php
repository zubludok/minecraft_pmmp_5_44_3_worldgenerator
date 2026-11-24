<?php

declare(strict_types=1);

namespace WorldGenerator\UI\Form;

use FormAPI\CustomForm;
use pocketmine\player\Player;
use WorldGenerator\UI\Form\Base\BaseForm;
use WorldGenerator\UI\Navigator\FormNavigator;
use WorldGenerator\Language\Translator;

final class WorldSettingsMenu extends BaseForm{

    public function send(Player $player): void{
        $preset = $this->getPreset($player);
        $world = $preset->getWorld(); 
		$t = $this->plugin->getTranslator();
        $form = new CustomForm(
            function(Player $player, ?array $data) use($preset, $t): void{
                if($data === null){	// Закрытие формы без сохранения.
                    return;
                }
                $world = $preset->getWorld();
				$oldSectorCountX=$world->getSectorCountX();
				$oldSectorCountZ=$world->getSectorCountZ();
                /*
                 * 0  - label
                 * 1  - название мира
                 * 2  - seed
                 * 3  - label "Один сектор..."
                 * 4  - размер X
                 * 5  - размер Z
                 * 6  - высота мира
                 * 7  - тип генерации
                 * 8  - количество основных островов
                 * 9  - процент суши
                 * 10 - уровень воды
                 * 11 - толщина водяного слоя
                 */

                // Название мира
                $name = trim((string)($data[1] ?? $world->getName()));
                if($name !== ""){
                    $world->setName($name);
                }
                // Seed
                $seed = (int)($data[2] ?? $world->getSeed());
                $world->setSeed($seed);
                // Размер X
                $sectorCountX = (int)($data[4] ?? $world->getSectorCountX());
                $world->setSectorCountX(max(1,$sectorCountX));
                // Размер Z
                $sectorCountZ = (int)($data[5] ?? $world->getSectorCountZ());
                $world->setSectorCountZ(max(1,$sectorCountZ));
                // Сброс смещения экватора при изменении размера карты
				if($oldSectorCountX !== $sectorCountX || $oldSectorCountZ !== $sectorCountZ){
					$preset->getGenerator()->setPolarShift(0);
				}
				// Высота мира
                $worldHeight = (int)($data[6] ?? $world->getWorldHeight());
                $world->setWorldHeight(max(16,$worldHeight));
                // Тип генерации
                $generationTypes=[
                    "islands",
                    "none"	// TODO: Луна, марс, парящие острова
                ];
                $generationIndex=(int)($data[7] ?? 0);
                $generationType=$generationTypes[$generationIndex] ?? $world->getGenerationType();
                $world->setGenerationType($generationType);
                // Количество основных островов
                $islandCount=(int)($data[8] ?? $world->getIslandCount());
                $world->setIslandCount(max(1,$islandCount));
                // Процент суши
                $landPercent=(int)($data[9] ?? $world->getLandPercent());
                $world->setLandPercent(
                    max(1,min(99,$landPercent))
                );
                // Уровень воды
                $waterLevel=(int)($data[10] ?? $world->getWaterLevel());
                $waterLevel=max(1,min($world->getWorldHeight()-1,$waterLevel));
                $world->setWaterLevel($waterLevel);
                // Толщина водяного слоя
                $aquiferLevel=(int)($data[11] ?? $world->getAquiferLevel());
                $world->setAquiferLevel(max(1,min(100,$aquiferLevel)));
                $player->sendMessage($t->get("menu.worldsettings.send1"));
                FormNavigator::back($player);
            }
        );

        $form->setTitle("World Settings Menu");
        // Заголовочная информация.
        $form->addLabel($t->get("menu.worldsettings.addlabel1"));
        $form->addInput($t->get("menu.worldsettings.addinput1"),$world->getName());
        $form->addInput($t->get("menu.worldsettings.addinput2"),(string)$world->getSeed());
        $form->addLabel($t->get("menu.worldsettings.addlabel2"));
        $form->addSlider($t->get("menu.worldsettings.addslider1"),8,64,2,$world->getSectorCountX());
        $form->addSlider($t->get("menu.worldsettings.addslider2"),8,64,2,$world->getSectorCountZ());
        $form->addSlider($t->get("menu.worldsettings.addslider3"),16,320,1,$world->getWorldHeight());
			$generationTypes=[$t->get("menu.worldsettings.generationtypes1"),$t->get("menu.worldsettings.generationtypes2")];
			$generationIndex=match($world->getGenerationType()){"continent" => 1,default => 0};
        $form->addDropdown($t->get("menu.worldsettings.adddropdown"),$generationTypes,$generationIndex);
        $form->addSlider($t->get("menu.worldsettings.addslider4"),1,20,1,$world->getIslandCount());
        $form->addSlider($t->get("menu.worldsettings.addslider5"),1,99,1,$world->getLandPercent());
        $form->addSlider($t->get("menu.worldsettings.addslider6"),1,max(1,$world->getWorldHeight()-1),1,min($world->getWaterLevel(),max(1,$world->getWorldHeight()-1)));
        $form->addLabel($t->get("menu.worldsettings.addlabel3"));
        $form->addSlider($t->get("menu.worldsettings.addslider7"),1,10,1,$world->getAquiferLevel());
		
        $player->sendForm($form);
    }
}